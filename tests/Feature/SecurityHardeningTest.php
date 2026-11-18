<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Jobs\DeliverWebhook;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ServiceClient;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Rules\PublicWebhookUrl;
use App\Services\Billing\SubscriptionManager;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    /* ---------------- Stripe ---------------- */

    public function test_stripe_bez_podpisu_odmietne_ked_je_kluc_nastaveny(): void
    {
        config(['services.stripe.webhook_secret' => 'whsec_test']);

        $this->postJson('/api/webhooks/stripe', ['type' => 'invoice.payment_failed'])->assertStatus(400);
    }

    public function test_stripe_s_platnym_podpisom_prejde_a_opakovanu_udalost_nespracuje_znova(): void
    {
        config(['services.stripe.webhook_secret' => 'whsec_test']);
        [$subscription] = $this->subscriptions();

        $payload = ['id' => 'evt_1', 'type' => 'invoice.payment_failed', 'data' => ['object' => ['subscription' => 'sub_A', 'customer' => 'cus_X']]];

        $this->signedStripe($payload)->assertOk()->assertJsonMissing(['duplicate' => true]);
        $this->assertSame(SubscriptionStatus::PastDue, $subscription->fresh()->status);

        // Medzitým sa predplatné obnovilo; oneskorená kópia tej istej udalosti ho nesmie zhodiť.
        app(SubscriptionManager::class)->activate($subscription->fresh());
        $this->signedStripe($payload)->assertOk()->assertJson(['duplicate' => true]);
        $this->assertSame(SubscriptionStatus::Active, $subscription->fresh()->status);
    }

    public function test_stripe_zrusenie_jedneho_predplatneho_nezasiahne_ine_toho_isteho_zakaznika(): void
    {
        config(['services.stripe.webhook_secret' => 'whsec_test']);
        [$first, $second] = $this->subscriptions();

        $this->signedStripe([
            'id' => 'evt_2', 'type' => 'customer.subscription.deleted',
            'data' => ['object' => ['object' => 'subscription', 'id' => 'sub_B', 'customer' => 'cus_X']],
        ])->assertOk();

        $this->assertSame(SubscriptionStatus::Cancelled, $second->fresh()->status);
        $this->assertNotSame(SubscriptionStatus::Cancelled, $first->fresh()->status);
    }

    public function test_stripe_podla_zakaznika_neparuje_ked_je_nejednoznacny(): void
    {
        config(['services.stripe.webhook_secret' => 'whsec_test']);
        $this->subscriptions();

        $this->signedStripe([
            'id' => 'evt_3', 'type' => 'invoice.payment_failed',
            'data' => ['object' => ['object' => 'invoice', 'id' => 'in_1', 'customer' => 'cus_X']],
        ])->assertStatus(202);
    }

    /* ---------------- API limit ---------------- */

    public function test_api_ma_limit_poziadaviek_na_token(): void
    {
        config(['accounts.api.rate_limit' => 2]);
        [, $token] = ServiceClient::issue(Product::factory()->create(), 'test');

        $call = fn () => $this->withToken($token)->getJson('/api/v1/organizations');

        $call()->assertOk();
        $call()->assertOk();
        $call()->assertStatus(429);
    }

    public function test_token_projektu_v_kosi_dostane_403_a_nie_chybu_servera(): void
    {
        $product = Product::factory()->create();
        [, $token] = ServiceClient::issue($product, 'test');
        $product->delete();

        $this->withToken($token)->getJson('/api/v1/organizations')->assertForbidden();
    }

    /* ---------------- Operátori ---------------- */

    public function test_novy_operator_dostane_odkaz_na_heslo_a_nie_heslo_od_kolegu(): void
    {
        Notification::fake();
        $admin = User::factory()->create();

        $this->actingAs($admin)->post('/settings/operators', ['name' => 'Nový', 'email' => 'novy@example.com'])
            ->assertSessionHas('success');

        $operator = User::where('email', 'novy@example.com')->firstOrFail();
        Notification::assertSentTo($operator, ResetPassword::class);
    }

    public function test_operatora_mozno_odobrat_a_odhlasi_sa(): void
    {
        config(['session.driver' => 'database']);
        $admin = User::factory()->create();
        $other = User::factory()->create();
        DB::table('sessions')->insert(['id' => 'abc', 'user_id' => $other->id, 'payload' => '', 'last_activity' => time()]);

        $this->actingAs($admin)->delete("/settings/operators/{$other->id}")->assertSessionHas('success');

        $this->assertSoftDeleted($other);
        $this->assertDatabaseMissing('sessions', ['id' => 'abc']);
    }

    public function test_sam_sebe_pristup_odobrat_nemozno(): void
    {
        $admin = User::factory()->create();
        User::factory()->create();

        $this->actingAs($admin)->delete("/settings/operators/{$admin->id}")->assertSessionHas('error');
        $this->assertNotSoftDeleted($admin);
    }

    public function test_odobraty_operator_sa_da_znova_pozvat(): void
    {
        Notification::fake();
        $admin = User::factory()->create();
        $other = User::factory()->create(['email' => 'spat@example.com']);
        $other->delete();

        $this->actingAs($admin)->post('/settings/operators', ['name' => 'Spat', 'email' => 'spat@example.com'])
            ->assertSessionHas('success');

        $this->assertNotSoftDeleted($other->fresh());
    }

    public function test_heslo_musi_mat_aspon_12_znakov(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $this->actingAs($user)->put('/settings/password', [
            'current_password' => 'password', 'password' => 'kratke1', 'password_confirmation' => 'kratke1',
        ])->assertSessionHasErrors('password');
    }

    /* ---------------- Webhooky ---------------- */

    public function test_tajomstvo_webhooku_je_v_databaze_sifrovane(): void
    {
        $endpoint = WebhookEndpoint::create(['product_id' => Product::factory()->create()->id, 'url' => 'https://example.com/hook']);

        $raw = DB::table('webhook_endpoints')->where('id', $endpoint->id)->value('secret');

        $this->assertStringStartsWith('whsec_', $endpoint->fresh()->secret);
        $this->assertStringNotContainsString('whsec_', $raw);
    }

    public function test_vnutorne_adresy_su_na_produkcii_zakazane(): void
    {
        config(['accounts.webhooks.allow_private' => false]);

        $this->assertFalse(PublicWebhookUrl::allowed('http://8.8.8.8/hook'));
        $this->assertFalse(PublicWebhookUrl::allowed('https://127.0.0.1/hook'));
        $this->assertFalse(PublicWebhookUrl::allowed('https://10.0.0.5/hook'));
        $this->assertFalse(PublicWebhookUrl::allowed('https://169.254.169.254/latest'));
        $this->assertFalse(PublicWebhookUrl::allowed('https://[::1]/hook'));
        $this->assertTrue(PublicWebhookUrl::allowed('https://8.8.8.8/hook'));
    }

    public function test_dorucenie_na_vnutornu_adresu_sa_zablokuje(): void
    {
        config(['accounts.webhooks.allow_private' => false]);
        Http::fake();

        $endpoint = WebhookEndpoint::create(['product_id' => Product::factory()->create()->id, 'url' => 'https://127.0.0.1/hook']);
        $delivery = WebhookDelivery::create([
            'webhook_endpoint_id' => $endpoint->id, 'event_id' => 'e1', 'event' => 'x', 'payload' => [], 'next_attempt_at' => now(),
        ]);

        (new DeliverWebhook($delivery->id))->handle();

        Http::assertNothingSent();
        $this->assertNotNull($delivery->fresh()->failed_at);
    }

    /* ---------------- pomocné ---------------- */

    /** @return array{0: Subscription, 1: Subscription} */
    private function subscriptions(): array
    {
        $organization = Organization::factory()->create();
        $manager = app(SubscriptionManager::class);

        $subs = [];
        foreach (['sub_A', 'sub_B'] as $i => $externalId) {
            $product = Product::factory()->create(['key' => "projekt-{$i}"]);
            $organization->linkTo($product);
            $plan = Plan::factory()->for($product)->create(['trial_days' => 0]);
            $subscription = $manager->subscribe($organization, $plan, $externalId);
            $subscription->forceFill(['external_customer_id' => 'cus_X'])->save();
            $subs[] = $manager->activate($subscription->fresh());
        }

        return $subs;
    }

    /** @param array<string, mixed> $payload */
    private function signedStripe(array $payload): TestResponse
    {
        $body = json_encode($payload);
        $t = time();
        $sig = hash_hmac('sha256', $t.'.'.$body, 'whsec_test');

        return $this->call('POST', '/api/webhooks/stripe', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => "t={$t},v1={$sig}",
        ], $body);
    }
}
