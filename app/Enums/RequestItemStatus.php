<?php

namespace App\Enums;

enum RequestItemStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case PartiallyIssued = 'partially_issued';
    case Issued = 'issued';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::PartiallyIssued => 'Partially Issued',
            self::Issued => 'Issued',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::PartiallyIssued => 'purple',
            self::Approved => 'info',
            self::Issued => 'success',
            self::Rejected => 'danger',
        };
    }
}
