<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RecepturaResource\Pages;
use App\Filament\Resources\RecepturaResource\RelationManagers;
use App\Models\Receptura;
use App\Models\Surowiec;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Get;

class RecepturaResource extends Resource
{
    protected static ?string $model = Receptura::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    
    protected static ?string $navigationLabel = 'Receptury';
    
    protected static ?string $modelLabel = 'Receptura';
    
    protected static ?string $pluralModelLabel = 'Receptury';
    
    protected static ?string $navigationGroup = 'Produkcja';
    
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('nazwa')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('kod')
                    ->required()
                    ->unique(ignorable: fn ($record) => $record)
                    ->maxLength(255),
                Forms\Components\Textarea::make('opis')
                    ->maxLength(65535)
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('koszt_calkowity')
                    ->disabled()
                    ->dehydrated(false)
                    ->prefix('PLN')
                    ->numeric()
                    ->label('Koszt całkowity (za 1kg)')
                    ->helperText('Koszt wytworzenia 1kg produktu według tej receptury.'),
                    
                Forms\Components\Placeholder::make('suma_procentowa')
                    ->label('Suma procentowa składników')
                    ->content(function ($record) {
                        if (!$record) return 'Obliczana po zapisaniu receptury';
                        
                        // Pobierz świeżą instancję rekordu, aby mieć aktualne dane
                        $record = Receptura::with('surowce')->find($record->id);
                        
                        // Sprawdźmy, czy meta jest tablicą, czy stringiem JSON
                        $meta = [];
                        if (is_array($record->meta)) {
                            $meta = $record->meta;
                        } elseif (is_string($record->meta)) {
                            $meta = json_decode($record->meta, true) ?: [];
                        }
                        
                        $sumaProcentowa = $meta['suma_procentowa'] ?? 0;
                        
                        // Określ kolor i informację na podstawie wartości
                        $kolorHex = '#10B981'; // zielony
                        $informacja = '';
                        
                        if ($sumaProcentowa < 99.5) {
                            $kolorHex = '#FBBF24'; // żółty
                            $informacja = ' (za mało - składniki stanowią mniej niż 100% produktu)';
                        } elseif ($sumaProcentowa > 100.5) {
                            $kolorHex = '#EF4444'; // czerwony
                            $informacja = ' (za dużo - składniki stanowią więcej niż 100% produktu)';
                        }
                        
                        // Bezpośrednie renderowanie HTML ze stylami inline
                        $randomId = uniqid();
                        return new \Illuminate\Support\HtmlString(
                            '<span id="suma-procentowa-' . $randomId . '" style="color: ' . $kolorHex . '; font-weight: 500;">' . 
                            number_format($sumaProcentowa, 2) . '%' . 
                            $informacja . 
                            '</span>'
                        );
                    })
                    ->extraAttributes(['class' => 'font-bold'])
                    ->columnSpanFull(),
            ]);
    }
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nazwa')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('kod')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('koszt_calkowity')
                    ->label('Koszt całkowity')
                    ->money('pln')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->tooltip('Całkowity koszt produkcji produktu'),
                Tables\Columns\TextColumn::make('surowce_count')
                    ->label('Ilość surowców')
                    ->counts('surowce')
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
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Edytuj')
                    ->icon('heroicon-o-pencil'),
                // Tables\Actions\DeleteAction::make()
                //     ->label('Usuń')
                //     ->icon('heroicon-o-trash')
                //     ->requiresConfirmation()
                //     ->modalHeading('Usuń recepturę')
                //     ->modalDescription('Czy na pewno chcesz usunąć ten recepturę? Ta akcja jest nieodwracalna.')
                //     ->modalSubmitActionLabel('Usuń')
                //     ->modalCancelActionLabel('Anuluj'),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\SurowceRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRecepturas::route('/'),
            'create' => Pages\CreateReceptura::route('/create'),
            'edit' => Pages\EditReceptura::route('/{record}/edit'),
        ];
    }
}