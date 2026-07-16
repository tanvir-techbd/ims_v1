<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserGroupResource\Pages;
use App\Models\UserGroup;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class UserGroupResource extends Resource
{
    protected static ?string $model = UserGroup::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Access Control';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->helperText('e.g. "Facilities Team", "IT Department".'),
                Forms\Components\Textarea::make('description')
                    ->columnSpanFull(),
                Forms\Components\Select::make('users')
                    ->relationship('users', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->columnSpanFull()
                    ->helperText('Members of this group — typically Demanders.'),
                Forms\Components\Select::make('itemGroups')
                    ->label('Permitted Item Groups')
                    ->relationship('itemGroups', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->columnSpanFull()
                    ->helperText('This group may only order products belonging to one of the item-groups selected here. Products with no item-group at all remain open to everyone regardless of this setting.')
                    ->saveRelationshipsUsing(function (UserGroup $record, ?array $state): void {
                        $record->itemGroups()->sync(collect($state ?? [])->mapWithKeys(
                            fn (int|string $itemGroupId) => [$itemGroupId => ['granted_by' => Auth::id()]]
                        ));
                    }),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            TextEntry::make('name'),
            TextEntry::make('users.name')
                ->label('Members')
                ->badge()
                ->placeholder('No members yet'),
            TextEntry::make('itemGroups.name')
                ->label('Permitted Item Groups')
                ->badge()
                ->placeholder('None permitted yet'),
            TextEntry::make('description')->placeholder('—')->columnSpanFull(),
            TextEntry::make('created_at')->dateTime(),
            TextEntry::make('updated_at')->dateTime(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('users_count')
                    ->counts('users')
                    ->label('Members')
                    ->sortable(),
                Tables\Columns\TextColumn::make('itemGroups_count')
                    ->counts('itemGroups')
                    ->label('Permitted Item Groups')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUserGroups::route('/'),
            'create' => Pages\CreateUserGroup::route('/create'),
            'view' => Pages\ViewUserGroup::route('/{record}'),
            'edit' => Pages\EditUserGroup::route('/{record}/edit'),
        ];
    }
}
