<?php

namespace App\Filament\Resources\StockRequestResource\Pages;

use App\Enums\RequestStatus;
use App\Filament\Resources\StockRequestResource;
use App\Models\StockRequest;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewStockRequest extends ViewRecord
{
    protected static string $resource = StockRequestResource::class;

    /**
     * Same "Mark as Received" action already on the list page's table row
     * — also needed here since a demander naturally lands on this page
     * (via the "View" link, or straight from the dashboard's "My Requests"
     * table) and would otherwise have to go back to the list to find it.
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('markReceived')
                ->label('Mark as Received')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription('Confirms you physically received the issued item(s). This cannot be undone.')
                ->visible(fn (StockRequest $record) => in_array($record->status, [
                    RequestStatus::Issued, RequestStatus::PartiallyIssued,
                ], true) && ($record->requester_id === Auth::id() || (Auth::user()?->hasRole('Admin') ?? false)))
                ->action(function (StockRequest $record): void {
                    $record->markReceived(Auth::user());

                    Notification::make()->title('Marked as received')->success()->send();
                }),
        ];
    }
}
