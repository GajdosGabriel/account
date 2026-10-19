<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\Invoicing\InvoiceSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_stranka_nastaveni_sa_zobrazi(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/invoices/settings')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Invoices/Settings')
                ->where('settings.reminders.enabled', (bool) config('invoicing.reminders.enabled')));
    }

    public function test_vypnutie_upomienok_sa_ulozi_a_prejavi_v_configu(): void
    {
        $payload = app(InvoiceSettings::class)->form();
        $payload['reminders']['enabled'] = false;
        $payload['reminders']['levels'][0]['days'] = 5;
        $payload['sending']['bcc'] = 'uctovnik@example.com';

        $this->actingAs(User::factory()->create())
            ->put('/invoices/settings', $payload)
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertFalse(config('invoicing.reminders.enabled'));
        $this->assertSame([5 => 'friendly', 10 => 'firm', 21 => 'final'], config('invoicing.reminders.schedule'));
        $this->assertSame('uctovnik@example.com', config('invoicing.sending.bcc'));

        $this->assertDatabaseHas('settings', ['key' => 'invoicing.reminders.enabled']);

        // Hodnoty zhodné s .env sa neukladajú.
        $this->assertDatabaseMissing('settings', ['key' => 'invoicing.qr.format']);
    }

    public function test_urovne_musia_ist_po_sebe(): void
    {
        $payload = app(InvoiceSettings::class)->form();
        $payload['reminders']['levels'][1]['days'] = 2; // firm skôr ako friendly (3)

        $this->actingAs(User::factory()->create())
            ->put('/invoices/settings', $payload)
            ->assertSessionHasErrors('reminders.levels');
    }

    public function test_obnovenie_vrati_hodnoty_z_env(): void
    {
        $original = config('invoicing.reminders.enabled');

        Setting::put('invoicing.reminders.enabled', ! $original);
        $this->assertSame(! $original, config('invoicing.reminders.enabled'));

        $this->actingAs(User::factory()->create())
            ->delete('/invoices/settings')
            ->assertRedirect();

        $this->assertSame($original, config('invoicing.reminders.enabled'));
        $this->assertDatabaseCount('settings', 0);
    }

    public function test_vypnute_upomienky_sa_neposielaju(): void
    {
        Setting::put('invoicing.reminders.enabled', false);

        $this->artisan('invoices:remind')
            ->expectsOutputToContain('Upomienky sú vypnuté')
            ->assertSuccessful();
    }
}
