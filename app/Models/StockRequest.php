<?php

namespace App\Models;

use App\Enums\RequestItemStatus;
use App\Enums\RequestStatus;
use App\Exceptions\InventoryRuleException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class StockRequest extends Model
{
    /** @use HasFactory<\Database\Factories\StockRequestFactory> */
    use HasFactory;

    use LogsActivity;

    protected $fillable = [
        'requester_id',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => RequestStatus::class,
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly($this->fillable)->logOnlyDirty()->dontLogEmptyChanges();
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockRequestItem::class);
    }

    /**
     * The only sanctioned way to add an item to a request — enforces the
     * ordering-permission rule (PLAN.md §3a) server-side rather than relying
     * on the Filament form to only offer permitted products. Mirrors the
     * guarded-method pattern used by Product::recordStockIn() and
     * StockRequestItem::approve()/issue().
     */
    public function addItem(Product $product, int $requestedQty): StockRequestItem
    {
        if ($requestedQty <= 0) {
            throw new InventoryRuleException('Requested quantity must be greater than zero.');
        }

        if (! $this->requester->canOrderProduct($product)) {
            throw new InventoryRuleException(
                "\"{$product->name}\" is not orderable by {$this->requester->name} — it belongs to an item-group their user-group(s) aren't permitted for."
            );
        }

        return $this->items()->create([
            'product_id' => $product->id,
            'requested_qty' => $requestedQty,
        ]);
    }

    public function cancel(): void
    {
        if ($this->status !== RequestStatus::Pending) {
            throw new InventoryRuleException('Only a request with no decided items yet can be cancelled.');
        }

        $this->update(['status' => RequestStatus::Cancelled]);
    }

    /**
     * Derives the request's overall status from its items' individual
     * statuses. Called after any item is approved, rejected, or issued —
     * never set the parent status directly elsewhere.
     */
    public function recomputeStatus(): void
    {
        $statuses = $this->items()->pluck('status');

        if ($statuses->isEmpty() || $statuses->every(fn (RequestItemStatus $s) => $s === RequestItemStatus::Pending)) {
            $status = RequestStatus::Pending;
        } elseif ($statuses->contains(RequestItemStatus::Pending)) {
            // Some items decided, others still awaiting a decision.
            $status = RequestStatus::PartiallyApproved;
        } elseif ($statuses->every(fn (RequestItemStatus $s) => $s === RequestItemStatus::Rejected)) {
            $status = RequestStatus::Rejected;
        } elseif ($statuses->every(fn (RequestItemStatus $s) => in_array($s, [RequestItemStatus::Issued, RequestItemStatus::Rejected], true))) {
            $status = RequestStatus::Issued;
        } elseif ($statuses->contains(RequestItemStatus::PartiallyIssued) || $statuses->contains(RequestItemStatus::Issued)) {
            $status = RequestStatus::PartiallyIssued;
        } else {
            $status = RequestStatus::Approved;
        }

        $this->update(['status' => $status]);
    }
}
