<?php

namespace App\Enums;

enum AppointmentEventType: string
{
    case CREATED = 'created';
    case RESCHEDULED = 'rescheduled';
    case CANCELLED_BY_CUSTOMER = 'cancelled_by_customer';
    case CANCELLED_BY_COMPANY = 'cancelled_by_company';
    case COMPLETED = 'completed';
    case NO_SHOW = 'no_show';

    /**
     * Human-readable label for UI display.
     */
    public function label(): string
    {
        return match ($this) {
            self::CREATED => 'Appointment Created',
            self::RESCHEDULED => 'Rescheduled',
            self::CANCELLED_BY_CUSTOMER => 'Cancelled by Customer',
            self::CANCELLED_BY_COMPANY => 'Cancelled by Company',
            self::COMPLETED => 'Completed',
            self::NO_SHOW => 'No Show',
        };
    }

    /**
     * Icon color for event timeline.
     */
    public function iconColor(): string
    {
        return match ($this) {
            self::CREATED => 'text-blue-500',
            self::RESCHEDULED => 'text-purple-500',
            self::CANCELLED_BY_CUSTOMER => 'text-yellow-500',
            self::CANCELLED_BY_COMPANY => 'text-gray-500',
            self::COMPLETED => 'text-green-500',
            self::NO_SHOW => 'text-red-500',
        };
    }
}
