<?php

namespace Tests\Feature\Mailing;

use App\Models\MailingContact;
use App\Models\MailingLabel;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ručné štítky kontaktov: import pod štítky, predvolený súhlas,
 * hromadné akcie a správa štítkov (premenovanie, zmazanie).
 */
class MailingLabelsTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->product = Product::factory()->create(['key' => 'anonymizer', 'name' => 'Anonymizér']);
        $this->user = User::factory()->create(['name' => 'Gabo']);
    }

    public function test_import_csv_zaradi_kontakty_pod_stitky_a_predvolene_udeli_suhlas(): void
    {
        $this->contact('stary@firma.test', consent: false);
        $this->contact('odhlaseny@firma.test')->forceFill(['unsubscribed_at' => now()])->save();

        $csv = "email;meno\njana@firma.test;Jana Nová\nPeter Malý,PETER@firma.test\n\"Eva\" <eva@firma.test>\nstary@firma.test\nodhlaseny@firma.test\nzle@\njana@firma.test";

        $this->actingAs($this->user)->post('/mailing/contacts', [
            'product_key' => 'anonymizer',
            'emails' => $csv,
            'labels' => ['Veľtrh 2026', 'Samospráva'],
        ])->assertSessionHas('success', __('mailing.contacts.imported', ['added' => 3, 'updated' => 2, 'unchanged' => 0, 'skipped' => 1]));

        $jana = MailingContact::where('email', 'jana@firma.test')->sole();
        $this->assertSame('Jana Nová', $jana->name);
        $this->assertTrue($jana->hasConsent());
        $this->assertSame(__('mailing.contacts.permission_default', ['user' => 'Gabo']), $jana->permission_note);
        $this->assertSame(['samosprava', 'veltrh-2026'], $jana->manual_tags);
        $this->assertSame('Peter Malý', MailingContact::where('email', 'peter@firma.test')->sole()->name);
        $this->assertSame('Eva', MailingContact::where('email', 'eva@firma.test')->sole()->name);

        // Existujúci kontakt bez súhlasu ho dostane, odhlásený ostane odhlásený.
        $this->assertTrue(MailingContact::where('email', 'stary@firma.test')->sole()->hasConsent());
        $this->assertNotNull(MailingContact::where('email', 'odhlaseny@firma.test')->sole()->unsubscribed_at);

        $this->assertSame(['Samospráva', 'Veľtrh 2026'], MailingLabel::orderBy('name')->pluck('name')->all());
    }

    public function test_import_bez_suhlasu_prida_kontakty_len_pre_oznamenia(): void
    {
        $this->actingAs($this->user)->post('/mailing/contacts', [
            'product_key' => 'anonymizer',
            'emails' => 'jana@firma.test',
            'consent' => false,
        ])->assertSessionHasNoErrors();

        $this->assertFalse(MailingContact::where('email', 'jana@firma.test')->sole()->hasConsent());
    }

    public function test_existujuci_stitok_sa_najde_podla_nazvu_aj_po_premenovani(): void
    {
        $label = MailingLabel::create(['product_id' => $this->product->id, 'key' => 'samosprava', 'name' => 'Samospráva']);

        $this->actingAs($this->user)->patch("/mailing/labels/{$label->id}", ['name' => 'Obce a mestá', 'color' => 'emerald'])
            ->assertSessionHas('success');
        $this->assertSame('samosprava', $label->fresh()->key);

        $this->actingAs($this->user)->post('/mailing/contacts', [
            'product_key' => 'anonymizer', 'emails' => 'jana@firma.test', 'labels' => ['obce a mestá'],
        ]);

        $this->assertSame(['samosprava'], MailingContact::where('email', 'jana@firma.test')->sole()->manual_tags);
        $this->assertSame(1, MailingLabel::count());
    }

    public function test_hromadne_pridanie_a_odobratie_stitku_vybranym_kontaktom(): void
    {
        $a = $this->contact('a@firma.test');
        $b = $this->contact('b@firma.test', ['stary']);
        $c = $this->contact('c@firma.test');

        $this->actingAs($this->user)->post('/mailing/contacts/bulk', [
            'action' => 'add_labels', 'ids' => [$a->id, $b->id], 'labels' => ['Nový klient'],
        ])->assertSessionHas('success', __('mailing.contacts.bulk_done.add_labels', ['count' => 2]));

        $this->assertSame(['novy-klient'], $a->fresh()->manual_tags);
        $this->assertSame(['novy-klient', 'stary'], $b->fresh()->manual_tags);
        $this->assertNull($c->fresh()->manual_tags);

        $this->actingAs($this->user)->post('/mailing/contacts/bulk', [
            'action' => 'remove_labels', 'ids' => [$a->id, $b->id], 'labels' => ['Nový klient'],
        ]);

        $this->assertNull($a->fresh()->manual_tags);
        $this->assertSame(['stary'], $b->fresh()->manual_tags);
    }

    public function test_hromadna_akcia_na_vsetky_kontakty_podla_filtra(): void
    {
        foreach (range(1, 60) as $i) {
            $this->contact("obec{$i}@mesto.test", consent: false);
        }
        $other = $this->contact('jana@firma.test', consent: false);

        $this->actingAs($this->user)->post('/mailing/contacts/bulk', [
            'action' => 'consent', 'all' => true, 'filters' => ['product' => 'anonymizer', 'search' => 'mesto.test'],
        ])->assertSessionHas('success', __('mailing.contacts.bulk_done.consent', ['count' => 60]));

        $this->assertSame(60, MailingContact::where('email', 'like', '%mesto.test')->consented()->count());
        $this->assertFalse($other->fresh()->hasConsent());

        $this->actingAs($this->user)->post('/mailing/contacts/bulk', [
            'action' => 'delete', 'all' => true, 'filters' => ['search' => 'mesto.test'],
        ]);

        $this->assertSame(1, MailingContact::withTrashed()->count());
    }

    public function test_hromadne_odhlasenie(): void
    {
        $a = $this->contact('a@firma.test');

        $this->actingAs($this->user)->post('/mailing/contacts/bulk', ['action' => 'unsubscribe', 'ids' => [$a->id]])
            ->assertSessionHas('success');

        $this->assertNotNull($a->fresh()->unsubscribed_at);
    }

    public function test_zmazanie_stitku_ho_odoberie_kontaktom(): void
    {
        $label = MailingLabel::create(['product_id' => $this->product->id, 'key' => 'samosprava', 'name' => 'Samospráva']);
        $a = $this->contact('a@firma.test', ['samosprava', 'iny']);

        $this->actingAs($this->user)->delete("/mailing/labels/{$label->id}")->assertSessionHas('success');

        $this->assertSame(['iny'], $a->fresh()->manual_tags);
        $this->assertModelMissing($label);
    }

    public function test_vytvorenie_stitku_a_duplicitny_nazov(): void
    {
        $this->actingAs($this->user)->post('/mailing/labels', ['product_key' => 'anonymizer', 'name' => 'Samospráva', 'color' => 'rose'])
            ->assertSessionHas('success');
        $this->actingAs($this->user)->post('/mailing/labels', ['product_key' => 'anonymizer', 'name' => 'samospráva', 'color' => 'sky'])
            ->assertSessionHasErrors('name');

        $this->assertSame([['samosprava', 'Samospráva', 'rose']], MailingLabel::get()->map(fn ($l) => [$l->key, $l->name, $l->color])->all());
    }

    public function test_zoznam_kontaktov_ukaze_nazvy_stitkov_a_pocty(): void
    {
        MailingLabel::create(['product_id' => $this->product->id, 'key' => 'samosprava', 'name' => 'Samospráva', 'color' => 'emerald']);
        $this->contact('a@firma.test', ['samosprava']);
        $this->contact('b@firma.test', ['samosprava']);

        $this->actingAs($this->user)->get('/mailing/contacts?product=anonymizer')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('contacts.data.0.manual_tags.0.name', 'Samospráva')
                ->where('contacts.data.0.manual_tags.0.color', 'emerald')
                ->where('labels.0.count', 2)
                ->where('tags', fn ($tags) => collect($tags)->firstWhere('key', 'samosprava')['label'] === 'Samospráva'));
    }

    /** @param list<string> $labels */
    private function contact(string $email, array $labels = [], bool $consent = true): MailingContact
    {
        return MailingContact::create([
            'product_id' => $this->product->id,
            'email' => $email,
            'manual_tags' => $labels ?: null,
            'permission_note' => $consent ? 'web' : null,
            'permission_at' => $consent ? now() : null,
        ]);
    }
}
