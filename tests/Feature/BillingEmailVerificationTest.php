<?php

namespace Tests\Feature;

use App\Enums\InvoiceType;
use App\Mail\BillingEmailVerificationMail;
use App\Mail\InvoiceMail;
use App\Models\Invoice;
use App\Models\InvoiceNumberSeries;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ServiceClient;
use App\Models\User;
use App\Services\Billing\BillingEmailVerifier;
use App\Services\Invoicing\InvoiceMailer;
use App\Services\Invoicing\InvoiceService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Overenie fakturačného e-mailu.
 *
 * Zmysel je jediný: faktúra nesie IČO, adresu aj sumy, takže preklep
 * v adrese ju pošle cudziemu človeku a odoslanie sa pritom tvári úspešne.
 */
class BillingEmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    public function test_firma_bez_emailu_je_neoverena_a_nic_sa_neposiela(): void
    {
        $organization = Organization::factory()->create(['email' => null, 'billing_email' => null]);

        $this->assertFalse($organization->hasVerifiedBillingEmail());
        $this->assertFalse(app(BillingEmailVerifier::class)->sendIfNeeded($organization));

        Mail::assertNothingOutgoing();
    }

    public function test_pri_zadanom_emaile_pride_ziadost_o_potvrdenie(): void
    {
        $organization = Organization::factory()->create(['billing_email' => 'faktury@firma.sk']);

        $this->assertTrue(app(BillingEmailVerifier::class)->sendIfNeeded($organization));

        // E-mail musí ísť na adresu, ktorá sa overuje – nie na inú.
        // Práve preto preklep v adrese overenie nikdy neprejde.
        Mail::assertQueued(
            BillingEmailVerificationMail::class,
            fn (BillingEmailVerificationMail $mail) => $mail->hasTo('faktury@firma.sk'),
        );

        $this->assertNotNull($organization->fresh()->billing_email_verification_sent_at);
    }

    public function test_kliknutie_na_odkaz_adresu_overi(): void
    {
        $organization = Organization::factory()->create(['billing_email' => 'faktury@firma.sk']);

        $this->get($this->linkFor($organization, 'faktury@firma.sk'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Public/BillingEmailVerified')
                ->where('confirmed', true));

        $this->assertTrue($organization->fresh()->hasVerifiedBillingEmail());
    }

    /** Adresa v odkaze nesmie stačiť – musí sedieť s tou, ktorú firma má teraz. */
    public function test_odkaz_na_stary_email_uz_neoveri(): void
    {
        $organization = Organization::factory()->create(['billing_email' => 'stara@firma.sk']);

        $link = $this->linkFor($organization, 'stara@firma.sk');

        $organization->update(['billing_email' => 'nova@firma.sk']);

        $this->get($link)->assertOk()->assertInertia(fn ($page) => $page->where('confirmed', false));

        $this->assertFalse($organization->fresh()->hasVerifiedBillingEmail());
    }

    public function test_bez_platneho_podpisu_odkaz_nefunguje(): void
    {
        $organization = Organization::factory()->create(['billing_email' => 'faktury@firma.sk']);

        $this->get("/organizations/{$organization->uuid}/billing-email/verify?hash=".sha1('faktury@firma.sk'))
            ->assertForbidden();
    }

    /**
     * Overenie visí na adrese, nie na časovej pečiatke. Po prepísaní e-mailu
     * preto prestane platiť samo – netreba to nikde rušiť ručne a funguje to
     * aj vtedy, keď stĺpec zmení iná cesta než formulár.
     */
    public function test_zmena_adresy_zrusi_overenie(): void
    {
        $organization = Organization::factory()->create(['billing_email' => 'faktury@firma.sk']);
        $organization->markBillingEmailVerified('faktury@firma.sk');

        $this->assertTrue($organization->hasVerifiedBillingEmail());

        $organization->update(['billing_email' => 'ine@firma.sk']);

        $this->assertFalse($organization->fresh()->hasVerifiedBillingEmail());
    }

    public function test_velkost_pismen_v_adrese_overenie_nezrusi(): void
    {
        $organization = Organization::factory()->create(['billing_email' => 'Faktury@Firma.sk']);
        $organization->markBillingEmailVerified('faktury@firma.sk');

        $this->assertTrue($organization->fresh()->hasVerifiedBillingEmail());
    }

    public function test_ziadost_sa_neposiela_znova_hned_po_sebe(): void
    {
        $organization = Organization::factory()->create(['billing_email' => 'faktury@firma.sk']);
        $verifier = app(BillingEmailVerifier::class);

        $this->assertTrue($verifier->sendIfNeeded($organization));
        $this->assertFalse($verifier->sendIfNeeded($organization->fresh()), 'Druhé uloženie nesmie poslať e-mail znova.');

        // Ručné „poslať znova“ čakaciu lehotu preskočí.
        $this->assertTrue($verifier->sendIfNeeded($organization->fresh(), force: true));
    }

    public function test_overenej_firme_sa_ziadost_neposiela(): void
    {
        $organization = Organization::factory()->create(['billing_email' => 'faktury@firma.sk']);
        $organization->markBillingEmailVerified('faktury@firma.sk');

        $this->assertFalse(app(BillingEmailVerifier::class)->sendIfNeeded($organization->fresh(), force: true));

        Mail::assertNothingOutgoing();
    }

    public function test_zalozenie_firmy_z_projektu_ziadost_odosle(): void
    {
        $product = Product::factory()->create();
        [, $token] = ServiceClient::issue($product, 'test');

        $this->withToken($token)->postJson('/api/v1/organizations', [
            'name' => 'Firma s e-mailom',
            'billing_email' => 'faktury@firma.sk',
        ])->assertCreated()
            ->assertJsonPath('data.contact.billing_email_verified', false)
            ->assertJsonPath('data.contact.billing_email_effective', 'faktury@firma.sk');

        Mail::assertQueued(BillingEmailVerificationMail::class);
    }

    public function test_operator_vie_poslat_overovaci_email_znova(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['billing_email' => 'faktury@firma.sk']);

        $this->actingAs($user)
            ->post("/organizations/{$organization->uuid}/billing-email/resend")
            ->assertRedirect();

        Mail::assertQueued(BillingEmailVerificationMail::class);
    }

    /**
     * Kvôli tomuto to celé je: doklad s IČO, adresou a sumami nemá odísť
     * na adresu, o ktorej nikto nepotvrdil, že patrí zákazníkovi.
     */
    public function test_pri_zapnutej_poziadavke_doklad_na_neoverenu_adresu_neodide(): void
    {
        config()->set('invoicing.require_verified_billing_email', true);

        $invoice = $this->issuedInvoiceFor(
            Organization::factory()->create(['billing_email' => 'preklep@frima.sk'])
        );

        $this->expectException(DomainException::class);

        app(InvoiceMailer::class)->send($invoice);
    }

    public function test_na_overenu_adresu_doklad_odide(): void
    {
        config()->set('invoicing.require_verified_billing_email', true);

        $organization = Organization::factory()->create(['billing_email' => 'faktury@firma.sk']);
        $organization->markBillingEmailVerified('faktury@firma.sk');

        $invoice = $this->issuedInvoiceFor($organization);

        app(InvoiceMailer::class)->send($invoice);

        Mail::assertQueued(InvoiceMail::class);
    }

    /**
     * Predvolene sa neblokuje – zapnutie funkcie nesmie zastaviť fakturáciu
     * firmám, ktoré vznikli pred jej zavedením. Do histórie dokladu sa to
     * ale zapíše, aby sa dalo dohľadať, kam doklad naozaj odišiel.
     */
    public function test_predvolene_doklad_odide_aj_na_neoverenu_adresu_ale_zapise_sa_to(): void
    {
        $organization = Organization::factory()->create(['billing_email' => 'neovereny@firma.sk']);
        $invoice = $this->issuedInvoiceFor($organization);

        app(InvoiceMailer::class)->send($invoice);

        $event = $invoice->fresh()->events()->where('event', 'sent')->latest('id')->first();

        $this->assertNotNull($event);
        $this->assertFalse($event->meta['recipient_verified']);
        $this->assertStringContainsString('nie je potvrdená', $event->description);
    }

    /** Doklad sa skladá cez službu, rovnako ako v InvoicingTest – faktúra bez položiek a čísla by neprešla. */
    protected function issuedInvoiceFor(Organization $organization): Invoice
    {
        InvoiceNumberSeries::firstOrCreate(
            ['key' => 'faktura'],
            [
                'name' => 'Odoslané faktúry',
                'document_type' => InvoiceType::Invoice,
                'pattern' => '{YYYY}{SEQ}',
                'sequence_length' => 4,
                'reset_period' => 'year',
                'is_default' => true,
            ],
        );

        $service = app(InvoiceService::class);

        $invoice = $service->draft($organization);

        $service->addItem($invoice, [
            'description' => 'Predplatné',
            'quantity' => 1,
            'unit' => 'mesiac',
            'unit_price' => 290_000,
            'vat_rate' => 23,
        ]);

        return $service->issue($invoice->refresh());
    }

    protected function linkFor(Organization $organization, string $email): string
    {
        return URL::temporarySignedRoute(
            'organizations.billing-email.verify',
            now()->addDays(7),
            ['organization' => $organization->uuid, 'hash' => sha1($email)],
        );
    }
}
