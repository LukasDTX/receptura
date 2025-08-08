<?php

namespace App\Filament\Resources\ZlecenieResource\Pages;

use App\Filament\Resources\ZlecenieResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListZlecenies extends ListRecords
{
    protected static string $resource = ZlecenieResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make()
                ->label('Nowe zlecenie')
                ->icon('heroicon-o-plus')
                            ->color('success') // zielony kolor
            ->size('lg') // większy rozmiar,
        ];
    }

public function getTabs(): array
{
    return [
        'wszystkie' => Tab::make('Wszystkie')
            ->badge(fn () => \App\Models\Zlecenie::count())
            ->badgeColor('gray'),
            
        'nowe' => Tab::make('Nowe')
            ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'nowe'))
            ->badge(fn () => \App\Models\Zlecenie::where('status', 'nowe')->count())
            ->badgeColor('warning'),
            
        'w_realizacji' => Tab::make('W realizacji')
            ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'w_realizacji'))
            ->badge(fn () => \App\Models\Zlecenie::where('status', 'w_realizacji')->count())
            ->badgeColor('info'),
            
        'zrealizowane' => Tab::make('Zrealizowane')
            ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'zrealizowane'))
            ->badge(fn () => \App\Models\Zlecenie::where('status', 'zrealizowane')->count())
            ->badgeColor('success'),
            
        'gotowe_do_wysylki' => Tab::make('Gotowe do wysyłki')
            ->modifyQueryUsing(fn (Builder $query) => 
                $query->where('status', 'zrealizowane')
                      ->whereNotNull('numer_partii')
                      ->whereDoesntHave('partie') // Nie ma jeszcze partii w magazynie
            )
            ->badge(fn () => \App\Models\Zlecenie::where('status', 'zrealizowane')
                                                 ->whereNotNull('numer_partii')
                                                 ->whereDoesntHave('partie')
                                                 ->count())
            ->badgeColor('primary'),
            
        'opoznione' => Tab::make('Opóźnione')
            ->modifyQueryUsing(fn (Builder $query) => 
                $query->where('planowana_data_realizacji', '<', today())
                      ->whereIn('status', ['nowe', 'w_realizacji'])
            )
            ->badge(fn () => \App\Models\Zlecenie::where('planowana_data_realizacji', '<', today())
                                                 ->whereIn('status', ['nowe', 'w_realizacji'])
                                                 ->count())
            ->badgeColor('danger'),
            
        'ten_tydzien' => Tab::make('Ten tydzień')
            ->modifyQueryUsing(fn (Builder $query) => 
                $query->whereBetween('planowana_data_realizacji', [
                    now()->startOfWeek(),
                    now()->endOfWeek()
                ])
            )
            ->badge(fn () => \App\Models\Zlecenie::whereBetween('planowana_data_realizacji', [
                now()->startOfWeek(),
                now()->endOfWeek()
            ])->count())
            ->badgeColor('info'),
    ];
}
}