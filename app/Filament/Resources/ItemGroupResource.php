<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ItemGroupResource\Pages;
use App\Filament\Resources\ItemGroupResource\RelationManagers;
use App\Models\ItemGroup;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ItemGroupResource extends Resource
{
    protected static ?string $model = ItemGroup::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?string $navigationLabel = 'Item Groups';

    protected static ?string $modelLabel = 'Item Group';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Forms\Set $set, ?string $state) => $set('slug', str($state)->slug())),
                Forms\Components\TextInput::make('slug')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->helperText('Auto-filled from the name.'),
                Forms\Components\Textarea::make('description')
                    ->helperText('Item Groups gate which Demander groups may order a product — separate from Category, which is just for browsing.')
                    ->columnSpanFull(),
                Forms\Components\Select::make('products')
                    ->relationship('products', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->columnSpanFull(),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            TextEntry::make('name'),
            TextEntry::make('slug'),
            TextEntry::make('products.name')
                ->label('Products')
                ->badge()
                ->placeholder('None assigned'),
            TextEntry::make('userGroups.name')
                ->label('Permitted User Groups')
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
                Tables\Columns\TextColumn::make('slug')
                    ->searchable(),
                Tables\Columns\TextColumn::make('products_count')
                    ->counts('products')
                    ->label('Products')
                    ->sortable(),
                Tables\Columns\TextColumn::make('userGroups_count')
                    ->counts('userGroups')
                    ->label('Permitted Groups')
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
            'index' => Pages\ListItemGroups::route('/'),
            'create' => Pages\CreateItemGroup::route('/create'),
            'view' => Pages\ViewItemGroup::route('/{record}'),
            'edit' => Pages\EditItemGroup::route('/{record}/edit'),
        ];
    }
}
