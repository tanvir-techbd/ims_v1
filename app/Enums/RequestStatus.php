<?php

namespace App\Enums;

enum RequestStatus: string
{
    case Pending = 'pending';
    case PartiallyApproved = 'partially_approved';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case PartiallyIssued = 'partially_issued';
    case Issued = 'issued';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::PartiallyApproved => 'Partially Approved',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::PartiallyIssued => 'Partially Issued',
            self::Issued => 'Issued',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::PartiallyApproved, self::PartiallyIssued => 'purple',
            self::Approved => 'info',
            self::Issued => 'success',
            self::Rejected => 'danger',
            self::Cancelled => 'gray',
        };
    }
}
