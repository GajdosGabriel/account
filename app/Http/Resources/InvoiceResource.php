<?php

namespace App\Http\Resources;

use App\Models\Invoice;
use App\Models\InvoiceEvent;
use App\Models\InvoiceItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/**
 * @mixin \App\Models\Invoice
 */
class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'type' => $this->type->value,
            'type_label' => $this->type->shortLabel(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_tone' => $this->status->tone(),

            'organization' => [
                'id' => $this->organization?->uuid,
                'name' => $this->billing_snapshot['name'] ?? $this->organization?->name,
            ],

            'currency' => $this->currency,
            'subtotal_cents' => $this->subtotal_cents,
            'vat_cents' => $this->vat_cents,
            'total_cents' => $this->total_cents,
            'paid_cents' => $this->paid_cents,
            'outstanding_cents' => $this->outstandingCents(),
            'total' => $this->formatMoney(),
            'outstanding' => $this->formatMoney($this->outstandingCents()),
            'vat_rate' => (float) $this->vat_rate,
            'vat_summary' => $this->vat_summary ?? [],
            'reverse_charge' => $this->reverse_charge,
            'vat_note' => $this->vat_note,

            'variable_symbol' => $this->variable_symbol,
            'constant_symbol' => $this->constant_symbol,
            'specific_symbol' => $this->specific_symbol,
            'payment_method' => $this->payment_method->value,
            'payment_method_label' => $this->payment_method->label(),

            'issued_at' => $this->issued_at?->toDateString(),
            'delivered_at' => $this->delivered_at?->toDateString(),
            'due_at' => $this->due_at?->toDateString(),
            'paid_at' => $this->paid_at?->toDateString(),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'sent_to' => $this->sent_to,
            'sent_count' => $this->sent_count,
            'reminder_count' => $this->reminder_count,
            'is_overdue' => $this->isOverdue(),
            'days_overdue' => $this->daysOverdue(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),

            'note' => $this->note,
            'internal_note' => $this->internal_note,
            'locale' => $this->locale,

            'parent' => $this->whenLoaded('parent', fn () => $this->parent ? [
                'id' => $this->parent->id,
                'number' => $this->parent->number,
                'type_label' => $this->parent->type->shortLabel(),
            ] : null),

            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn (InvoiceItem $item) => [
                'id' => $item->id,
                'description' => $item->description,
                'detail' => $item->detail,
                'quantity' => (float) $item->quantity,
                'unit' => $item->unit,
                'unit_price' => $item->unitPrice(),
                'discount_percent' => (float) $item->discount_percent,
                'vat_rate' => (float) $item->vat_rate,
                'subtotal_cents' => $item->subtotal_cents,
                'vat_cents' => $item->vat_cents,
                'total_cents' => $item->total_cents,
                'period' => $item->periodLabel(),
                'period_start' => $item->period_start?->toDateString(),
                'period_end' => $item->period_end?->toDateString(),
                'sort_order' => $item->sort_order,
            ])),

            'events' => $this->whenLoaded('events', fn () => $this->events->map(fn (InvoiceEvent $event) => [
                'id' => $event->id,
                'event' => $event->event,
                'label' => $event->label(),
                'icon' => $event->icon(),
                'description' => $event->description,
                'user' => $event->user?->name,
                'at' => $event->created_at?->toIso8601String(),
            ])),

            // Jediný zdroj pravdy pre dropdown menu vo frontende.
            'can' => static::abilities($this->resource),
        ];
    }

    /**
     * Zoznam povolených akcií pre daný doklad.
     *
     * Frontend z toho skladá položky v dropdowne – nikdy nezobrazí akciu,
     * ktorú by InvoicePolicy na serveri odmietla. Keď sa raz zmení pravidlo
     * v policy, UI sa prispôsobí samo.
     *
     * @return array<string, bool>
     */
    public static function abilities(Invoice $invoice): array
    {
        $checks = [
            'view', 'update', 'delete', 'issue', 'send', 'remind',
            'pay', 'cancel', 'credit', 'convert', 'download', 'duplicate',
            'restore', 'forceDelete',
        ];

        return collect($checks)
            ->mapWithKeys(fn (string $ability) => [$ability => Gate::allows($ability, $invoice)])
            ->all();
    }
}
