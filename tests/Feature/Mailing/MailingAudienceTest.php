<?php

namespace Tests\Feature\Mailing;

use App\Models\Mailbox;
use App\Models\MailingCampaign;
use App\Models\MailingContact;
use App\Models\MailingDelivery;
use App\Models\MailingTag;
use App\Models\MailingTrigger;
use App\Models\MailSuppression;
use App\Models\Product;
use App\Models\ServiceClient;
use App\Models\User;
use App\Services\Mailing\MailingSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

/**
 * Adresár z projektu (štítky, atribúty, súhlas) a výber adresátov kampane
 * podľa druhu (ponuka / oznámenie o službe) a segmentu.
 */
class MailingAudienceTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Http::fake();
        config(['accounts.mailing.hourly_limit' => 100, 'accounts.mailing.batch_size' => 50]);

        $this->product = Product::factory()->create(['key' => 'anonymizer', 'name' => 'Anonymizér']);
        [, $this->token] = ServiceClient::issue($this->product, 'test');

        Mailbox::create([
            'product_id' => $this->product->id,
            'name' => 'Podpora',
            'from_address' => 'podpora@anonymizer.test',
            'is_default' => true,
        ]);
    }

    public function test_projekt_zaregistruje_stitky_a_zoznam_nahradi_povodny(): void
    {
        MailingTag::create(['product_id' => $this->product->id, 'key' => 'stary', 'label' => 'Starý']);

        $this->api('PUT', '/api/v1/mailing/tags', ['tags' => [
            ['key' => 'trial', 'label' => 'Vyskúšal verziu', 'description' => 'Skúška bez účtu'],
            ['key' => 'verified', 'label' => 'Overený účet'],
        ]])->assertOk()->assertJsonCount(2, 'data');

        $this->assertSame(['trial', 'verified'], MailingTag::where('product_id', $this->product->id)->orderBy('key')->pluck('key')->all());
    }

    public function test_kontakty_z_projektu_nahradia_stitky_a_suhlas_neodoberu(): void
    {
        $this->api('POST', '/api/v1/mailing/contacts', ['contacts' => [
            ['email' => 'Jana@Firma.test', 'name' => 'Jana', 'tags' => ['user', 'trial', 'user'], 'attributes' => ['meno' => 'Jana']],
            ['email' => 'peter@firma.test', 'tags' => ['user'], 'permission_note' => 'súhlas pri registrácii'],
        ]])->assertCreated()->assertJsonPath('data.added', 2);

        $jana = MailingContact::where('email', 'jana@firma.test')->sole();
        $this->assertSame(['trial', 'user'], $jana->tags);
        $this->assertSame(['meno' => 'Jana'], $jana->attributes);
        $this->assertFalse($jana->hasConsent());

        // Nová sada štítkov nahradí starú; súhlas sa doplní, poslaný bez súhlasu sa neodoberie.
        $this->api('POST', '/api/v1/mailing/contacts', ['contacts' => [
            ['email' => 'jana@firma.test', 'tags' => ['user', 'verified'], 'permission_note' => 'checkbox v profile'],
            ['email' => 'peter@firma.test', 'tags' => []],
        ]])->assertJsonPath('data.updated', 2);

        $jana->refresh();
        $this->assertSame(['user', 'verified'], $jana->tags);
        $this->assertSame('checkbox v profile', $jana->permission_note);
        $peter = MailingContact::where('email', 'peter@firma.test')->sole();
        $this->assertNull($peter->tags);
        $this->assertTrue($peter->hasConsent());
    }

    public function test_odhlasenie_z_projektu_plati_len_ak_je_novsie(): void
    {
        $contact = $this->contact('jana@firma.test');
        $contact->forceFill(['unsubscribed_at' => now()->subDay(), 'unsubscribed_changed_at' => now()->subDay()])->save();

        // Staršia zmena z projektu odhlásenie v Accounte neprepíše.
        $this->api('POST', '/api/v1/mailing/contacts', ['contacts' => [
            ['email' => 'jana@firma.test', 'unsubscribed' => false, 'changed_at' => now()->subDays(2)->toIso8601String()],
        ]]);
        $this->assertNotNull($contact->fresh()->unsubscribed_at);

        // Novšie opätovné prihlásenie v projekte (človek to zmenil v profile) áno.
        $this->api('POST', '/api/v1/mailing/contacts', ['contacts' => [
            ['email' => 'jana@firma.test', 'unsubscribed' => false, 'changed_at' => now()->toIso8601String()],
        ]]);
        $this->assertNull($contact->fresh()->unsubscribed_at);
    }

    public function test_projekt_odstrani_kontakt_aj_s_cakajucimi_mailmi(): void
    {
        $contact = $this->contact('jana@firma.test');
        $campaign = $this->campaign();
        $delivery = MailingDelivery::create(['campaign_id' => $campaign->id, 'contact_id' => $contact->id, 'email' => $contact->email, 'status' => MailingDelivery::PENDING, 'dedupe_key' => '']);

        $this->api('POST', '/api/v1/mailing/contacts/remove', ['emails' => ['JANA@firma.test', 'nikto@firma.test']])
            ->assertOk()->assertJsonPath('data.removed', 1);

        // Kontakt je len skrytý: nedostane kampaň, história ostane naviazaná.
        $this->assertSame(0, MailingContact::count());
        $this->assertTrue($contact->fresh()->trashed());
        $this->assertSame(MailingDelivery::SKIPPED, $delivery->fresh()->status);
        $this->assertSame($contact->id, $delivery->fresh()->contact_id);
        $this->assertSame(0, MailingContact::audience($this->campaign(['kind' => MailingCampaign::SERVICE]))->count());
        $this->api('GET', '/api/v1/mailing/contacts/jana@firma.test', [])->assertJsonPath('data.exists', false);
    }

    public function test_odstraneny_kontakt_sa_po_novej_objednavke_obnovi_aj_s_odhlasenim(): void
    {
        $contact = $this->contact('jana@firma.test', ['customer']);
        $contact->forceFill(['unsubscribed_at' => now()->subWeek()])->save();
        $token = $contact->token;

        $this->api('POST', '/api/v1/mailing/contacts/remove', ['emails' => ['jana@firma.test']]);
        // Starý odkaz na odhlásenie funguje aj pre odstránený kontakt.
        $this->get("/m/u/{$token}")->assertOk();

        $this->api('POST', '/api/v1/mailing/contacts', ['contacts' => [
            ['email' => 'jana@firma.test', 'tags' => ['customer', 'school'], 'permission_note' => 'súhlas pri objednávke'],
        ]])->assertJsonPath('data.added', 1);

        $restored = MailingContact::sole();
        $this->assertSame($contact->id, $restored->id);
        $this->assertSame($token, $restored->token);
        $this->assertNotNull($restored->unsubscribed_at);
        $this->assertSame(['customer', 'school'], $restored->tags);
    }

    public function test_rucne_zmazanie_v_accounte_je_trvale(): void
    {
        $contact = $this->contact('jana@firma.test');

        $this->actingAs(User::factory()->create())->delete("/mailing/contacts/{$contact->id}")->assertSessionHas('success');

        $this->assertSame(0, MailingContact::withTrashed()->count());
    }

    public function test_ponuka_ide_len_so_suhlasom_a_v_segmente(): void
    {
        $this->contact('trial@firma.test', ['trial']);
        $this->contact('overeny@firma.test', ['trial', 'verified']);
        $this->contact('bezstitku@firma.test');
        $this->contact('bezsuhlasu@firma.test', ['trial'], consent: false);
        $this->contact('odhlaseny@firma.test', ['trial'])->forceFill(['unsubscribed_at' => now()])->save();

        $campaign = $this->campaign(['status' => MailingCampaign::DRAFT, 'audience' => ['any' => ['trial'], 'none' => ['verified']]]);
        $this->actingAs(User::factory()->create())->post("/mailing/{$campaign->id}/queue")->assertSessionHas('success');

        $this->assertSame(['trial@firma.test'], MailingDelivery::pluck('email')->all());
    }

    public function test_oznamenie_ide_aj_bez_suhlasu_bez_odhlasenia_ale_nie_na_blokovanu_adresu(): void
    {
        $this->contact('suhlas@firma.test', ['user']);
        $this->contact('bezsuhlasu@firma.test', ['user'], consent: false, attributes: ['meno' => 'Peter']);
        $this->contact('odhlaseny@firma.test', ['user'])->forceFill(['unsubscribed_at' => now()])->save();
        $this->contact('bounce@firma.test', ['user'], consent: false);
        MailSuppression::create(['product_id' => $this->product->id, 'email' => 'bounce@firma.test', 'reason' => MailSuppression::HARD_BOUNCE]);

        $campaign = $this->campaign([
            'status' => MailingCampaign::DRAFT,
            'kind' => MailingCampaign::SERVICE,
            'subject' => 'Zmena podmienok',
            'body' => 'Dobrý deň {meno}, meníme podmienky.',
        ]);
        $this->actingAs(User::factory()->create())->post("/mailing/{$campaign->id}/queue")->assertSessionHas('success');

        $this->assertEqualsCanonicalizing(['suhlas@firma.test', 'bezsuhlasu@firma.test', 'odhlaseny@firma.test'], MailingDelivery::pluck('email')->all());
        $this->assertSame(3, app(MailingSender::class)->runBatch());

        $sent = collect($this->sentEmails())->keyBy(fn (Email $e) => $e->getTo()[0]->getAddress());
        $this->assertCount(3, $sent);
        $this->assertNull($sent['bezsuhlasu@firma.test']->getHeaders()->get('List-Unsubscribe'));
        $this->assertStringNotContainsString('/m/u/', $sent['bezsuhlasu@firma.test']->getHtmlBody());
        // Atribút kontaktu z projektu sa dosadí do textu.
        $this->assertStringContainsString('Dobrý deň Peter', $sent['bezsuhlasu@firma.test']->getHtmlBody());
    }

    public function test_automatizacia_respektuje_segment_a_druh(): void
    {
        MailingTrigger::create(['product_id' => $this->product->id, 'key' => 'trial_expired', 'label' => 'Koniec skúšky']);
        $offer = $this->campaign(['trigger_event' => 'trial_expired', 'audience' => ['none' => ['verified']]]);
        $service = $this->campaign(['trigger_event' => 'trial_expired', 'kind' => MailingCampaign::SERVICE, 'audience' => ['any' => ['trial']]]);

        $this->contact('trial@firma.test', ['trial'], consent: false);
        $this->contact('overeny@firma.test', ['trial', 'verified']);

        $this->api('POST', '/api/v1/mailing/events', ['event' => 'trial_expired', 'email' => 'trial@firma.test'])->assertJsonPath('data.queued', 1);
        $this->api('POST', '/api/v1/mailing/events', ['event' => 'trial_expired', 'email' => 'overeny@firma.test'])->assertJsonPath('data.queued', 1);

        $this->assertSame(
            [[$service->id, 'trial@firma.test'], [$service->id, 'overeny@firma.test']],
            MailingDelivery::orderBy('id')->get()->map(fn ($d) => [$d->campaign_id, $d->email])->all(),
        );
        $this->assertSame(0, $offer->deliveries()->count());
    }

    public function test_pocet_adresatov_a_editor_so_stitkami(): void
    {
        $user = User::factory()->create();
        MailingTag::create(['product_id' => $this->product->id, 'key' => 'trial', 'label' => 'Vyskúšal verziu']);
        $this->contact('a@firma.test', ['trial']);
        $this->contact('b@firma.test', ['user'], consent: false);

        $this->actingAs($user)->postJson('/mailing/audience-count', ['product_key' => 'anonymizer', 'kind' => 'offer'])
            ->assertOk()->assertJsonPath('count', 1);
        $this->actingAs($user)->postJson('/mailing/audience-count', ['product_key' => 'anonymizer', 'kind' => 'service'])
            ->assertJsonPath('count', 2);
        $this->actingAs($user)->postJson('/mailing/audience-count', ['product_key' => 'anonymizer', 'kind' => 'service', 'audience' => ['any' => ['user']]])
            ->assertJsonPath('count', 1);

        $this->actingAs($user)->get('/mailing/create?product=anonymizer')->assertOk()
            ->assertInertia(fn ($page) => $page->where('products.0.tags.0.key', 'trial')->where('products.0.tags.1.key', 'user'));
        $this->actingAs($user)->get('/mailing/contacts?product=anonymizer&tag=trial')->assertOk()
            ->assertInertia(fn ($page) => $page->has('contacts.data', 1));
    }

    /** @param array<string, mixed> $attributes */
    private function campaign(array $attributes = []): MailingCampaign
    {
        return MailingCampaign::create([
            'product_id' => $this->product->id,
            'name' => 'Kampaň',
            'subject' => 'Ponuka',
            'body' => 'Text',
            'status' => MailingCampaign::QUEUED,
            'scheduled_at' => now()->subMinute(),
            ...$attributes,
        ]);
    }

    /**
     * @param  list<string>  $tags
     * @param  array<string, string>  $attributes
     */
    private function contact(string $email, array $tags = [], bool $consent = true, array $attributes = []): MailingContact
    {
        return MailingContact::create([
            'product_id' => $this->product->id,
            'email' => $email,
            'tags' => $tags ?: null,
            'attributes' => $attributes ?: null,
            'permission_note' => $consent ? 'registrácia na webe' : null,
            'permission_at' => $consent ? now() : null,
        ]);
    }

    /** @param array<string, mixed> $data */
    private function api(string $method, string $uri, array $data): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->token)->json($method, $uri, $data);
    }

    /** @return array<int, Email> */
    private function sentEmails(): array
    {
        return collect(app('mailer')->getSymfonyTransport()->messages())
            ->map(fn ($sent) => $sent->getOriginalMessage())
            ->values()
            ->all();
    }
}
