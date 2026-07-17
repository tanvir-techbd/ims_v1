<?php

namespace App\Filament\Resources\StockRequestResource\Pages;

use App\Exceptions\InventoryRuleException;
use App\Filament\Resources\StockRequestResource;
use App\Models\Product;
use App\Models\StockRequest;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CreateStockRequest extends CreateRecord
{
    protected static string $resource = StockRequestResource::class;

    /**
     * The form's "products" checklist isn't a real column on
     * stock_requests — each ticked-and-quantified product is added
     * through StockRequest::addItem(), which is where the
     * ordering-permission rule (PLAN.md §3a) is actually enforced. Never
     * create StockRequestItem rows directly here.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $selected = collect($data['products'] ?? [])
            ->filter(fn (array $row) => (bool) ($row['selected'] ?? false));

        if ($selected->isEmpty()) {
            Notification::make()
                ->title('Could not submit request')
                ->body('Tick at least one product and enter a quantity.')
                ->danger()
                ->send();

            $this->halt();
        }

        try {
            return DB::transaction(function () use ($data, $selected) {
                $stockRequest = StockRequest::create([
                    'requester_id' => Auth::id(),
                    'notes' => $data['notes'] ?? null,
                ]);

                foreach ($selected as $productId => $row) {
                    $stockRequest->addItem(
                        Product::findOrFail($productId),
                        (int) ($row['qty'] ?? 0),
                    );
                }

                return $stockRequest;
            });
        } catch (InventoryRuleException $e) {
            Notification::make()
                ->title('Could not submit request')
                ->body($e->getMessage())
                ->danger()
                ->send();

            $this->halt();
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
