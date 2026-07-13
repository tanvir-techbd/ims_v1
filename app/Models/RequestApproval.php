<?php

namespace App\Models;

use App\Enums\ApprovalDecision;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestApproval extends Model
{
    /** @use HasFactory<\Database\Factories\RequestApprovalFactory> */
    use HasFactory;

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

    public function stockRequestItem(): BelongsTo
    {
        return $this->belongsTo(StockRequestItem::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
