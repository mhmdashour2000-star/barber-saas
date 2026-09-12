<?php

namespace App\Enums;

enum AppointmentStatus: string
{
    case CONFIRMED = 'confirmed';
    case COMPLETED = 'completed';
    case NO_SHOW = 'no_show';
    case CANCELLED_BY_CUSTOMER = 'cancelled_by_customer';
    case CANCELLED_BY_COMPANY = 'cancelled_by_company';

    /**
     * Terminal statuses that cannot transition to any other status.
     */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::COMPLETED,
            self::NO_SHOW,
            self::CANCELLED_BY_CUSTOMER,
            self::CANCELLED_BY_COMPANY => true,
            default => false,
        };
    }

    /**
     * Check if this status can transition to the given target status.
     */
    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::CONFIRMED => in_array($target, [
                self::COMPLETED,
                self::NO_SHOW,
                self::CANCELLED_BY_CUSTOMER,
                self::CANCELLED_BY_COMPANY,
            ]),
            default => false,
        };
    }

    /**
     * Statuses that block an employee's time slot (prevent double-booking).
     */
    public static function schedulingBlockingStatuses(): array
    {
        return [self::CONFIRMED];
    }

    /**
     * Check if this status blocks the employee schedule.
     */
    public function blocksSchedule(): bool
    {
        return $this === self::CONFIRMED;
    }

    /**
     * Human-readable label for UI display.
     */
    public function label(): string
    {
        return match ($this) {
            self::CONFIRMED => 'Confirmed',
            self::COMPLETED => 'Completed',
            self::NO_SHOW => 'No Show',
            self::CANCELLED_BY_CUSTOMER => 'Cancelled by Customer',
            self::CANCELLED_BY_COMPANY => 'Cancelled by Company',
        };
    }

    /**
     * CSS badge color for UI display.
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::CONFIRMED => 'bg-blue-100 text-blue-800',
            self::COMPLETED => 'bg-green-100 text-green-800',
            self::NO_SHOW => 'bg-red-100 text-red-800',
            self::CANCELLED_BY_CUSTOMER => 'bg-yellow-100 text-yellow-800',
            self::CANCELLED_BY_COMPANY => 'bg-gray-100 text-gray-800',
        };
    }
}
