<?php

namespace App\Models;

use App\Enums\ApprovalDecision;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class RequestApproval extends Model
{
    /** @use HasFactory<\Database\Factories\RequestApprovalFactory> */
    use HasFactory;

    use LogsActivity;

    protected $fillable = [
        'stock_request_item_id',
        'approver_id',
        'decision',
        'approved_qty',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'decision' => ApprovalDecision::class,
            'approved_qty' => 'integer',
        ];
    }

    /**
     * Append-only (never updated after creation) — this just captures the
     * "created" event for the general audit log; request_approvals itself
     * is already the authoritative per-item trail (see the Phase 4 "View
     * Trail" action).
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly($this->fillable)->dontLogEmptyChanges();
    }

    public function stockRequestItem(): BelongsTo
    {
        return $this->belongsTo(StockRequestItem::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
