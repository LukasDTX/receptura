<?php

// app/Filament/Resources/BaseLinkerOrderResource.php
namespace App\Filament\Resources;

use App\Filament\Resources\BaseLinkerOrderResource\Pages;
use App\Models\BaseLinkerOrder;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;
use Carbon\Carbon;

class BaseLinkerOrderResource extends Resource
{
    protected static ?string $model = BaseLinkerOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    
    protected static ?string $navigationGroup = 'Sprzedaż';
    
    protected static ?string $navigationLabel = 'Zamówienia BaseLinker';
    
    protected static ?string $modelLabel = 'Zamówienie BL';
    
    protected static ?string $pluralModelLabel = 'Zamówienia BaseLinker';
    
    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'baselinker_order_id';

    // Wyłącz możliwość tworzenia i edycji
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Pusty formularz - brak edycji
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('baselinker_order_id')
                    ->label('ID BaseLinker')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('external_order_id')
                    ->label('ID zewnętrzne')
                    ->searchable()
                    ->placeholder('Brak')
                    ->toggleable(),

                Tables\Columns\BadgeColumn::make('order_source')
                    ->label('Źródło')
                    ->colors([
                        'primary' => 'personal',
                        'success' => 'shop',
                        'warning' => 'allegro',
                        'info' => 'amazon',
                        'secondary' => 'ebay',
                    ])
                    ->formatStateUsing(function ($state) {
                        return match($state) {
                            'personal' => 'Personal',
                            'shop' => 'Sklep',
                            'allegro' => 'Allegro',
                            'amazon' => 'Amazon',
                            'ebay' => 'eBay',
                            default => ucfirst($state),
                        };
                    }),

                Tables\Columns\IconColumn::make('confirmed')
                    ->label('Potwierdzone')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\TextColumn::make('delivery_fullname')
                    ->label('Odbiorca')
                    ->searchable()
                    ->limit(30)
                    ->placeholder('Brak danych'),

                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('Brak'),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Telefon')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('Brak'),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Wartość')
                    ->getStateUsing(function ($record) {
                        return $record->products->sum(function ($product) {
                            return $product['price_brutto'] * $product['quantity'];
                        });
                    })
                    ->money('PLN')
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('products_count')
                    ->label('Pozycje')
                    ->getStateUsing(fn ($record) => $record->products->count())
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('delivery_method')
                    ->label('Dostawa')
                    ->placeholder('Nie określono')
                    ->toggleable(),

                Tables\Columns\IconColumn::make('payment_done')
                    ->label('Opłacone')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('warning'),

                Tables\Columns\TextColumn::make('delivery_package_nr')
                    ->label('Nr przesyłki')
                    ->searchable()
                    ->copyable()
                    ->placeholder('Brak')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('date_add')
                    ->label('Data dodania')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('date_confirmed')
                    ->label('Data potwierdzenia')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('Nie potwierdzone'),

                Tables\Columns\TextColumn::make('synced_at')
                    ->label('Synchronizacja')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('order_source')
                    ->label('Źródło')
                    ->options([
                        'personal' => 'Personal',
                        'shop' => 'Sklep',
                        'allegro' => 'Allegro',
                        'amazon' => 'Amazon',
                        'ebay' => 'eBay',
                    ]),

                SelectFilter::make('confirmed')
                    ->label('Status potwierdzenia')
                    ->options([
                        '1' => 'Potwierdzone',
                        '0' => 'Niepotwierdzone',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (isset($data['value'])) {
                            return $query->where('confirmed', (bool) $data['value']);
                        }
                        return $query;
                    }),

                SelectFilter::make('payment_done')
                    ->label('Status płatności')
                    ->options([
                        '1' => 'Opłacone',
                        '0' => 'Nieopłacone',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (isset($data['value'])) {
                            return $query->where('payment_done', (bool) $data['value']);
                        }
                        return $query;
                    }),

                Filter::make('date_add')
                    ->label('Data dodania')
                    ->form([
                        DatePicker::make('from')
                            ->label('Od'),
                        DatePicker::make('until')
                            ->label('Do'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('date_add', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('date_add', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'] ?? null) {
                            $indicators['from'] = 'Od: ' . Carbon::parse($data['from'])->format('d.m.Y');
                        }
                        if ($data['until'] ?? null) {
                            $indicators['until'] = 'Do: ' . Carbon::parse($data['until'])->format('d.m.Y');
                        }
                        return $indicators;
                    }),

                Filter::make('has_tracking')
                    ->label('Z numerem przesyłki')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('delivery_package_nr')->where('delivery_package_nr', '!=', ''))
                    ->toggle(),

                Filter::make('no_email')
                    ->label('Bez emaila')
                    ->query(fn (Builder $query): Builder => $query->where(function ($q) {
                        $q->whereNull('email')->orWhere('email', '');
                    }))
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Szczegóły'),
                
                Tables\Actions\Action::make('open_baselinker')
                    ->label('Otwórz w BaseLinker')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn ($record) => $record->order_page)
                    ->openUrlInNewTab()
                    ->visible(fn ($record) => !empty($record->order_page)),

                Tables\Actions\Action::make('copy_tracking')
                    ->label('Kopiuj nr przesyłki')
                    ->icon('heroicon-o-clipboard')
                    ->color('gray')
                    ->action(function ($record) {
                        // JavaScript copy będzie dodany w blade
                    })
                    ->visible(fn ($record) => !empty($record->delivery_package_nr)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('export_csv')
                        ->label('Eksportuj CSV')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('gray')
                        ->action(function ($records) {
                            return response()->streamDownload(function () use ($records) {
                                $csv = fopen('php://output', 'w');
                                
                                // Nagłówki CSV
                                fputcsv($csv, [
                                    'ID BaseLinker',
                                    'ID zewnętrzne',
                                    'Źródło',
                                    'Potwierdzone',
                                    'Odbiorca',
                                    'Email',
                                    'Telefon',
                                    'Wartość',
                                    'Metoda dostawy',
                                    'Nr przesyłki',
                                    'Data dodania'
                                ]);
                                
                                // Dane
                                foreach ($records as $record) {
                                    $totalAmount = $record->products->sum(function ($product) {
                                        return $product['price_brutto'] * $product['quantity'];
                                    });
                                    
                                    fputcsv($csv, [
                                        $record->baselinker_order_id,
                                        $record->external_order_id,
                                        $record->order_source,
                                        $record->confirmed ? 'Tak' : 'Nie',
                                        $record->delivery_fullname,
                                        $record->email,
                                        $record->phone,
                                        number_format($totalAmount, 2) . ' PLN',
                                        $record->delivery_method,
                                        $record->delivery_package_nr,
                                        $record->date_add?->format('d.m.Y H:i'),
                                    ]);
                                }
                                
                                fclose($csv);
                            }, 'zamowienia-baselinker-' . now()->format('Y-m-d') . '.csv');
                        }),
                ]),
            ])
            ->defaultSort('date_add', 'desc')
            ->persistSortInSession()
            ->persistSearchInSession()
            ->persistFiltersInSession();
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Podstawowe informacje')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('baselinker_order_id')
                                    ->label('ID BaseLinker')
                                    ->copyable()
                                    ->weight('bold')
                                    ->size('lg'),
                                
                                Infolists\Components\TextEntry::make('external_order_id')
                                    ->label('ID zewnętrzne')
                                    ->copyable()
                                    ->placeholder('Brak'),
                                
                                Infolists\Components\TextEntry::make('order_source')
                                    ->label('Źródło')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'personal' => 'primary',
                                        'shop' => 'success',
                                        'allegro' => 'warning',
                                        'amazon' => 'info',
                                        'ebay' => 'secondary',
                                        default => 'gray',
                                    }),
                            ]),
                        
                        Infolists\Components\Grid::make(4)
                            ->schema([
                                Infolists\Components\IconEntry::make('confirmed')
                                    ->label('Potwierdzone')
                                    ->boolean()
                                    ->trueIcon('heroicon-o-check-circle')
                                    ->falseIcon('heroicon-o-x-circle')
                                    ->trueColor('success')
                                    ->falseColor('danger'),
                                
                                Infolists\Components\IconEntry::make('payment_done')
                                    ->label('Opłacone')
                                    ->boolean()
                                    ->trueIcon('heroicon-o-banknotes')
                                    ->falseIcon('heroicon-o-clock')
                                    ->trueColor('success')
                                    ->falseColor('warning'),
                                
                                Infolists\Components\TextEntry::make('currency')
                                    ->label('Waluta')
                                    ->badge(),
                                
                                Infolists\Components\TextEntry::make('payment_method')
                                    ->label('Metoda płatności')
                                    ->placeholder('Nie określono'),
                            ]),
                    ]),

                Infolists\Components\Section::make('Dane dostawy')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('delivery_fullname')
                                    ->label('Imię i nazwisko')
                                    ->copyable(),
                                
                                Infolists\Components\TextEntry::make('delivery_company')
                                    ->label('Firma')
                                    ->placeholder('Brak'),
                            ]),
                        
                        Infolists\Components\TextEntry::make('delivery_address_full')
                            ->label('Adres dostawy')
                            ->getStateUsing(function ($record): string {
                                $address = collect([
                                    $record->delivery_address,
                                    $record->delivery_postcode . ' ' . $record->delivery_city,
                                    $record->delivery_state,
                                    $record->delivery_country,
                                ])->filter()->implode(', ');
                                
                                return $address ?: 'Brak adresu';
                            })
                            ->copyable(),
                        
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('delivery_method')
                                    ->label('Metoda dostawy')
                                    ->placeholder('Nie określono'),
                                
                                Infolists\Components\TextEntry::make('delivery_price')
                                    ->label('Koszt dostawy')
                                    ->money('PLN'),
                                
                                Infolists\Components\TextEntry::make('delivery_package_nr')
                                    ->label('Numer przesyłki')
                                    ->copyable()
                                    ->placeholder('Brak'),
                            ]),
                    ])
                    ->collapsed(false),

                Infolists\Components\Section::make('Punkt odbioru')
                    ->schema([
                        Infolists\Components\TextEntry::make('delivery_point_name')
                            ->label('Nazwa punktu')
                            ->placeholder('Brak'),
                        
                        Infolists\Components\TextEntry::make('delivery_point_address_full')
                            ->label('Adres punktu')
                            ->getStateUsing(function ($record): string {
                                if (empty($record->delivery_point_address)) {
                                    return 'Brak';
                                }
                                
                                return collect([
                                    $record->delivery_point_address,
                                    $record->delivery_point_postcode . ' ' . $record->delivery_point_city,
                                ])->filter()->implode(', ');
                            }),
                    ])
                    ->visible(fn ($record) => !empty($record->delivery_point_name))
                    ->collapsed(),

                Infolists\Components\Section::make('Dane do faktury')
                    ->schema([
                        Infolists\Components\IconEntry::make('want_invoice')
                            ->label('Chce fakturę')
                            ->boolean()
                            ->trueIcon('heroicon-o-document-text')
                            ->falseIcon('heroicon-o-receipt-percent'),
                        
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('invoice_fullname')
                                    ->label('Imię i nazwisko')
                                    ->placeholder('Brak'),
                                
                                Infolists\Components\TextEntry::make('invoice_company')
                                    ->label('Firma')
                                    ->placeholder('Brak'),
                            ]),
                        
                        Infolists\Components\TextEntry::make('invoice_nip')
                            ->label('NIP')
                            ->copyable()
                            ->placeholder('Brak'),
                        
                        Infolists\Components\TextEntry::make('invoice_address_full')
                            ->label('Adres do faktury')
                            ->getStateUsing(function ($record): string {
                                $address = collect([
                                    $record->invoice_address,
                                    $record->invoice_postcode . ' ' . $record->invoice_city,
                                    $record->invoice_state,
                                    $record->invoice_country,
                                ])->filter()->implode(', ');
                                
                                return $address ?: 'Brak adresu';
                            }),
                    ])
                    ->visible(fn ($record) => $record->want_invoice)
                    ->collapsed(),

                Infolists\Components\Section::make('Kontakt')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('email')
                                    ->label('Email')
                                    ->copyable()
                                    ->placeholder('Brak')
                                    ->url(fn ($state) => $state ? "mailto:{$state}" : null),
                                
                                Infolists\Components\TextEntry::make('phone')
                                    ->label('Telefon')
                                    ->copyable()
                                    ->placeholder('Brak')
                                    ->url(fn ($state) => $state ? "tel:{$state}" : null),
                            ]),
                    ])
                    ->collapsed(),

                Infolists\Components\Section::make('Produkty')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('products')
                            ->label('')
                            ->schema([
                                Infolists\Components\Grid::make(6)
                                    ->schema([
                                        Infolists\Components\TextEntry::make('name')
                                            ->label('Nazwa produktu')
                                            ->weight('medium')
                                            ->columnSpan(2),
                                        
                                        Infolists\Components\TextEntry::make('sku')
                                            ->label('SKU')
                                            ->badge()
                                            ->color('gray'),
                                        
                                        Infolists\Components\TextEntry::make('quantity')
                                            ->label('Ilość')
                                            ->badge()
                                            ->color('primary'),
                                        
                                        Infolists\Components\TextEntry::make('price_brutto')
                                            ->label('Cena')
                                            ->money('PLN'),
                                        
                                        Infolists\Components\TextEntry::make('total')
                                            ->label('Razem')
                                            ->getStateUsing(fn ($record) => $record['price_brutto'] * $record['quantity'])
                                            ->money('PLN')
                                            ->weight('bold'),
                                    ]),
                            ])
                            ->contained(false),
                        
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('products_total')
                                    ->label('Wartość produktów')
                                    ->getStateUsing(function ($record) {
                                        return $record->products->sum(function ($product) {
                                            return $product['price_brutto'] * $product['quantity'];
                                        });
                                    })
                                    ->money('PLN')
                                    ->weight('bold')
                                    ->size('lg'),
                                
                                Infolists\Components\TextEntry::make('products_count')
                                    ->label('Liczba pozycji')
                                    ->getStateUsing(fn ($record) => $record->products->count())
                                    ->badge()
                                    ->color('gray'),
                            ]),
                    ]),

                Infolists\Components\Section::make('Uwagi i dodatkowe informacje')
                    ->schema([
                        Infolists\Components\TextEntry::make('user_comments')
                            ->label('Uwagi klienta')
                            ->placeholder('Brak uwag')
                            ->prose(),
                        
                        Infolists\Components\TextEntry::make('admin_comments')
                            ->label('Uwagi administratora')
                            ->placeholder('Brak uwag')
                            ->prose(),
                        
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('extra_field_1')
                                    ->label('Pole dodatkowe 1')
                                    ->placeholder('Brak'),
                                
                                Infolists\Components\TextEntry::make('extra_field_2')
                                    ->label('Pole dodatkowe 2')
                                    ->placeholder('Brak'),
                            ]),
                    ])
                    ->visible(fn ($record) => !empty($record->user_comments) || !empty($record->admin_comments) || !empty($record->extra_field_1) || !empty($record->extra_field_2))
                    ->collapsed(),

                Infolists\Components\Section::make('Daty i synchronizacja')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('date_add')
                                    ->label('Data dodania')
                                    ->dateTime('d.m.Y H:i'),
                                
                                Infolists\Components\TextEntry::make('date_confirmed')
                                    ->label('Data potwierdzenia')
                                    ->dateTime('d.m.Y H:i')
                                    ->placeholder('Nie potwierdzone'),
                                
                                Infolists\Components\TextEntry::make('synced_at')
                                    ->label('Ostatnia synchronizacja')
                                    ->dateTime('d.m.Y H:i'),
                            ]),
                        
                        Infolists\Components\TextEntry::make('order_page')
                            ->label('Link do zamówienia w BaseLinker')
                            ->url(fn ($state) => $state)
                            ->openUrlInNewTab()
                            ->placeholder('Brak linku'),
                    ])
                    ->collapsed(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBaseLinkerOrders::route('/'),
            'view' => Pages\ViewBaseLinkerOrder::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $newOrdersCount = static::getModel()::where('confirmed', false)
            ->where('date_add', '>', now()->subDays(7))
            ->count();
            
        return $newOrdersCount > 0 ? (string) $newOrdersCount : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }
}