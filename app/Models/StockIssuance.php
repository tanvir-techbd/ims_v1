<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class StockIssuance extends Model
{
    /** @use HasFactory<\Database\Factories\StockIssuanceFactory> */
    use HasFactory;

    protected $fillable = [
        'stock_request_item_id',
        'storekeeper_id',
        'issued_qty',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'issued_qty' => 'integer',
        ];
    }

    public function stockRequestItem(): BelongsTo
    {
        return $this->belongsTo(StockRequestItem::class);
    }

    public function storekeeper(): BelongsTo
    {
        return $this->belongsTo(User::class, 'storekeeper_id');
    }

    public function stockMovement(): MorphOne
    {
        return $this->morphOne(StockMovement::class, 'reference');
    }
}
