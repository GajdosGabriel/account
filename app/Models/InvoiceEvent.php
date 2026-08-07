<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Nemenný záznam v histórii dokladu.
 */
class InvoiceEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['invoice_id', 'user_id', 'event', 'description', 'meta'];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function label(): string
    {
        return match ($this->event) {
            'created' => 'Vytvorený koncept',
            'issued' => 'Doklad vystavený',
            'sent' => 'Odoslané e-mailom',
            'viewed' => 'Zákazník otvoril doklad',
            'paid' => 'Označené ako uhradené',
            'partially_paid' => 'Prijatá čiastočná úhrada',
            'reminded' => 'Odoslaná upomienka',
            'cancelled' => 'Doklad stornovaný',
            'credited' => 'Vystavený dobropis',
            'exported' => 'Exportované pre účtovníka',
            default => $this->event,
        };
    }

    public function icon(): string
    {
        return match ($this->event) {
            'sent', 'reminded' => 'mail',
            'paid', 'partially_paid' => 'check',
            'cancelled' => 'warning',
            'exported' => 'download',
            default => 'invoice',
        };
    }
}
