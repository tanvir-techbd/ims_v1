<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ActivityResource\Pages;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Form;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Models\Activity;

/**
 * General-purpose audit log (Admin-only) — spatie/laravel-activitylog
 * across every domain model, in addition to (not instead of) the
 * domain-level append-only trail already in request_approvals /
 * stock_issuances / stock_movements. This is the safety net that catches
 * everything else (e.g. a Category rename, a Product description edit).
 * Read-only: no create/edit/delete — this resource only ever displays
 * what already happened.
 */
class ActivityResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationLabel = 'Audit Log';

    protected static ?string $modelLabel = 'Activity';

    protected static ?string $navigationGroup = 'Access Control';

    protected static ?int $navigationSort = 4;

    public static function canViewAny(): bool
    {
        return Auth::user()?->hasRole('Admin') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('causer.name')
                    ->label('User')
                    ->placeholder('System')
                    ->searchable(),
                Tables\Columns\TextColumn::make('event')
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'created' => 'success',
                        'updated' => 'info',
                        'deleted' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('subject_type')
                    ->label('Model')
                    ->formatStateUsing(fn (?string $state) => $state ? class_basename($state) : '—')
                    ->searchable(),
                Tables\Columns\TextColumn::make('description')
                    ->searchable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('subject_type')
                    ->label('Model')
                    ->options(fn () => Activity::query()
                        ->whereNotNull('subject_type')
                        ->distinct()
                        ->pluck('subject_type', 'subject_type')
                        ->mapWithKeys(fn ($type) => [$type => class_basename($type)])),
                Tables\Filters\SelectFilter::make('event')
                    ->options([
                        'created' => 'Created',
                        'updated' => 'Updated',
                        'deleted' => 'Deleted',
                    ]),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        DatePicker::make('from')->native(false),
                        DatePicker::make('until')->native(false),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('viewChanges')
                    ->label('View Changes')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->infolist(fn (Activity $record) => [
                        TextEntry::make('description')->hiddenLabel(),
                        TextEntry::make('causer.name')->label('User')->placeholder('System'),
                        TextEntry::make('created_at')->label('When')->dateTime(),
                        KeyValueEntry::make('old')
                            ->label('Before')
                            ->state($record->attribute_changes?->get('old') ?? [])
                            ->visible(fn () => filled($record->attribute_changes?->get('old'))),
                        KeyValueEntry::make('new')
                            ->label('After')
                            ->state($record->attribute_changes?->get('attributes') ?? [])
                            ->visible(fn () => filled($record->attribute_changes?->get('attributes'))),
                    ])
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivities::route('/'),
        ];
    }
}
