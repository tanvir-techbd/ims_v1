<?php

namespace App\Filament\Widgets;

use App\Enums\RequestItemStatus;
use App\Models\StockRequestItem;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Auth;

class StorekeeperAwaitingIssuanceWidget extends BaseWidget
{
    protected static ?string $heading = 'Approved — Awaiting Issuance';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return Auth::user()?->hasRole('Storekeeper') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                StockRequestItem::query()
                    ->whereIn('status', [RequestItemStatus::Approved, RequestItemStatus::PartiallyIssued])
                    ->latest()
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('stock_request_id')
                    ->label('Request')
                    ->formatStateUsing(fn (int $state) => "REQ-{$state}"),
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Product'),
                Tables\Columns\TextColumn::make('approved_qty')
                    ->label('Approved')
                    ->numeric(),
                Tables\Columns\TextColumn::make('product.current_stock')
                    ->label('In Stock')
                    ->numeric(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (StockRequestItem $record) => $record->product->current_stock >= ($record->approved_qty - $record->issued_qty)
                        ? 'Ready'
                        : 'Partial stock only')
                    ->color(fn (StockRequestItem $record) => $record->product->current_stock >= ($record->approved_qty - $record->issued_qty)
                        ? 'success'
                        : 'purple'),
            ])
            ->paginated(false)
            ->emptyStateHeading('Nothing waiting to be issued')
            ->emptyStateIcon('heroicon-o-check-circle');
    }
}
