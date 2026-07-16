<?php

namespace App\Filament\Widgets;

use App\Enums\RequestItemStatus;
use App\Models\StockRequestItem;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Auth;

class ApproverPendingQueueWidget extends BaseWidget
{
    protected static ?string $heading = 'Pending Your Decision';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return Auth::user()?->hasRole('Approver') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                StockRequestItem::query()
                    ->where('status', RequestItemStatus::Pending)
                    ->latest()
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('stock_request_id')
                    ->label('Request')
                    ->formatStateUsing(fn (int $state) => "REQ-{$state}"),
                Tables\Columns\TextColumn::make('stockRequest.requester.name')
                    ->label('Requester'),
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Product'),
                Tables\Columns\TextColumn::make('requested_qty')
                    ->label('Requested')
                    ->numeric(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Submitted')
                    ->since(),
            ])
            ->paginated(false)
            ->emptyStateHeading('Nothing awaiting your decision')
            ->emptyStateIcon('heroicon-o-check-circle');
    }
}
