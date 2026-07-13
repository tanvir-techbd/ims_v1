<?php

namespace App\Models;

use App\Enums\StockMovementType;
use App\Exceptions\InventoryRuleException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    /** @use HasFactory<\Database\Factories\ProductFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'name',
        'sku',
        'category_id',
        'unit_id',
        'description',
        'current_stock',
    ];

    protected function casts(): array
    {
        return [
            'current_stock' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function requestItems(): HasMany
    {
        return $this->hasMany(StockRequestItem::class);
    }

    public function isLowStock(): bool
    {
        return $this->current_stock <= (int) Setting::get('low_stock_threshold', 10);
    }

    /**
     * Record stock received (purchase/delivery). Storekeeper/Admin only —
     * callers are responsible for the permission check, this method only
     * enforces the data invariant (current_stock stays in sync with the ledger).
     */
    public function recordStockIn(int $quantity, User $actor, ?string $note = null): StockMovement
    {
        if ($quantity <= 0) {
            throw new InventoryRuleException('Stock-in quantity must be greater than zero.');
        }

        return \DB::transaction(function () use ($quantity, $actor, $note) {
            $product = static::query()->lockForUpdate()->findOrFail($this->id);

            $movement = $product->stockMovements()->create([
                'type' => StockMovementType::In,
                'quantity' => $quantity,
                'note' => $note,
                'created_by' => $actor->id,
            ]);

            $product->increment('current_stock', $quantity);
            $this->current_stock = $product->fresh()->current_stock;

            return $movement;
        });
    }

    /**
     * Record stock leaving (an issuance). Never call this directly for the
     * request workflow — go through StockRequestItem::issue(), which caps
     * the quantity at what's actually in stock under the same row lock.
     */
    public function recordStockOut(int $quantity, User $actor, ?Model $reference = null, ?string $note = null): StockMovement
    {
        if ($quantity <= 0) {
            throw new InventoryRuleException('Stock-out quantity must be greater than zero.');
        }

        return \DB::transaction(function () use ($quantity, $actor, $reference, $note) {
            $product = static::query()->lockForUpdate()->findOrFail($this->id);

            if ($quantity > $product->current_stock) {
                throw new InventoryRuleException(
                    "Cannot issue {$quantity} units of \"{$product->name}\" — only {$product->current_stock} in stock."
                );
            }

            $movement = $product->stockMovements()->create([
                'type' => StockMovementType::Out,
                'quantity' => $quantity,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'note' => $note,
                'created_by' => $actor->id,
            ]);

            $product->decrement('current_stock', $quantity);
            $this->current_stock = $product->fresh()->current_stock;

            return $movement;
        });
    }
}
