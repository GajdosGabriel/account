<?php

namespace Tests\Feature\Mailing;

use App\Console\Commands\ImportMailing;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Mailbox;
use App\Models\MailingCampaign;
use App\Models\MailingContact;
use App\Models\MailingDelivery;
use App\Models\MailingLink;
use App\Models\MailingTemplate;
use App\Models\MailingTrigger;
use App\Models\MailSuppression;
use App\Models\Product;
use App\Models\ServiceClient;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\Mailing\BounceClassifier;
use App\Services\Mailing\MailingSender;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

/**
 * Hromadný emailing: udalosti z projektu, dávkové odosielanie, odhlásenie
 * a meranie. Mailer je `array`, fronta synchrónna – mail naozaj „odíde“.
 */
class MailingTest extends TestCase
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

        $this->product = Product::factory()->create(['key' => 'zastavy', 'name' => 'Zástavy']);
        [, $this->token] = ServiceClient::issue($this->product, 'test');

        Mailbox::create([
            'product_id' => $this->product->id,
            'name' => 'Obchod',
            'from_address' => 'obchod@zastavy.test',
            'from_name' => 'Zástavy',
            'is_default' => true,
        ]);

        MailingTrigger::create([
            'product_id' => $this->product->id,
            'key' => 'order_created',
            'label' => 'Po vytvorení objednávky',
            'variables' => ['cislo_objednavky'],
            'quote_kind' => 'order.OrderCreated',
        ]);
    }

    public function test_projekt_zaregistruje_udalosti_a_zoznam_nahradi_povodny(): void
    {
        $this->api('PUT', '/api/v1/mailing/triggers', ['triggers' => [
            ['key' => 'order_cancelled', 'label' => 'Po storne', 'variables' => ['cislo_objednavky']],
            ['key' => 'shipping_dispatched', 'label' => 'Po expedícii', 'quote_kind' => 'order.OrderExpedition'],
        ]])->assertOk()->assertJsonCount(2, 'data');

        $this->assertSame(
            ['order_cancelled', 'shipping_dispatched'],
            MailingTrigger::where('product_id', $this->product->id)->orderBy('key')->pluck('key')->all(),
        );
    }

    public function test_token_bez_opravnenia_na_emailing_neprejde(): void
    {
        [, $token] = ServiceClient::issue($this->product, 'messages only', ['messages:write']);

        $this->withToken($token)->postJson('/api/v1/mailing/events', ['event' => 'order_created', 'email' => 'a@b.test'])
            ->assertForbidden();
    }

    public function test_udalost_zaradi_ponuku_len_kontaktu_so_suhlasom_a_raz(): void
    {
        $campaign = $this->campaign(['trigger_event' => 'order_created', 'delay_hours' => 24]);
        $this->contact('jana@firma.test');

        $event = [
            'event' => 'order_created',
            'email' => 'Jana@Firma.test',
            'ref' => ['type' => 'order', 'id' => 12, 'label' => 'Objednávka 12'],
            'occurred_at' => '2026-10-20T10:00:00+00:00',
            'variables' => ['cislo_objednavky' => '2026/12'],
        ];

        $this->api('POST', '/api/v1/mailing/events', $event)->assertStatus(202)->assertJsonPath('data.queued', 1);
        // Opakované hlásenie tej istej udalosti nič nepridá.
        $this->api('POST', '/api/v1/mailing/events', $event)->assertStatus(202)->assertJsonPath('data.queued', 0);
        // Adresa bez súhlasu ponuku nedostane.
        $this->api('POST', '/api/v1/mailing/events', [...$event, 'email' => 'cudzi@firma.test'])->assertJsonPath('data.queued', 0);

        $delivery = MailingDelivery::sole();
        $this->assertSame($campaign->id, $delivery->campaign_id);
        $this->assertSame('jana@firma.test', $delivery->email);
        $this->assertSame('order:12', $delivery->dedupe_key);
        $this->assertSame('2026-10-21 10:00:00', $delivery->due_at->utc()->toDateTimeString());
        $this->assertSame('2026/12', $delivery->variables()['cislo_objednavky']);
    }

    public function test_hromadna_kampan_odide_s_odhlasenim_a_skonci(): void
    {
        $user = User::factory()->create();
        $campaign = $this->campaign(['status' => MailingCampaign::DRAFT, 'subject' => 'Jesenná ponuka', 'body' => 'Ahoj {meno}']);
        $this->contact('a@firma.test', 'Anna');
        $this->contact('b@firma.test');
        $this->contact('odhlaseny@firma.test')->forceFill(['unsubscribed_at' => now()])->save();
        $this->contact('bounce@firma.test');
        MailSuppression::create(['product_id' => $this->product->id, 'email' => 'bounce@firma.test', 'reason' => MailSuppression::HARD_BOUNCE]);

        $this->actingAs($user)->post("/mailing/{$campaign->id}/queue")->assertSessionHas('success');

        $this->assertSame(2, MailingDelivery::count());
        $this->assertSame(2, app(MailingSender::class)->runBatch());

        $sent = $this->sentEmails();
        $this->assertCount(2, $sent);
        $this->assertSame('Jesenná ponuka', $sent[0]->getSubject());
        $this->assertSame('obchod@zastavy.test', $sent[0]->getFrom()[0]->getAddress());
        $this->assertStringContainsString('/m/u/', $sent[0]->getHeaders()->get('List-Unsubscribe')->getBodyAsString());
        $this->assertSame('List-Unsubscribe=One-Click', $sent[0]->getHeaders()->get('List-Unsubscribe-Post')->getBodyAsString());
        $this->assertStringContainsString(e(__('mailing.render.greeting', ['name' => 'Anna'])), $sent[0]->getHtmlBody());
        $this->assertSame(MailingCampaign::COMPLETED, $campaign->fresh()->status);
    }

    public function test_hodinovy_limit_zastavi_davku(): void
    {
        config(['accounts.mailing.hourly_limit' => 1]);
        $campaign = $this->campaign();
        foreach (['a', 'b'] as $name) {
            $contact = $this->contact("{$name}@firma.test");
            MailingDelivery::create(['campaign_id' => $campaign->id, 'contact_id' => $contact->id, 'email' => $contact->email]);
        }

        $this->assertSame(1, app(MailingSender::class)->runBatch());
        $this->assertSame(0, app(MailingSender::class)->runBatch());
        $this->assertSame(1, MailingDelivery::where('status', MailingDelivery::PENDING)->count());
    }

    public function test_odhlasenie_jednym_klikom_zastavi_ponuky_ale_nie_servisne_maily(): void
    {
        $campaign = $this->campaign();
        $contact = $this->contact('jana@firma.test');
        MailingDelivery::create(['campaign_id' => $campaign->id, 'contact_id' => $contact->id, 'email' => $contact->email]);
        $endpoint = WebhookEndpoint::create(['product_id' => $this->product->id, 'url' => 'https://zastavy.test/hook', 'events' => ['mailing.unsubscribed']]);

        $this->get("/m/u/{$contact->token}")->assertOk()->assertSee(__('mailing.unsubscribe.button'));
        $this->post("/m/u/{$contact->token}")->assertOk()->assertSee(__('mailing.unsubscribe.done_title'));

        $this->assertNotNull($contact->fresh()->unsubscribed_at);
        $this->assertSame(MailingDelivery::SKIPPED, MailingDelivery::sole()->status);
        $this->assertSame('mailing.unsubscribed', WebhookDelivery::where('webhook_endpoint_id', $endpoint->id)->sole()->event);

        // Potvrdenie objednávky na tú istú adresu stále odíde.
        $this->api('POST', '/api/v1/messages', [
            'to' => 'jana@firma.test', 'subject' => 'Potvrdenie', 'html' => '<p>OK</p>',
            'ref' => ['type' => 'order', 'id' => 1, 'label' => 'Objednávka 1'],
        ])->assertStatus(202);
    }

    public function test_storno_preskoci_cakajuce_maily_okrem_kampane_na_storno(): void
    {
        MailingTrigger::create(['product_id' => $this->product->id, 'key' => 'order_cancelled', 'label' => 'Storno']);
        $created = $this->campaign(['trigger_event' => 'order_created']);
        $cancelled = $this->campaign(['trigger_event' => 'order_cancelled']);
        $contact = $this->contact('jana@firma.test');

        foreach ([$created, $cancelled] as $campaign) {
            MailingDelivery::create(['campaign_id' => $campaign->id, 'contact_id' => $contact->id, 'email' => $contact->email,
                'ref_type' => 'order', 'ref_id' => '12', 'dedupe_key' => 'order:12', 'due_at' => now()->addDay()]);
        }

        $this->api('POST', '/api/v1/mailing/events/cancel', ['ref' => ['type' => 'order', 'id' => 12], 'except' => ['order_cancelled']])
            ->assertOk()->assertJsonPath('data.skipped', 1);

        $this->assertSame(MailingDelivery::SKIPPED, MailingDelivery::where('campaign_id', $created->id)->value('status'));
        $this->assertSame(MailingDelivery::PENDING, MailingDelivery::where('campaign_id', $cancelled->id)->value('status'));
    }

    public function test_odpoved_vo_vlakne_cituje_systemovy_mail_a_ide_do_konverzacie(): void
    {
        $campaign = $this->campaign([
            'trigger_event' => 'order_created', 'delay_hours' => 0, 'reply_in_thread' => true,
            'subject' => null, 'body' => 'Ďakujeme za objednávku {cislo_objednavky}.',
        ]);
        $endpoint = WebhookEndpoint::create(['product_id' => $this->product->id, 'url' => 'https://zastavy.test/hook', 'events' => ['mailing.delivery.sent']]);

        // Systémový mail objednávky poslal projekt cez Account.
        $this->api('POST', '/api/v1/messages', [
            'to' => 'jana@firma.test', 'subject' => 'Objednávka č. 12', 'html' => '<html><body><p>Potvrdzujeme objednávku</p></body></html>',
            'ref' => ['type' => 'order', 'id' => 12, 'label' => 'Objednávka 12'], 'kind' => 'order.OrderCreated',
        ])->assertStatus(202);

        // Bez súhlasu s ponukami – servisná odpoveď ide aj tak.
        $this->api('POST', '/api/v1/mailing/events', [
            'event' => 'order_created', 'email' => 'jana@firma.test',
            'ref' => ['type' => 'order', 'id' => 12, 'label' => 'Objednávka 12'],
            'variables' => ['cislo_objednavky' => '2026/12'],
        ])->assertJsonPath('data.queued', 1);

        $this->assertSame(1, app(MailingSender::class)->runBatch());

        $reply = ConversationMessage::where('kind', 'mailing.reply')->sole();
        $original = ConversationMessage::where('kind', 'order.OrderCreated')->sole();
        $this->assertSame($original->conversation_id, $reply->conversation_id);
        $this->assertSame('Re: Objednávka č. 12', $reply->subject);
        $this->assertSame($original->message_id, $reply->in_reply_to);
        $this->assertStringContainsString('Ďakujeme za objednávku 2026/12.', $reply->body_html);
        $this->assertStringContainsString('Potvrdzujeme objednávku', $reply->body_html);
        $this->assertSame(1, Conversation::count());

        $delivery = MailingDelivery::sole();
        $this->assertSame(MailingDelivery::SENT, $delivery->status);
        $this->assertSame($reply->id, $delivery->conversation_message_id);

        $webhook = WebhookDelivery::where('webhook_endpoint_id', $endpoint->id)->sole();
        $this->assertSame('order', $webhook->payload['data']['delivery']['ref']['type']);
        $this->assertSame('12', $webhook->payload['data']['delivery']['ref']['id']);
    }

    public function test_klik_sa_zmeria_a_presmeruje_na_ulozeny_ciel(): void
    {
        $campaign = $this->campaign(['track_clicks' => true, 'body' => '<p><a href="https://zastavy.test/ponuka">Ponuka</a></p>']);
        $contact = $this->contact('jana@firma.test');
        MailingDelivery::create(['campaign_id' => $campaign->id, 'contact_id' => $contact->id, 'email' => $contact->email]);

        app(MailingSender::class)->runBatch();

        $link = MailingLink::where('url', 'https://zastavy.test/ponuka')->sole();
        $this->assertStringContainsString('/m/c/'.$link->token, $this->sentEmails()[0]->getHtmlBody());

        $this->get("/m/c/{$link->token}")->assertRedirect('https://zastavy.test/ponuka');

        $this->assertSame(1, $link->fresh()->click_count);
        $this->assertNotNull(MailingDelivery::sole()->clicked_at);
    }

    public function test_trvaly_bounce_blokuje_adresu_docasny_az_po_opakovani(): void
    {
        $sender = app(MailingSender::class);
        $this->contact('plna@firma.test');

        $this->assertFalse($sender->recordBounce($this->product, 'plna@firma.test', BounceClassifier::SOFT, '452 mailbox full'));
        $this->assertFalse($sender->recordBounce($this->product, 'plna@firma.test', BounceClassifier::SOFT, '452 mailbox full'));
        $this->assertTrue($sender->recordBounce($this->product, 'plna@firma.test', BounceClassifier::SOFT, '452 mailbox full'));
        $this->assertNotNull(MailingContact::where('email', 'plna@firma.test')->value('bounced_at'));
        $this->assertFalse(MailSuppression::blocks($this->product, 'plna@firma.test'));

        $this->assertTrue($sender->recordBounce($this->product, 'zla@firma.test', BounceClassifier::HARD, '550 5.1.1 no such user'));
        $this->assertTrue(MailSuppression::blocks($this->product, 'zla@firma.test'));
    }

    public function test_layout_dosadi_znacky_a_sekcie(): void
    {
        $html = \App\Services\Mailing\MailingRenderer::template(
            '<h1>{{heading}}</h1>{{{content}}}{{#unsubscribe_url}}<a href="{{unsubscribe_url}}">x</a>{{/unsubscribe_url}}{{^coupon_code}}bez kupónu{{/coupon_code}}',
            ['heading' => 'A & B', 'content' => '<p>text</p>', 'unsubscribe_url' => null, 'coupon_code' => ''],
        );

        $this->assertSame('<h1>A &amp; B</h1><p>text</p>bez kupónu', $html);
    }

    public function test_stranky_back_office_sa_otvoria_a_nahlad_sa_vykresli(): void
    {
        $user = User::factory()->create();
        $campaign = $this->campaign(['status' => MailingCampaign::DRAFT]);
        $this->contact('jana@firma.test');

        foreach (['/mailing', '/mailing/create?product=zastavy', "/mailing/{$campaign->id}/edit", "/mailing/{$campaign->id}", '/mailing/templates', '/mailing/contacts'] as $url) {
            $this->actingAs($user)->get($url)->assertOk();
        }

        $this->actingAs($user)->postJson('/mailing/preview', [
            'product_key' => 'zastavy', 'name' => 'X', 'subject' => 'Ponuka', 'heading' => 'Jeseň', 'body' => 'Objednávka {cislo_objednavky}',
        ])->assertOk()->assertJsonPath('html', fn ($html) => str_contains($html, 'Jeseň') && str_contains($html, '{cislo_objednavky}'));
    }

    public function test_import_z_projektu_zachova_tokeny_a_rozdeli_bounce(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'mailing');
        file_put_contents($file, json_encode([
            'layouts' => [['key' => 'default', 'name' => 'Zástavy', 'html' => '<h1>{{heading}}</h1>{{{content}}}']],
            'templates' => [['name' => 'Jeseň', 'layout' => 'default', 'subject' => 'S', 'heading' => 'H', 'body' => 'B']],
            'contacts' => [
                ['email' => 'a@firma.test', 'token' => str_repeat('a', 64), 'permission_note' => 'web'],
                ['email' => 'zla@firma.test', 'token' => str_repeat('b', 64), 'bounced_at' => '2026-10-01T10:00:00+02:00', 'bounce_type' => 'hard'],
                ['email' => 'plna@firma.test', 'token' => str_repeat('c', 64), 'bounced_at' => '2026-10-01T10:00:00+02:00', 'bounce_type' => 'soft_limit'],
            ],
            'campaigns' => [['id' => 7, 'name' => 'Po objednávke', 'body' => 'Ďakujeme', 'status' => 'queued', 'layout' => 'default',
                'trigger_event' => 'order_created', 'delay_hours' => 24, 'reply_to_order' => true, 'scheduled_at' => '2026-10-01T10:00:00+02:00']],
            'deliveries' => [['id' => 3, 'campaign_id' => 7, 'email' => 'a@firma.test', 'reply' => true, 'status' => 'sent',
                'ref_type' => 'order', 'ref_id' => 'uuid-1', 'dedupe_key' => 'order:uuid-1', 'open_token' => str_repeat('d', 64)]],
            'links' => [['delivery_id' => 3, 'position' => 1, 'token' => str_repeat('e', 64), 'url' => 'https://zastavy.test', 'click_count' => 2]],
        ]));

        $this->artisan('mailing:import', ['product' => 'zastavy', 'file' => $file])->assertSuccessful();
        // Druhý import by zdvojil kampane a štatistiky.
        $this->artisan('mailing:import', ['product' => 'zastavy', 'file' => $file])->assertFailed();

        $campaign = MailingCampaign::sole();
        $this->assertTrue($campaign->reply_in_thread);
        $this->assertNotNull($campaign->layout_id);
        $this->assertTrue($campaign->scheduled_at->eq(Carbon::parse('2026-10-01T08:00:00Z')));
        $this->assertSame(str_repeat('a', 64), MailingContact::where('email', 'a@firma.test')->value('token'));
        $this->assertTrue(MailSuppression::blocks($this->product, 'zla@firma.test'));
        $this->assertNull(MailingContact::where('email', 'zla@firma.test')->value('bounced_at'));
        $this->assertNotNull(MailingContact::where('email', 'plna@firma.test')->value('bounced_at'));
        $this->assertNull(MailingDelivery::sole()->contact_id);
        $this->assertSame(2, MailingLink::where('token', str_repeat('e', 64))->value('click_count'));
        // Prednastavené šablóny z admina zastáv pribudnú k exportovaným.
        $this->assertSame(1 + count(ImportMailing::PRESETS['zastavy']), MailingTemplate::count());

        unlink($file);
    }

    /* ---------------------------------------------------------------
     | Pomocné
     |---------------------------------------------------------------*/

    /** @param array<string, mixed> $attributes */
    private function campaign(array $attributes = []): MailingCampaign
    {
        return MailingCampaign::create([
            'product_id' => $this->product->id,
            'name' => 'Kampaň',
            'subject' => 'Ponuka',
            'heading' => 'Nadpis',
            'body' => 'Text ponuky',
            'status' => MailingCampaign::QUEUED,
            'scheduled_at' => now()->subMinute(),
            ...$attributes,
        ]);
    }

    private function contact(string $email, ?string $name = null): MailingContact
    {
        return MailingContact::create([
            'product_id' => $this->product->id,
            'email' => $email,
            'name' => $name,
            'permission_note' => 'registrácia na webe',
            'permission_at' => now(),
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
