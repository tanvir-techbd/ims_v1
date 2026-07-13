<?php

namespace App\Models;

use App\Enums\ApprovalDecision;
use App\Enums\RequestItemStatus;
use App\Exceptions\InventoryRuleException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class StockRequestItem extends Model
{
    /** @use HasFactory<\Database\Factories\StockRequestItemFactory> */
    use HasFactory;

    protected $fillable = [
        'stock_request_id',
        'product_id',
        'requested_qty',
        'approved_qty',
        'issued_qty',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'requested_qty' => 'integer',
            'approved_qty' => 'integer',
            'issued_qty' => 'integer',
            'status' => RequestItemStatus::class,
        ];
    }

    public function stockRequest(): BelongsTo
    {
        return $this->belongsTo(StockRequest::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(RequestApproval::class);
    }

    public function issuances(): HasMany
    {
        return $this->hasMany(StockIssuance::class);
    }

    /**
     * Approve up to (never more than) the requested quantity. Re-approving
     * an already-decided item replaces the decided amount but keeps the
     * full history in `request_approvals`.
     */
    public function approve(int $approvedQty, User $approver, ?string $remarks = null): RequestApproval
    {
        if ($approvedQty < 0) {
            throw new InventoryRuleException('Approved quantity cannot be negative.');
        }

        if ($approvedQty > $this->requested_qty) {
            throw new InventoryRuleException(
                "Cannot approve {$approvedQty} units — only {$this->requested_qty} were requested."
            );
        }

        if ($approvedQty < $this->issued_qty) {
            throw new InventoryRuleException(
                "Cannot reduce the approved quantity below {$this->issued_qty}, which has already been issued."
            );
        }

        return DB::transaction(function () use ($approvedQty, $approver, $remarks) {
            $approval = $this->approvals()->create([
                'approver_id' => $approver->id,
                'decision' => ApprovalDecision::Approved,
                'approved_qty' => $approvedQty,
                'remarks' => $remarks,
            ]);

            $this->approved_qty = $approvedQty;
            $this->recomputeStatus();
            $this->save();
            $this->stockRequest->recomputeStatus();

            return $approval;
        });
    }

    public function reject(User $approver, ?string $remarks = null): RequestApproval
    {
        if ($this->issued_qty > 0) {
            throw new InventoryRuleException('Cannot reject an item that has already had stock issued against it.');
        }

        return DB::transaction(function () use ($approver, $remarks) {
            $approval = $this->approvals()->create([
                'approver_id' => $approver->id,
                'decision' => ApprovalDecision::Rejected,
                'approved_qty' => 0,
                'remarks' => $remarks,
            ]);

            $this->approved_qty = 0;
            $this->status = RequestItemStatus::Rejected;
            $this->save();
            $this->stockRequest->recomputeStatus();

            return $approval;
        });
    }

    /**
     * Issue up to $requestedQty units, capped by both the remaining approved
     * amount and the product's actual stock on hand at this moment — the
     * storekeeper may end up issuing less than asked if stock is short.
     * Locks the product row for the duration of the transaction so
     * concurrent issuances against the same product can't oversell it.
     */
    public function issue(int $requestedQty, User $storekeeper, ?string $remarks = null): StockIssuance
    {
        if ($requestedQty <= 0) {
            throw new InventoryRuleException('Issue quantity must be greater than zero.');
        }

        if ($this->approved_qty === null) {
            throw new InventoryRuleException('This item has not been approved yet.');
        }

        $remainingApproved = $this->approved_qty - $this->issued_qty;

        if ($requestedQty > $remainingApproved) {
            throw new InventoryRuleException(
                "Cannot issue {$requestedQty} units — only {$remainingApproved} remain approved for this item."
            );
        }

        return DB::transaction(function () use ($requestedQty, $storekeeper, $remarks) {
            $product = Product::query()->lockForUpdate()->findOrFail($this->product_id);

            $actualQty = min($requestedQty, $product->current_stock);

            if ($actualQty <= 0) {
                throw new InventoryRuleException(
                    "Cannot issue \"{$product->name}\" — no stock currently available."
                );
            }

            $issuance = $this->issuances()->create([
                'storekeeper_id' => $storekeeper->id,
                'issued_qty' => $actualQty,
                'remarks' => $remarks,
            ]);

            $product->recordStockOut($actualQty, $storekeeper, $issuance, $remarks);

            $this->issued_qty += $actualQty;
            $this->recomputeStatus();
            $this->save();
            $this->stockRequest->recomputeStatus();

            return $issuance;
        });
    }

    public function recomputeStatus(): void
    {
        $this->status = match (true) {
            $this->approved_qty === null => RequestItemStatus::Pending,
            $this->approved_qty === 0 => RequestItemStatus::Rejected,
            $this->issued_qty === 0 => RequestItemStatus::Approved,
            $this->issued_qty < $this->approved_qty => RequestItemStatus::PartiallyIssued,
            default => RequestItemStatus::Issued,
        };
    }
}
