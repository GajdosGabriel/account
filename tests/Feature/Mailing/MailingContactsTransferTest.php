<?php

namespace Tests\Feature\Mailing;

use App\Models\MailingContact;
use App\Models\MailingLabel;
use App\Models\MailSuppression;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Prenos kontaktov medzi projektmi: kópia do iného projektu (hromadná
 * akcia) a export do CSV, ktorý sa dá načítať naspäť importom.
 */
class MailingContactsTransferTest extends TestCase
{
    use RefreshDatabase;

    private Product $source;

    private Product $target;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->source = Product::factory()->create(['key' => 'anonymizer', 'name' => 'Anonymizér']);
        $this->target = Product::factory()->create(['key' => 'event', 'name' => 'Event']);
        $this->user = User::factory()->create(['name' => 'Gabo']);
        MailingLabel::create(['product_id' => $this->source->id, 'key' => 'samosprava', 'name' => 'Samospráva']);
    }

    public function test_kopia_do_projektu_prenesie_stitky_a_suhlas_a_vynecha_odhlasene(): void
    {
        $jana = $this->contact($this->source, 'jana@obec.test', ['samosprava'], name: 'Jana');
        $service = $this->contact($this->source, 'servis@obec.test', ['samosprava'], consent: false);
        $off = $this->contact($this->source, 'off@obec.test', ['samosprava']);
        $off->forceFill(['unsubscribed_at' => now()])->save();
        $blocked = $this->contact($this->source, 'blok@obec.test', ['samosprava']);
        MailSuppression::create(['product_id' => $this->source->id, 'email' => 'blok@obec.test', 'reason' => MailSuppression::HARD_BOUNCE]);

        // V cieli už existuje bez súhlasu a s vlastným štítkom.
        $this->contact($this->target, 'jana@obec.test', ['vip'], consent: false);
        MailingLabel::create(['product_id' => $this->target->id, 'key' => 'obce', 'name' => 'samospráva']);

        $this->actingAs($this->user)->post('/mailing/contacts/bulk', [
            'action' => 'copy', 'all' => true, 'filters' => ['product' => 'anonymizer', 'tag' => 'samosprava'],
            'product_key' => 'event', 'labels' => ['Pozvánka 2027'],
        ])->assertSessionHas('success', __('mailing.contacts.bulk_done.copy', ['product' => 'Event', 'count' => 2, 'existing' => 1, 'skipped' => 2]));

        $copy = MailingContact::where('product_id', $this->target->id)->where('email', 'jana@obec.test')->sole();
        // Štítok sa v cieli nájde podľa názvu (bez ohľadu na veľkosť písmen).
        $this->assertSame(['obce', 'pozvanka-2027', 'vip'], $copy->manual_tags);
        $this->assertTrue($copy->hasConsent());
        $this->assertStringContainsString('Anonymizér', $copy->permission_note);

        // Bez súhlasu v zdroji ostane aj v cieli len pre oznámenia.
        $serviceCopy = MailingContact::where('product_id', $this->target->id)->where('email', 'servis@obec.test')->sole();
        $this->assertFalse($serviceCopy->hasConsent());

        $this->assertFalse(MailingContact::where('product_id', $this->target->id)->whereIn('email', ['off@obec.test', 'blok@obec.test'])->exists());
        $this->assertSame(4, MailingContact::where('product_id', $this->source->id)->count());
        $this->assertSame('Jana', $jana->fresh()->name);
        $this->assertNotNull($service->fresh());
        $this->assertNotNull($blocked->fresh());
    }

    public function test_kopia_bez_stitkov_a_bez_suhlasu(): void
    {
        $jana = $this->contact($this->source, 'jana@obec.test', ['samosprava']);

        $this->actingAs($this->user)->post('/mailing/contacts/bulk', [
            'action' => 'copy', 'ids' => [$jana->id], 'product_key' => 'event', 'with_labels' => false, 'consent' => false,
        ])->assertSessionHas('success');

        $copy = MailingContact::where('product_id', $this->target->id)->sole();
        $this->assertNull($copy->manual_tags);
        $this->assertFalse($copy->hasConsent());
    }

    public function test_kopia_vyzaduje_cielovy_projekt(): void
    {
        $jana = $this->contact($this->source, 'jana@obec.test');

        $this->actingAs($this->user)->post('/mailing/contacts/bulk', ['action' => 'copy', 'ids' => [$jana->id]])
            ->assertSessionHasErrors('product_key');
    }

    public function test_export_a_import_do_ineho_projektu(): void
    {
        $this->contact($this->source, 'jana@obec.test', ['samosprava'], name: 'Jana Nová');
        $this->contact($this->source, 'peter@obec.test', name: '=HYPERLINK("x")');
        $this->contact($this->source, 'iny@firma.test');

        $response = $this->actingAs($this->user)->get('/mailing/contacts/export?product=anonymizer&search=obec.test')->assertOk();
        $csv = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBFemail;meno;stitky;", $csv);
        $this->assertStringContainsString('jana@obec.test;"Jana Nová";Samospráva;;anonymizer;web;', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString('iny@firma.test', $csv);

        $this->actingAs($this->user)->post('/mailing/contacts', [
            'product_key' => 'event', 'emails' => $csv, 'labels' => ['Import'],
        ])->assertSessionHas('success', __('mailing.contacts.imported', ['added' => 2, 'updated' => 0, 'unchanged' => 0, 'skipped' => 0]));

        $jana = MailingContact::where('product_id', $this->target->id)->where('email', 'jana@obec.test')->sole();
        $this->assertSame('Jana Nová', $jana->name);
        $this->assertSame(['import', 'samosprava'], $jana->manual_tags);
        $this->assertSame(['import'], MailingContact::where('product_id', $this->target->id)->where('email', 'peter@obec.test')->sole()->manual_tags);
        $this->assertSame('=HYPERLINK("x")', MailingContact::where('product_id', $this->target->id)->where('email', 'peter@obec.test')->sole()->name);
    }

    public function test_export_vybranych_kontaktov(): void
    {
        $a = $this->contact($this->source, 'a@obec.test');
        $this->contact($this->source, 'b@obec.test');

        $csv = $this->actingAs($this->user)->get('/mailing/contacts/export?ids[]='.$a->id)->assertOk()->streamedContent();

        $this->assertStringContainsString('a@obec.test', $csv);
        $this->assertStringNotContainsString('b@obec.test', $csv);
    }

    public function test_import_csv_s_hlavickou_v_inom_poradi_stlpcov(): void
    {
        $csv = "Meno,Štítky,E-mail\n\"Nová, Jana\",\"Samospráva, VIP\",jana@obec.test\n,,peter@obec.test";

        $this->actingAs($this->user)->post('/mailing/contacts', ['product_key' => 'event', 'emails' => $csv])
            ->assertSessionHasNoErrors();

        $jana = MailingContact::where('email', 'jana@obec.test')->sole();
        $this->assertSame('Nová, Jana', $jana->name);
        $this->assertSame(['samosprava', 'vip'], $jana->manual_tags);
        $this->assertNull(MailingContact::where('email', 'peter@obec.test')->sole()->name);
    }

    /** @param list<string> $labels */
    private function contact(Product $product, string $email, array $labels = [], bool $consent = true, ?string $name = null): MailingContact
    {
        return MailingContact::create([
            'product_id' => $product->id,
            'email' => $email,
            'name' => $name,
            'manual_tags' => $labels ?: null,
            'permission_note' => $consent ? 'web' : null,
            'permission_at' => $consent ? now() : null,
        ]);
    }
}
