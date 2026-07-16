<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class StockIssuance extends Model
{
    /** @use HasFactory<\Database\Factories\StockIssuanceFactory> */
    use HasFactory;

    use LogsActivity;

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

    /** Append-only — see RequestApproval's note on why this only logs "created". */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly($this->fillable)->dontLogEmptyChanges();
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
