<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Trialing = 'trialing';
    case Active = 'active';
    case PastDue = 'past_due';
    case Suspended = 'suspended';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Trialing => 'Skúšobné obdobie',
            self::Active => 'Aktívne',
            self::PastDue => 'Po splatnosti',
            self::Suspended => 'Pozastavené',
            self::Cancelled => 'Zrušené',
        };
    }

    /** Ma organizacia plny (zapisovaci) pristup k produktu? */
    public function grantsAccess(): bool
    {
        return in_array($this, [self::Trialing, self::Active, self::PastDue], true);
    }

    /** Moze citat data, ale nie zapisovat (paywall rezim). */
    public function isReadOnly(): bool
    {
        return $this === self::Suspended;
    }

    public function color(): string
    {
        return match ($this) {
            self::Trialing => 'sky',
            self::Active => 'emerald',
            self::PastDue => 'amber',
            self::Suspended => 'orange',
            self::Cancelled => 'rose',
        };
    }

    /**
     * Povolene prechody stavoveho automatu.
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Trialing => [self::Active, self::PastDue, self::Cancelled],
            self::Active => [self::PastDue, self::Cancelled],
            self::PastDue => [self::Active, self::Suspended, self::Cancelled],
            self::Suspended => [self::Active, self::Cancelled],
            self::Cancelled => [self::Active],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
