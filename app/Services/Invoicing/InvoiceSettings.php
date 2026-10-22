<?php

namespace App\Services\Invoicing;

use App\Models\AuditLog;
use App\Models\Setting;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Nastavenia fakturácie meniteľné z back-office.
 *
 * Formulár má vlastný tvar (úrovne upomienok ako zoznam, sumy v eurách),
 * do configu sa ukladá tak, ako ho čaká zvyšok aplikácie – vďaka tomu
 * služby a príkazy ďalej čítajú `config('invoicing…')` a o databáze
 * nemusia vedieť.
 *
 * Hodnota zhodná s .env sa neukladá. Kto nič nemenil, ďalej sa riadi
 * .env a jeho zmena sa prejaví bez zásahu do databázy.
 */
class InvoiceSettings
{
    /** Úrovne upomienok od najmiernejšej. */
    public const TONES = ['friendly', 'firm', 'final'];

    public const QR_FORMATS = ['pay_by_square', 'epc'];

    /** Režimy odosielania – viď config/invoicing.php, sekcia Odosielanie. */
    public const SENDING_MODES = ['live', 'redirect', 'off'];

    /**
     * Aktuálne hodnoty v tvare formulára.
     *
     * @return array<string, mixed>
     */
    public function form(): array
    {
        return $this->build(fn (string $key) => config($key));
    }

    /**
     * Hodnoty z .env – na čo sa vráti „Obnoviť pôvodné“.
     *
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return $this->build(fn (string $key) => Setting::original($key));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reminders.enabled' => ['required', 'boolean'],
            'reminders.levels' => ['required', 'array', 'size:'.count(self::TONES)],
            'reminders.levels.*.tone' => ['required', Rule::in(self::TONES)],
            'reminders.levels.*.enabled' => ['required', 'boolean'],
            'reminders.levels.*.days' => ['required', 'integer', 'min:1', 'max:365'],
            'reminders.min_amount' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'reminders.final_grace_days' => ['required', 'integer', 'min:1', 'max:90'],
            'reminders.attach_pdf' => ['required', 'boolean'],
            'reminders.copy_to_supplier' => ['required', 'boolean'],

            'sending.require_verified_email' => ['required', 'boolean'],
            'sending.mode' => ['required', Rule::in(self::SENDING_MODES)],
            'sending.redirect_to' => ['nullable', 'required_if:sending.mode,redirect', 'email', 'max:255'],
            'sending.bcc' => ['nullable', 'email', 'max:255'],

            'document.due_days' => ['required', 'integer', 'min:0', 'max:365'],
            'document.constant_symbol' => ['nullable', 'regex:/^\d{1,4}$/'],
            'document.unit' => ['required', 'string', 'max:20'],
            'document.vat_rate' => ['required', 'numeric', Rule::in(config('invoicing.vat.rates', []))],
            'document.footer_note' => ['nullable', 'string', 'max:1000'],

            'qr.enabled' => ['required', 'boolean'],
            'qr.format' => ['required', Rule::in(self::QR_FORMATS)],

            'billing_run.issue_days_before' => ['required', 'integer', 'min:0', 'max:60'],
            'billing_run.auto_issue' => ['required', 'boolean'],
            'billing_run.auto_send' => ['required', 'boolean'],
            'billing_run.skip_zero' => ['required', 'boolean'],
        ];
    }

    /**
     * Kontroly, ktoré nejdú zapísať jedným pravidlom.
     */
    public function after(Validator $validator): void
    {
        $data = $validator->getData();
        $levels = collect($data['reminders']['levels'] ?? [])->filter(fn ($l) => (bool) ($l['enabled'] ?? false));

        if (($data['reminders']['enabled'] ?? false) && $levels->isEmpty()) {
            $validator->errors()->add('reminders.levels', __('invoices.settings.errors.no_level'));
        }

        // Prísnejšia upomienka nesmie prísť skôr než miernejšia.
        $days = $levels->pluck('days')->map(fn ($d) => (int) $d)->values();

        foreach ($days as $i => $value) {
            if ($i > 0 && $value <= $days[$i - 1]) {
                $validator->errors()->add('reminders.levels', __('invoices.settings.errors.level_order'));
                break;
            }
        }
    }

    /**
     * Uloží formulár. Vráti zoznam zmenených kľúčov configu.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, string>
     */
    public function save(array $data, ?int $userId = null): array
    {
        $changed = [];

        foreach ($this->toConfig($data) as $key => $value) {
            if ($value != config($key)) {
                $changed[] = $key;
            }

            $value == Setting::original($key)
                ? Setting::forget($key)
                : Setting::put($key, $value, $userId);
        }

        if ($changed) {
            AuditLog::record('settings.invoicing.updated', null, ['changed' => $changed]);
        }

        return $changed;
    }

    /** Všetko späť na hodnoty z .env. */
    public function reset(): void
    {
        foreach (array_keys($this->toConfig($this->form())) as $key) {
            Setting::forget($key);
        }

        AuditLog::record('settings.invoicing.reset');
    }

    /**
     * Formulár → kľúče configu.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function toConfig(array $data): array
    {
        $schedule = collect($data['reminders']['levels'])
            ->filter(fn ($level) => (bool) $level['enabled'])
            ->mapWithKeys(fn ($level) => [(int) $level['days'] => $level['tone']])
            ->sortKeys()
            ->all();

        $autoSend = (bool) $data['billing_run']['auto_send'];

        return [
            'invoicing.reminders.enabled' => (bool) $data['reminders']['enabled'],
            'invoicing.reminders.schedule' => $schedule,
            'invoicing.reminders.min_outstanding_cents' => (int) round(((float) $data['reminders']['min_amount']) * 100),
            'invoicing.reminders.final_grace_days' => (int) $data['reminders']['final_grace_days'],
            'invoicing.reminders.attach_pdf' => (bool) $data['reminders']['attach_pdf'],
            'invoicing.reminders.copy_to_supplier' => (bool) $data['reminders']['copy_to_supplier'],

            'invoicing.require_verified_billing_email' => (bool) $data['sending']['require_verified_email'],
            'invoicing.sending.bcc' => filled($data['sending']['bcc'] ?? null) ? trim($data['sending']['bcc']) : null,
            'invoicing.sending.mode' => $data['sending']['mode'],
            'invoicing.sending.redirect_to' => filled($data['sending']['redirect_to'] ?? null)
                ? trim($data['sending']['redirect_to']) : null,

            'invoicing.defaults.due_days' => (int) $data['document']['due_days'],
            'invoicing.defaults.constant_symbol' => filled($data['document']['constant_symbol'] ?? null)
                ? (string) $data['document']['constant_symbol'] : null,
            'invoicing.defaults.unit' => trim((string) $data['document']['unit']),
            'invoicing.vat.standard' => (float) $data['document']['vat_rate'],
            'invoicing.defaults.footer_note' => filled($data['document']['footer_note'] ?? null)
                ? trim($data['document']['footer_note']) : null,

            'invoicing.qr.enabled' => (bool) $data['qr']['enabled'],
            'invoicing.qr.format' => $data['qr']['format'],

            'invoicing.billing_run.issue_days_before' => (int) $data['billing_run']['issue_days_before'],
            // Odoslať sa dá len vystavený doklad.
            'invoicing.billing_run.auto_issue' => $autoSend || (bool) $data['billing_run']['auto_issue'],
            'invoicing.billing_run.auto_send' => $autoSend,
            'invoicing.billing_run.skip_zero' => (bool) $data['billing_run']['skip_zero'],
        ];
    }

    /**
     * Kľúče configu → formulár.
     *
     * @param  \Closure(string): mixed  $get
     * @return array<string, mixed>
     */
    protected function build(\Closure $get): array
    {
        $schedule = (array) ($get('invoicing.reminders.schedule') ?? []);
        $fallback = [3, 10, 21];

        $levels = collect(self::TONES)->map(function (string $tone, int $i) use ($schedule, $fallback) {
            $days = array_search($tone, $schedule, true);

            return [
                'tone' => $tone,
                'enabled' => $days !== false,
                'days' => $days !== false ? (int) $days : $fallback[$i],
            ];
        })->all();

        return [
            'reminders' => [
                'enabled' => (bool) $get('invoicing.reminders.enabled'),
                'levels' => $levels,
                'min_amount' => ((int) $get('invoicing.reminders.min_outstanding_cents')) / 100,
                'final_grace_days' => (int) ($get('invoicing.reminders.final_grace_days') ?? 7),
                'attach_pdf' => (bool) ($get('invoicing.reminders.attach_pdf') ?? true),
                'copy_to_supplier' => (bool) $get('invoicing.reminders.copy_to_supplier'),
            ],
            'sending' => [
                'require_verified_email' => (bool) $get('invoicing.require_verified_billing_email'),
                'bcc' => $get('invoicing.sending.bcc'),
                'mode' => (string) ($get('invoicing.sending.mode') ?? 'live'),
                'redirect_to' => $get('invoicing.sending.redirect_to'),
            ],
            'document' => [
                'due_days' => (int) $get('invoicing.defaults.due_days'),
                'constant_symbol' => $get('invoicing.defaults.constant_symbol'),
                'unit' => (string) $get('invoicing.defaults.unit'),
                'vat_rate' => (float) $get('invoicing.vat.standard'),
                'footer_note' => $get('invoicing.defaults.footer_note'),
            ],
            'qr' => [
                'enabled' => (bool) $get('invoicing.qr.enabled'),
                'format' => (string) ($get('invoicing.qr.format') ?? 'pay_by_square'),
            ],
            'billing_run' => [
                'issue_days_before' => (int) $get('invoicing.billing_run.issue_days_before'),
                'auto_issue' => (bool) $get('invoicing.billing_run.auto_issue'),
                'auto_send' => (bool) $get('invoicing.billing_run.auto_send'),
                'skip_zero' => (bool) $get('invoicing.billing_run.skip_zero'),
            ],
        ];
    }
}
