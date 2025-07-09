<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BaseLinkerProductResource\Pages;
use App\Models\Produkt;
use App\Services\BaseLinkerService;
use App\Services\ProductUpdateService;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Exception;

class BaseLinkerProductResource extends Resource
{
    protected static ?string $model = Produkt::class;
    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    protected static ?string $navigationGroup = 'Sprzedaż';
    protected static ?string $navigationLabel = 'Produkty';
    protected static ?string $modelLabel = 'Produkt';
    protected static ?string $pluralModelLabel = 'Produkty BaseLinker';
    protected static ?int $navigationSort = 1;
    protected static ?string $recordTitleAttribute = 'nazwa';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // Główne dane produktu
                Tables\Columns\TextColumn::make('nazwa')
                    ->label('Nazwa produktu')
                    ->searchable(['nazwa', 'kod'])
                    ->sortable()
                    ->limit(60)
                    ->weight('medium')
                    ->wrap()
                    ->tooltip(fn ($record) => $record->nazwa),

                Tables\Columns\TextColumn::make('kod')
                    ->label('SKU')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->badge()
                    ->color('blue')
                    ->tooltip('Kliknij aby skopiować'),

                Tables\Columns\TextColumn::make('stan_magazynowy')
                    ->label('Ilość lokalnie')
                    ->sortable()
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        $state <= 0 => 'danger',
                        $state <= 5 => 'warning',
                        $state <= 20 => 'info',
                        default => 'success',
                    })
                    ->tooltip('Stan magazynowy w systemie lokalnym'),
                Tables\Columns\TextColumn::make('cena_sprzedazy')
                    ->label('Cena lokalna')
                    ->money('PLN')
                    ->sortable()
                    ->weight('bold')
                    ->color('info')
                    ->tooltip('Cena w systemie lokalnym'),
                Tables\Columns\TextColumn::make('baselinker_stock')
                    ->label('Ilość BL')
                    ->sortable()
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        $state === null => 'gray',
                        $state <= 0 => 'danger',
                        $state <= 5 => 'warning',
                        $state <= 20 => 'info',
                        default => 'success',
                    })
                    ->placeholder('—')
                    ->tooltip('Stan magazynowy w BaseLinker')
                    ->action(
                        Tables\Actions\Action::make('edit_bl_quantity')
                            ->modalHeading('Edycja ilości BaseLinker')
                            ->modalDescription(fn ($record) => "Produkt: {$record->nazwa}")
                            ->modalWidth('lg')
                            ->form([
                                Forms\Components\Section::make('Szybkie akcje')
                                    ->schema([
                                        Forms\Components\Actions::make([
                                            Forms\Components\Actions\Action::make('zero')
                                                ->label('Wyzeruj (0)')
                                                ->color('danger')
                                                ->action(fn ($set) => $set('quantity', 0)),
                                            Forms\Components\Actions\Action::make('dec_100')
                                                ->label('-100')
                                                ->color('danger')
                                                ->action(fn ($set, $get) => $set('quantity', max(0, (int)$get('quantity') - 100))),
                                            Forms\Components\Actions\Action::make('dec_10')
                                                ->label('-10')
                                                ->color('warning')
                                                ->action(fn ($set, $get) => $set('quantity', max(0, (int)$get('quantity') - 10))),
                                            Forms\Components\Actions\Action::make('dec_1')
                                                ->label('-1')
                                                ->color('warning')
                                                ->action(fn ($set, $get) => $set('quantity', max(0, (int)$get('quantity') - 1))),
                                        ])->columnSpan(1),
                                        Forms\Components\Actions::make([
                                            Forms\Components\Actions\Action::make('inc_1')
                                                ->label('+1')
                                                ->color('success')
                                                ->action(fn ($set, $get) => $set('quantity', (int)$get('quantity') + 1)),
                                            Forms\Components\Actions\Action::make('inc_10')
                                                ->label('+10')
                                                ->color('success')
                                                ->action(fn ($set, $get) => $set('quantity', (int)$get('quantity') + 10)),
                                            Forms\Components\Actions\Action::make('inc_100')
                                                ->label('+100')
                                                ->color('success')
                                                ->action(fn ($set, $get) => $set('quantity', (int)$get('quantity') + 100)),
                                            Forms\Components\Actions\Action::make('round_100')
                                                ->label('Zaokrąglij do 100')
                                                ->color('info')
                                                ->action(fn ($set, $get) => $set('quantity', ceil((int)$get('quantity') / 100) * 100)),
                                        ])->columnSpan(1),
                                    ])->columns(2),
                                
                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\TextInput::make('quantity')
                                        ->label('Nowa ilość BL')
                                        ->numeric()
                                        ->integer()
                                        ->minValue(0)
                                        ->maxValue(999999)
                                        ->suffix('szt.')
                                        ->required()
                                        ->default(fn ($record) => $record->baselinker_stock ?? 0)
                                        ->live(),
                                    
                                    Forms\Components\Placeholder::make('current_stock')
                                        ->label('Obecna ilość BL')
                                        ->content(fn ($record) => ($record->baselinker_stock ?? 0) . ' szt.'),
                                ]),
                                
                                Forms\Components\Select::make('reason_template')
                                    ->label('Szablon powodu')
                                    ->options([
                                        'Inwentaryzacja' => 'Inwentaryzacja',
                                        'Dostawa towaru' => 'Dostawa towaru',
                                        'Sprzedaż bezpośrednia' => 'Sprzedaż bezpośrednia',
                                        'Korekta stanu' => 'Korekta stanu',
                                        'Zwrot towaru' => 'Zwrot towaru',
                                        'Uszkodzenie towaru' => 'Uszkodzenie towaru',
                                    ])
                                    ->live()
                                    ->afterStateUpdated(fn ($state, $set) => $set('reason', $state)),
                                
                                Forms\Components\TextInput::make('reason')
                                    ->label('Powód zmiany')
                                    ->maxLength(255)
                                    ->placeholder('Opisz powód zmiany stanu'),
                            ])
                            ->action(function (array $data, $record) {
                                $result = app(ProductUpdateService::class)->updateBaseLinkerQuantity(
                                    $record,
                                    $data['quantity'],
                                    $data['reason'] ?? 'Edycja z tabeli BaseLinker'
                                );
                                
                                Notification::make()
                                    ->title($result['success'] ? 'Ilość BL zaktualizowana' : 'Błąd')
                                    ->body($result['message'])
                                    ->color($result['success'] ? 'success' : 'danger')
                                    ->send();
                            })
                            ->modalSubmitActionLabel('Zaktualizuj')
                            ->modalCancelActionLabel('Anuluj')
                    ),
                Tables\Columns\TextColumn::make('baselinker_price')
                    ->label('Cena BL')
                    ->money('PLN')
                    ->sortable()
                    ->weight('bold')
                    ->color('success')
                    ->placeholder('—')
                    ->tooltip('Cena w BaseLinker')
                    ->action(
                        Tables\Actions\Action::make('edit_bl_price')
                            ->modalHeading('Edycja ceny BaseLinker')
                            ->modalDescription(fn ($record) => "Produkt: {$record->nazwa}")
                            ->modalWidth('lg')
                            ->form([
                                Forms\Components\Section::make('Szybkie akcje')
                                    ->schema([
                                        Forms\Components\Actions::make([
                                            Forms\Components\Actions\Action::make('dec_50')
                                                ->label('-50 zł')
                                                ->color('danger')
                                                ->action(fn ($set, $get) => $set('price', max(0, (float)$get('price') - 50))),
                                            Forms\Components\Actions\Action::make('dec_10')
                                                ->label('-10 zł')
                                                ->color('warning')
                                                ->action(fn ($set, $get) => $set('price', max(0, (float)$get('price') - 10))),
                                            Forms\Components\Actions\Action::make('dec_1')
                                                ->label('-1 zł')
                                                ->color('warning')
                                                ->action(fn ($set, $get) => $set('price', max(0, (float)$get('price') - 1))),
                                            Forms\Components\Actions\Action::make('dec_01')
                                                ->label('-0.10 zł')
                                                ->color('warning')
                                                ->action(fn ($set, $get) => $set('price', max(0, round((float)$get('price') - 0.1, 2)))),
                                        ])->columnSpan(1),
                                        Forms\Components\Actions::make([
                                            Forms\Components\Actions\Action::make('inc_01')
                                                ->label('+0.10 zł')
                                                ->color('success')
                                                ->action(fn ($set, $get) => $set('price', round((float)$get('price') + 0.1, 2))),
                                            Forms\Components\Actions\Action::make('inc_1')
                                                ->label('+1 zł')
                                                ->color('success')
                                                ->action(fn ($set, $get) => $set('price', (float)$get('price') + 1)),
                                            Forms\Components\Actions\Action::make('inc_10')
                                                ->label('+10 zł')
                                                ->color('success')
                                                ->action(fn ($set, $get) => $set('price', (float)$get('price') + 10)),
                                            Forms\Components\Actions\Action::make('inc_50')
                                                ->label('+50 zł')
                                                ->color('success')
                                                ->action(fn ($set, $get) => $set('price', (float)$get('price') + 50)),
                                        ])->columnSpan(1),
                                    ])->columns(2),
                                
                                Forms\Components\Section::make('Kalkulacje procentowe')
                                    ->schema([
                                        Forms\Components\Actions::make([
                                            Forms\Components\Actions\Action::make('minus_20_percent')
                                                ->label('-20%')
                                                ->color('danger')
                                                ->action(fn ($set, $get) => $set('price', round((float)$get('price') * 0.8, 2))),
                                            Forms\Components\Actions\Action::make('minus_10_percent')
                                                ->label('-10%')
                                                ->color('warning')
                                                ->action(fn ($set, $get) => $set('price', round((float)$get('price') * 0.9, 2))),
                                            Forms\Components\Actions\Action::make('plus_10_percent')
                                                ->label('+10%')
                                                ->color('success')
                                                ->action(fn ($set, $get) => $set('price', round((float)$get('price') * 1.1, 2))),
                                            Forms\Components\Actions\Action::make('plus_20_percent')
                                                ->label('+20%')
                                                ->color('success')
                                                ->action(fn ($set, $get) => $set('price', round((float)$get('price') * 1.2, 2))),
                                        ]),
                                    ]),
                                
                                Forms\Components\Section::make('Kopiuj z lokalnej ceny')
                                    ->schema([
                                        Forms\Components\Actions::make([
                                            Forms\Components\Actions\Action::make('copy_local_price')
                                                ->label('Kopiuj z lokalnej ceny')
                                                ->color('info')
                                                ->action(fn ($set, $record) => $set('price', $record->cena_sprzedazy)),
                                        ]),
                                    ]),
                                
                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\TextInput::make('price')
                                        ->label('Nowa cena BL')
                                        ->numeric()
                                        ->step(0.01)
                                        ->minValue(0)
                                        ->maxValue(999999.99)
                                        ->suffix('PLN')
                                        ->required()
                                        ->default(fn ($record) => $record->baselinker_price ?? $record->cena_sprzedazy)
                                        ->live(),
                                    
                                    Forms\Components\Placeholder::make('current_price')
                                        ->label('Obecna cena BL')
                                        ->content(fn ($record) => number_format($record->baselinker_price ?? 0, 2) . ' PLN'),
                                ]),
                                
                                Forms\Components\Select::make('reason_template')
                                    ->label('Szablon powodu')
                                    ->options([
                                        'Aktualizacja cennika' => 'Aktualizacja cennika',
                                        'Promocja' => 'Promocja',
                                        'Wzrost kosztów' => 'Wzrost kosztów',
                                        'Korekta marży' => 'Korekta marży',
                                        'Dostosowanie do rynku' => 'Dostosowanie do rynku',
                                        'Błąd w cenie' => 'Błąd w cenie',
                                    ])
                                    ->live()
                                    ->afterStateUpdated(fn ($state, $set) => $set('reason', $state)),
                                
                                Forms\Components\TextInput::make('reason')
                                    ->label('Powód zmiany')
                                    ->maxLength(255)
                                    ->placeholder('Opisz powód zmiany ceny'),
                            ])
                            ->action(function (array $data, $record) {
                                $result = app(ProductUpdateService::class)->updateBaseLinkerPrice(
                                    $record,
                                    $data['price'],
                                    $data['reason'] ?? 'Edycja z tabeli BaseLinker'
                                );
                                
                                Notification::make()
                                    ->title($result['success'] ? 'Cena BL zaktualizowana' : 'Błąd')
                                    ->body($result['message'])
                                    ->color($result['success'] ? 'success' : 'danger')
                                    ->send();
                            })
                            ->modalSubmitActionLabel('Zaktualizuj')
                            ->modalCancelActionLabel('Anuluj')
                    ),

                Tables\Columns\ViewColumn::make('ean')
                    ->label('EAN')
                    ->view('filament.columns.barcode')
                    ->toggleable(),

                // Status i informacje BaseLinker
                Tables\Columns\IconColumn::make('bl_ready')
                    ->label('Status')
                    ->getStateUsing(function ($record) {
                        return app(BaseLinkerService::class)->isProductReadyForExport($record);
                    })
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->tooltip(fn ($record) => app(BaseLinkerService::class)->isProductReadyForExport($record) 
                        ? 'Gotowy do wysłania' : 'Brakuje danych'),

                Tables\Columns\TextColumn::make('baselinker_id')
                    ->label('ID BL')
                    ->placeholder('—')
                    ->badge()
                    ->color(fn ($state) => $state ? 'success' : 'gray')
                    ->copyable()
                    ->tooltip(fn ($state) => $state ? 'Kliknij aby skopiować ID' : 'Nie wysłano do BaseLinker'),

                Tables\Columns\IconColumn::make('data_diff')
                    ->label('Różnice')
                    ->getStateUsing(function ($record) {
                        if (!$record->baselinker_id) return null;
                        
                        $priceDiff = $record->baselinker_price && $record->cena_sprzedazy != $record->baselinker_price;
                        $stockDiff = $record->baselinker_stock !== null && $record->stan_magazynowy != $record->baselinker_stock;
                        
                        return $priceDiff || $stockDiff;
                    })
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('warning')
                    ->falseColor('success')
                    ->tooltip(function ($record) {
                        if (!$record->baselinker_id) return 'Nie ma w BaseLinker';
                        
                        $differences = [];
                        if ($record->baselinker_price && $record->cena_sprzedazy != $record->baselinker_price) {
                            $differences[] = 'Cena: ' . number_format($record->cena_sprzedazy, 2) . ' PLN (lokalna) vs ' . number_format($record->baselinker_price, 2) . ' PLN (BL)';
                        }
                        if ($record->baselinker_stock !== null && $record->stan_magazynowy != $record->baselinker_stock) {
                            $differences[] = 'Stan: ' . $record->stan_magazynowy . ' (lokalny) vs ' . $record->baselinker_stock . ' (BL)';
                        }
                        
                        return empty($differences) ? 'Dane zgodne' : implode(', ', $differences);
                    }),

                Tables\Columns\IconColumn::make('sync_with_baselinker')
                    ->label('Sync')
                    ->boolean()
                    ->trueIcon('heroicon-o-arrow-path')
                    ->falseIcon('heroicon-o-pause')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->tooltip(fn ($state) => $state ? 'Synchronizacja włączona' : 'Synchronizacja wyłączona'),

                // Kolumny ukryte domyślnie
                Tables\Columns\TextColumn::make('last_baselinker_sync')
                    ->label('Ostatnia sync')
                    ->dateTime('d.m H:i')
                    ->placeholder('Nigdy')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('koszt_calkowity')
                    ->label('Koszt')
                    ->money('PLN')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('receptura.nazwa')
                    ->label('Receptura')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Utworzony')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // Filtry stanu produktu
                Tables\Filters\SelectFilter::make('stock_status')
                    ->label('Stan magazynowy')
                    ->options([
                        'high' => 'Wysoki (>20)',
                        'medium' => 'Średni (6-20)',
                        'low' => 'Niski (1-5)',
                        'empty' => 'Pusty (0)',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'high' => $query->where('stan_magazynowy', '>', 20),
                            'medium' => $query->whereBetween('stan_magazynowy', [6, 20]),
                            'low' => $query->whereBetween('stan_magazynowy', [1, 5]),
                            'empty' => $query->where('stan_magazynowy', '<=', 0),
                            default => $query,
                        };
                    }),

                // Filtry BaseLinker
                Tables\Filters\Filter::make('ready_for_bl')
                    ->label('Gotowe do BaseLinker')
                    ->query(function (Builder $query): Builder {
                        return app(BaseLinkerService::class)->scopeReadyForExport($query);
                    })
                    ->toggle(),

                Tables\Filters\Filter::make('in_baselinker')
                    ->label('W BaseLinker')
                    ->query(fn (Builder $query): Builder => 
                        $query->whereNotNull('baselinker_id'))
                    ->toggle(),

                Tables\Filters\Filter::make('sync_enabled')
                    ->label('Synchronizacja włączona')
                    ->query(fn (Builder $query): Builder => 
                        $query->where('sync_with_baselinker', true))
                    ->toggle(),

                Tables\Filters\Filter::make('data_mismatch')
                    ->label('Różnice w danych')
                    ->query(fn (Builder $query): Builder => 
                        $query->whereNotNull('baselinker_id')
                              ->where(function ($query) {
                                  $query->whereRaw('stan_magazynowy != baselinker_stock')
                                        ->orWhereRaw('cena_sprzedazy != baselinker_price');
                              }))
                    ->toggle(),

                // Filtry cenowe
                Tables\Filters\Filter::make('price_range')
                    ->form([
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\TextInput::make('price_from')
                                ->label('Cena od')
                                ->numeric()
                                ->suffix('PLN'),
                            Forms\Components\TextInput::make('price_to')
                                ->label('Cena do')
                                ->numeric()
                                ->suffix('PLN'),
                        ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['price_from'], 
                                fn (Builder $query, $price): Builder => 
                                    $query->where('baselinker_price', '>=', $price))
                            ->when($data['price_to'], 
                                fn (Builder $query, $price): Builder => 
                                    $query->where('baselinker_price', '<=', $price));
                    }),

                // Filtry relacji
                Tables\Filters\SelectFilter::make('receptura')
                    ->relationship('receptura', 'nazwa')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                // Grupa akcji BaseLinker
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('send_to_baselinker')
                        ->label('Wyślij do BL')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('success')
                        ->visible(fn ($record) => app(BaseLinkerService::class)->canSendToBaseLinker($record))
                        ->requiresConfirmation()
                        ->modalHeading('Wysłać do BaseLinker?')
                        ->modalDescription(function ($record) {
                            $blPrice = $record->baselinker_price ?? $record->cena_sprzedazy;
                            $blStock = $record->baselinker_stock ?? $record->stan_magazynowy;
                            return "Produkt: {$record->nazwa}\nSKU: {$record->kod}\nCena BL: " . number_format($blPrice, 2) . " PLN\nIlość BL: {$blStock} szt.\n\n" . 
                                   ($record->baselinker_price === null ? "⚠️ Brak baselinker_price - użyje ceny lokalnej\n" : "") .
                                   ($record->baselinker_stock === null ? "⚠️ Brak baselinker_stock - użyje stanu lokalnego" : "");
                        })
                        ->action(function ($record) {
                            $result = app(BaseLinkerService::class)->sendProductToBaseLinker($record);
                            
                            Notification::make()
                                ->title($result['success'] ? 'Wysłano do BaseLinker' : 'Błąd wysyłania')
                                ->body($result['message'])
                                ->color($result['success'] ? 'success' : 'danger')
                                ->send();
                        }),

                    Tables\Actions\Action::make('sync_from_bl')
                        ->label('Pobierz z BL')
                        ->icon('heroicon-o-arrow-down-circle')
                        ->color('info')
                        ->visible(fn ($record) => $record->baselinker_id)
                        ->action(function ($record) {
                            $result = app(BaseLinkerService::class)->syncFromBaseLinker($record);
                            
                            Notification::make()
                                ->title($result['success'] ? 'Pobrano z BaseLinker' : 'Błąd pobierania')
                                ->body($result['message'])
                                ->color($result['success'] ? 'success' : 'danger')
                                ->send();
                        }),

                    Tables\Actions\Action::make('push_to_bl')
                        ->label('Wyślij do BL')
                        ->icon('heroicon-o-arrow-up-circle')
                        ->color('warning')
                        ->visible(fn ($record) => $record->baselinker_id && $record->sync_with_baselinker)
                        ->requiresConfirmation()
                        ->modalHeading('Wysłać dane do BaseLinker?')
                        ->modalDescription(function ($record) {
                            $blPrice = $record->baselinker_price ?? $record->cena_sprzedazy;
                            $blStock = $record->baselinker_stock ?? $record->stan_magazynowy;
                            return "Wyślę dane:\nCena BL: " . number_format($blPrice, 2) . " PLN\nIlość BL: {$blStock} szt.\n\n" . 
                                   ($record->baselinker_price === null ? "⚠️ Brak baselinker_price - użyje ceny lokalnej\n" : "") .
                                   ($record->baselinker_stock === null ? "⚠️ Brak baselinker_stock - użyje stanu lokalnego" : "");
                        })
                        ->action(function ($record) {
                            $result = app(BaseLinkerService::class)->pushToBaseLinker($record);
                            
                            Notification::make()
                                ->title($result['success'] ? 'Zaktualizowano w BL' : 'Błąd aktualizacji')
                                ->body($result['message'])
                                ->color($result['success'] ? 'success' : 'danger')
                                ->send();
                        }),
                ])
                ->label('BaseLinker')
                ->icon('heroicon-o-cube')
                ->size('sm')
                ->color('primary')
                ->button(),

                // Grupa akcji zarządzania
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('toggle_sync')
                        ->label(fn ($record) => $record->sync_with_baselinker ? 'Wyłącz sync' : 'Włącz sync')
                        ->icon(fn ($record) => $record->sync_with_baselinker ? 'heroicon-o-pause' : 'heroicon-o-play')
                        ->color(fn ($record) => $record->sync_with_baselinker ? 'danger' : 'success')
                        ->action(function ($record) {
                            $record->update(['sync_with_baselinker' => !$record->sync_with_baselinker]);
                            
                            Notification::make()
                                ->title('Synchronizacja ' . ($record->sync_with_baselinker ? 'włączona' : 'wyłączona'))
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\Action::make('copy_local_to_bl')
                        ->label('Kopiuj dane lokalne → BL')
                        ->icon('heroicon-o-arrow-right')
                        ->color('info')
                        ->action(function ($record) {
                            $record->update([
                                'baselinker_price' => $record->cena_sprzedazy,
                                'baselinker_stock' => $record->stan_magazynowy,
                            ]);
                            
                            Notification::make()
                                ->title('Skopiowano dane lokalne do BaseLinker')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\Action::make('view_bl_data')
                        ->label('Podgląd JSON')
                        ->icon('heroicon-o-code-bracket')
                        ->color('gray')
                        ->modalContent(function ($record) {
                            $productData = app(BaseLinkerService::class)->prepareProductData($record);
                            
                            return view('filament.modals.baselinker-data-preview', [
                                'product' => $record,
                                'blData' => $productData,
                                'jsonData' => json_encode($productData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                                'isInventory' => true
                            ]);
                        }),

                    Tables\Actions\Action::make('delete_from_bl')
                        ->label('Usuń z BL')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->visible(fn ($record) => $record->baselinker_id)
                        ->requiresConfirmation()
                        ->modalHeading('Usunąć z BaseLinker?')
                        ->modalDescription('Produkt zostanie usunięty tylko z BaseLinker.')
                        ->action(function ($record) {
                            try {
                                app(BaseLinkerService::class)->deleteInventoryProduct($record->baselinker_id);
                                
                                $record->update([
                                    'baselinker_id' => null,
                                    'baselinker_price' => null,
                                    'baselinker_stock' => null,
                                    'sync_with_baselinker' => false,
                                    'last_baselinker_sync' => null
                                ]);
                                
                                Notification::make()
                                    ->title('Usunięto z BaseLinker')
                                    ->success()
                                    ->send();
                                
                            } catch (Exception $e) {
                                Notification::make()
                                    ->title('Błąd usuwania')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),
                ])
                ->label('Opcje')
                ->icon('heroicon-o-cog-6-tooth')
                ->size('sm')
                ->color('gray')
                ->button(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Masowe edycje
                    Tables\Actions\BulkAction::make('bulk_update_bl_prices')
                        ->label('Masowa edycja cen BL')
                        ->icon('heroicon-o-currency-dollar')
                        ->color('warning')
                        ->form([
                            Forms\Components\Select::make('operation')
                                ->label('Operacja')
                                ->options([
                                    'set' => 'Ustaw cenę na',
                                    'copy_local' => 'Kopiuj z lokalnej ceny',
                                    'increase' => 'Zwiększ o kwotę',
                                    'decrease' => 'Zmniejsz o kwotę', 
                                    'multiply' => 'Pomnóż przez',
                                    'percentage_increase' => 'Zwiększ o procent',
                                    'percentage_decrease' => 'Zmniejsz o procent',
                                ])
                                ->required()
                                ->live(),
                            
                            Forms\Components\TextInput::make('value')
                                ->label('Wartość')
                                ->numeric()
                                ->step(0.01)
                                ->required()
                                ->suffix(function (Forms\Get $get) {
                                    return match($get('operation')) {
                                        'percentage_increase', 'percentage_decrease' => '%',
                                        'multiply' => 'x',
                                        default => 'PLN'
                                    };
                                })
                                ->hidden(fn (Forms\Get $get) => $get('operation') === 'copy_local'),
                            
                            Forms\Components\TextInput::make('reason')
                                ->label('Powód zmiany')
                                ->placeholder('np. Aktualizacja cennika BL Q1 2024')
                                ->maxLength(255),
                        ])
                        ->action(function (array $data, $records) {
                            $updates = [];
                            
                            foreach ($records as $record) {
                                $currentPrice = $record->baselinker_price ?? $record->cena_sprzedazy;
                                
                                $newPrice = match($data['operation']) {
                                    'set' => $data['value'],
                                    'copy_local' => $record->cena_sprzedazy,
                                    'increase' => $currentPrice + $data['value'],
                                    'decrease' => max(0, $currentPrice - $data['value']),
                                    'multiply' => $currentPrice * $data['value'],
                                    'percentage_increase' => $currentPrice * (1 + $data['value'] / 100),
                                    'percentage_decrease' => $currentPrice * (1 - $data['value'] / 100),
                                    default => $currentPrice
                                };
                                
                                $updates[] = [
                                    'product_id' => $record->id,
                                    'price' => max(0, round($newPrice, 2))
                                ];
                            }
                            
                            $result = app(ProductUpdateService::class)->bulkUpdateBaseLinkerPrices($updates, $data['reason']);
                            
                            Notification::make()
                                ->title($result['success'] ? 'Ceny BL zaktualizowane' : 'Błąd aktualizacji')
                                ->body($result['message'])
                                ->color($result['success'] ? 'success' : 'danger')
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('bulk_update_bl_quantities')
                        ->label('Masowa edycja ilości BL')
                        ->icon('heroicon-o-cube')
                        ->color('info')
                        ->form([
                            Forms\Components\Select::make('operation')
                                ->label('Operacja')
                                ->options([
                                    'set' => 'Ustaw ilość na',
                                    'copy_local' => 'Kopiuj z lokalnego stanu',
                                    'increase' => 'Zwiększ o',
                                    'decrease' => 'Zmniejsz o',
                                    'multiply' => 'Pomnóż przez',
                                    'zero' => 'Wyzeruj wszystkie',
                                ])
                                ->required()
                                ->live(),
                            
                            Forms\Components\TextInput::make('value')
                                ->label('Wartość')
                                ->numeric()
                                ->integer()
                                ->required()
                                ->suffix('szt.')
                                ->hidden(fn (Forms\Get $get) => in_array($get('operation'), ['zero', 'copy_local'])),
                            
                            Forms\Components\TextInput::make('reason')
                                ->label('Powód zmiany')
                                ->placeholder('np. Inwentaryzacja BL październik 2024')
                                ->maxLength(255),
                        ])
                        ->action(function (array $data, $records) {
                            $updates = [];
                            
                            foreach ($records as $record) {
                                $currentQuantity = $record->baselinker_stock ?? $record->stan_magazynowy;
                                
                                $newQuantity = match($data['operation']) {
                                    'set' => $data['value'],
                                    'copy_local' => $record->stan_magazynowy,
                                    'increase' => $currentQuantity + $data['value'],
                                    'decrease' => max(0, $currentQuantity - $data['value']),
                                    'multiply' => $currentQuantity * $data['value'],
                                    'zero' => 0,
                                    default => $currentQuantity
                                };
                                
                                $updates[] = [
                                    'product_id' => $record->id,
                                    'quantity' => max(0, (int)$newQuantity)
                                ];
                            }
                            
                            $result = app(ProductUpdateService::class)->bulkUpdateBaseLinkerQuantities($updates, $data['reason']);
                            
                            Notification::make()
                                ->title($result['success'] ? 'Ilości BL zaktualizowane' : 'Błąd aktualizacji')
                                ->body($result['message'])
                                ->color($result['success'] ? 'success' : 'danger')
                                ->send();
                        }),

                    // BaseLinker operacje
                    Tables\Actions\BulkAction::make('bulk_send_to_bl')
                        ->label('Wyślij do BaseLinker')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Wysłać produkty do BaseLinker?')
                        ->modalDescription('Tylko produkty z kompletnymi danymi zostaną wysłane.')
                        ->action(function ($records) {
                            $result = app(BaseLinkerService::class)->bulkSendProductsToBaseLinker($records);
                            
                            Notification::make()
                                ->title('Wysyłanie zakończone')
                                ->body("Wysłano: {$result['success_count']}, Błędy: {$result['error_count']}")
                                ->color($result['error_count'] > 0 ? 'warning' : 'success')
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('bulk_sync_from_bl')
                        ->label('Pobierz z BL')
                        ->icon('heroicon-o-arrow-down-circle')
                        ->color('info')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $successCount = 0;
                            $errorCount = 0;
                            
                            foreach ($records as $record) {
                                if (!$record->baselinker_id) continue;
                                
                                $result = app(BaseLinkerService::class)->syncFromBaseLinker($record);
                                $result['success'] ? $successCount++ : $errorCount++;
                                
                                usleep(200000); // 0.2s delay
                            }
                            
                            Notification::make()
                                ->title('Pobieranie zakończone')
                                ->body("Pobrano: {$successCount}, Błędy: {$errorCount}")
                                ->color($errorCount > 0 ? 'warning' : 'success')
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('bulk_push_to_bl')
                        ->label('Wyślij do BL')
                        ->icon('heroicon-o-arrow-up-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Wysłać dane do BaseLinker?')
                        ->modalDescription('Wyślę dane baselinker_price i baselinker_stock do BaseLinker.')
                        ->action(function ($records) {
                            $successCount = 0;
                            $errorCount = 0;
                            
                            foreach ($records as $record) {
                                if (!$record->baselinker_id || !$record->sync_with_baselinker) continue;
                                
                                $result = app(BaseLinkerService::class)->pushToBaseLinker($record);
                                $result['success'] ? $successCount++ : $errorCount++;
                                
                                usleep(200000); // 0.2s delay
                            }
                            
                            Notification::make()
                                ->title('Wysyłanie zakończone')
                                ->body("Wysłano: {$successCount}, Błędy: {$errorCount}")
                                ->color($errorCount > 0 ? 'warning' : 'success')
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('bulk_copy_local_to_bl')
                        ->label('Kopiuj lokalne → BL')
                        ->icon('heroicon-o-arrow-right')
                        ->color('info')
                        ->action(function ($records) {
                            $count = 0;
                            foreach ($records as $record) {
                                $record->update([
                                    'baselinker_price' => $record->cena_sprzedazy,
                                    'baselinker_stock' => $record->stan_magazynowy,
                                ]);
                                $count++;
                            }
                            
                            Notification::make()
                                ->title("Skopiowano dane lokalne do BL dla {$count} produktów")
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('bulk_enable_sync')
                        ->label('Włącz synchronizację')
                        ->icon('heroicon-o-play')
                        ->color('success')
                        ->action(function ($records) {
                            $count = 0;
                            foreach ($records as $record) {
                                if ($record->baselinker_id) {
                                    $record->update(['sync_with_baselinker' => true]);
                                    $count++;
                                }
                            }
                            
                            Notification::make()
                                ->title("Włączono synchronizację dla {$count} produktów")
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('bulk_disable_sync')
                        ->label('Wyłącz synchronizację')
                        ->icon('heroicon-o-pause')
                        ->color('danger')
                        ->action(function ($records) {
                            $count = $records->count();
                            $records->each(fn ($record) => $record->update(['sync_with_baselinker' => false]));
                            
                            Notification::make()
                                ->title("Wyłączono synchronizację dla {$count} produktów")
                                ->warning()
                                ->send();
                        }),
                ]),
            ]);
            // ->headerActions([
            //     Tables\Actions\Action::make('test_connection')
            //         ->label('Test połączenia')
            //         ->icon('heroicon-o-signal')
            //         ->color('info')
            //         ->action(function () {
            //             $service = app(BaseLinkerService::class);
                        
            //             if (!$service->isConfigured()) {
            //                 Notification::make()
            //                     ->title('BaseLinker nie skonfigurowany')
            //                     ->body('Ustaw BASELINKER_API_TOKEN w pliku .env')
            //                     ->warning()
            //                     ->send();
            //                 return;
            //             }
                        
            //             $connected = $service->testConnection();
                        
            //             Notification::make()
            //                 ->title($connected ? 'Połączenie OK' : 'Błąd połączenia')
            //                 ->body($connected ? 'BaseLinker API odpowiada' : 'Sprawdź konfigurację')
            //                 ->color($connected ? 'success' : 'danger')
            //                 ->send();
            //         }),
                    
            //     Tables\Actions\Action::make('sync_report')
            //         ->label('Raport')
            //         ->icon('heroicon-o-document-chart-bar')
            //         ->color('info')
            //         ->modalContent(function () {
            //             $report = app(BaseLinkerService::class)->generateSyncReport();
                        
            //             return view('filament.modals.sync-report', ['report' => $report]);
            //         })
            //         ->modalHeading('Raport synchronizacji BaseLinker')
            //         ->modalWidth('2xl'),
            // ])

            // ->defaultSort('nazwa')
            // ->striped()
            // ->paginated([25, 50, 100])
            // ->persistSortInSession()
            // ->persistSearchInSession()
            // ->persistFiltersInSession()
            // ->deferLoading()
            // ->poll('30s');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBaseLinkerProducts::route('/'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $service = app(BaseLinkerService::class);
        $readyCount = $service->getReadyForExportCount();
        return $readyCount > 0 ? (string) $readyCount : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }

    public static function getGlobalSearchResultTitle($record): string
    {
        return $record->nazwa;
    }

    public static function getGlobalSearchResultDetails($record): array
    {
        return [
            'SKU' => $record->kod,
            'Cena lokalna' => number_format($record->cena_sprzedazy, 2) . ' PLN',
            'Cena BL' => number_format($record->baselinker_price ?? 0, 2) . ' PLN',
            'Stan lokalny' => $record->stan_magazynowy . ' szt.',
            'Stan BL' => ($record->baselinker_stock ?? 0) . ' szt.',
        ];
    }
}