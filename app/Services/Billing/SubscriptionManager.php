<?php

namespace App\Services\Billing;

use App\Enums\SubscriptionStatus;
use App\Events\SubscriptionStatusChanged;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\SubscriptionEvent;
use App\Services\Entitlements\EntitlementService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Stavový automat predplatného.
 *
 *   trialing ─┬─> active ──> past_due ──> suspended ──> cancelled
 *             └─> past_due       ^  │          │
 *                                └──┘          └──> active (po zaplatení)
 */
class SubscriptionManager
{
    public function __construct(private readonly EntitlementService $entitlements) {}

    public function subscribe(Organization $organization, Plan $plan, ?string $externalId = null): Subscription
    {
        $product = $plan->product;

        return DB::transaction(function () use ($organization, $plan, $product, $externalId) {
            $subscription = Subscription::updateOrCreate(
                ['organization_id' => $organization->id, 'product_id' => $product->id],
                [
                    'plan_id' => $plan->id,
                    'status' => $plan->trial_days > 0 ? SubscriptionStatus::Trialing : SubscriptionStatus::Active,
                    'trial_ends_at' => $plan->trial_days > 0 ? now()->addDays($plan->trial_days) : null,
                    'current_period_start' => now(),
                    'current_period_end' => $this->periodEnd($plan),
                    'grace_ends_at' => null,
                    'suspended_until' => null,
                    'cancelled_at' => null,
                    'ends_at' => null,
                    'external_id' => $externalId,
                ],
            );

            $this->log($subscription, null, $subscription->status, 'subscribed');
            $this->entitlements->flush($organization, $product);

            event(new SubscriptionStatusChanged($subscription, null, $subscription->status, 'subscribed'));

            return $subscription;
        });
    }

    public function changePlan(Subscription $subscription, Plan $plan): Subscription
    {
        if ($plan->product_id !== $subscription->product_id) {
            throw new InvalidArgumentException('Plán patrí inému produktu.');
        }

        $subscription->forceFill([
            'plan_id' => $plan->id,
            'current_period_end' => $this->periodEnd($plan),
        ])->save();

        $this->log($subscription, $subscription->status, $subscription->status, 'plan_changed');
        $this->entitlements->flush($subscription->organization, $subscription->product);

        return $subscription->refresh();
    }

    /** Platba zlyhala – spúšťa grace periódu, prístup zatiaľ zostáva. */
    public function markPastDue(Subscription $subscription, string $reason = 'payment_failed'): Subscription
    {
        return $this->transition($subscription, SubscriptionStatus::PastDue, $reason, [
            'grace_ends_at' => now()->addDays(config('accounts.grace_days')),
        ]);
    }

    /** Platba prišla – vracia predplatné do aktívneho stavu. */
    public function activate(Subscription $subscription, string $reason = 'payment_succeeded'): Subscription
    {
        return $this->transition($subscription, SubscriptionStatus::Active, $reason, [
            'grace_ends_at' => null,
            'suspended_until' => null,
            'cancelled_at' => null,
            'current_period_start' => now(),
            'current_period_end' => $this->periodEnd($subscription->plan),
        ]);
    }

    /** Grace perióda vypršala – read-only režim + paywall. */
    public function suspend(Subscription $subscription, string $reason = 'grace_period_expired'): Subscription
    {
        return $this->transition($subscription, SubscriptionStatus::Suspended, $reason, [
            'suspended_until' => now()->addDays(config('accounts.suspended_days')),
        ]);
    }

    /** Definitívne ukončenie – dáta zostávajú, prístup je uzamknutý. */
    public function cancel(Subscription $subscription, string $reason = 'cancelled'): Subscription
    {
        return $this->transition($subscription, SubscriptionStatus::Cancelled, $reason, [
            'cancelled_at' => now(),
            'ends_at' => now(),
        ]);
    }

    /**
     * Jediná cesta, ako sa mení stav. Kontroluje povolené prechody,
     * zapíše audit, zneplatní cache a odpáli event pre webhooky.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function transition(Subscription $subscription, SubscriptionStatus $to, string $reason, array $attributes = []): Subscription
    {
        $from = $subscription->status;

        if ($from === $to) {
            return $subscription;
        }

        if (! $from->canTransitionTo($to)) {
            throw new InvalidArgumentException(
                "Neplatný prechod predplatného: {$from->value} -> {$to->value}"
            );
        }

        DB::transaction(function () use ($subscription, $to, $attributes) {
            $subscription->forceFill(array_merge($attributes, ['status' => $to]))->save();
        });

        $this->log($subscription, $from, $to, $reason);
        $this->entitlements->flush($subscription->organization, $subscription->product);

        event(new SubscriptionStatusChanged($subscription, $from, $to, $reason));

        return $subscription->refresh();
    }

    /**
     * Denná údržba: posúva predplatné podľa uplynutých lehôt.
     *
     * @return array<string, int>
     */
    public function processLifecycle(): array
    {
        $counts = ['trial_ended' => 0, 'suspended' => 0, 'cancelled' => 0, 'renewal_due' => 0];

        // Skúšobné obdobie skončilo -> čaká sa platba
        Subscription::query()
            ->status(SubscriptionStatus::Trialing)
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<=', now())
            ->each(function (Subscription $subscription) use (&$counts) {
                $this->markPastDue($subscription, 'trial_ended');
                $counts['trial_ended']++;
            });

        // Obdobie skončilo a nová platba neprišla
        Subscription::query()
            ->status(SubscriptionStatus::Active)
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '<=', now())
            ->each(function (Subscription $subscription) use (&$counts) {
                $this->markPastDue($subscription, 'period_ended');
                $counts['renewal_due']++;
            });

        // Grace perióda vypršala -> read-only
        Subscription::query()
            ->status(SubscriptionStatus::PastDue)
            ->whereNotNull('grace_ends_at')
            ->where('grace_ends_at', '<=', now())
            ->each(function (Subscription $subscription) use (&$counts) {
                $this->suspend($subscription);
                $counts['suspended']++;
            });

        // Read-only lehota vypršala -> uzamknutie
        Subscription::query()
            ->status(SubscriptionStatus::Suspended)
            ->whereNotNull('suspended_until')
            ->where('suspended_until', '<=', now())
            ->each(function (Subscription $subscription) use (&$counts) {
                $this->cancel($subscription, 'suspension_expired');
                $counts['cancelled']++;
            });

        return $counts;
    }

    protected function periodEnd(?Plan $plan): ?\Illuminate\Support\Carbon
    {
        if (! $plan) {
            return null;
        }

        return $plan->interval === 'year' ? now()->addYear() : now()->addMonth();
    }

    protected function log(Subscription $subscription, ?SubscriptionStatus $from, SubscriptionStatus $to, string $reason): void
    {
        SubscriptionEvent::create([
            'subscription_id' => $subscription->id,
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'reason' => $reason,
        ]);
    }

    public function productFor(Plan $plan): Product
    {
        return $plan->product;
    }
}
