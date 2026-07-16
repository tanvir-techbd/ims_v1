<?php

namespace App\Filament\Widgets;

use App\Enums\RequestStatus;
use App\Models\StockRequest;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Auth;

class DemanderMyRequestsWidget extends BaseWidget
{
    protected static ?string $heading = 'My Requests';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $user = Auth::user();

        return $user !== null && ! $user->hasAnyRole(['Admin', 'Approver', 'Storekeeper', 'Supplier']);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                StockRequest::query()
                    ->where('requester_id', Auth::id())
                    ->latest()
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('Request')
                    ->formatStateUsing(fn (int $state) => "REQ-{$state}"),
                Tables\Columns\TextColumn::make('items.product.name')
                    ->label('Items')
                    ->listWithLineBreaks()
                    ->limitList(3),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (RequestStatus $state) => $state->label())
                    ->color(fn (RequestStatus $state) => $state->color()),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Submitted')
                    ->since(),
            ])
            ->paginated(false)
            ->emptyStateHeading('No requests yet')
            ->emptyStateIcon('heroicon-o-clipboard-document-list');
    }
}
