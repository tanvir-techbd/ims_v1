<?php

namespace App\Filament\Widgets;

use App\Enums\RequestStatus;
use App\Models\StockRequest;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Auth;

class AdminRecentRequestsWidget extends BaseWidget
{
    protected static ?string $heading = 'Recent Requests';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return Auth::user()?->hasRole('Admin') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(StockRequest::query()->latest()->limit(5))
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('Request')
                    ->formatStateUsing(fn (int $state) => "REQ-{$state}"),
                Tables\Columns\TextColumn::make('requester.name')
                    ->label('Requester'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (RequestStatus $state) => $state->label())
                    ->color(fn (RequestStatus $state) => $state->color()),
                Tables\Columns\TextColumn::make('items_count')
                    ->counts('items')
                    ->label('Items'),
            ])
            ->paginated(false)
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No requests yet')
            ->emptyStateIcon('heroicon-o-clipboard-document-list');
    }
}
