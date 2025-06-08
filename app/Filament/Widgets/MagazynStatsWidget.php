<?php

namespace App\Filament\Widgets;

use App\Models\StanMagazynu;
use App\Models\Partia;
use App\Enums\StatusPartii;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MagazynStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        // Statystyki magazynu
        $produktyWMagazynie = StanMagazynu::where('typ_towaru', 'produkt')->sum('ilosc_dostepna');
        $surowceWMagazynie = StanMagazynu::where('typ_towaru', 'surowiec')->count();
        $wartoscMagazynu = StanMagazynu::sum('wartosc');
        
        // Partie
        $partieWMagazynie = Partia::where('status', StatusPartii::W_MAGAZYNIE)->count();
        $przeterminowanePartie = Partia::where('status', StatusPartii::W_MAGAZYNIE)
            ->where('data_waznosci', '<', now())
            ->count();
        $wkrotcePrzeterminowane = Partia::where('status', StatusPartii::W_MAGAZYNIE)
            ->whereBetween('data_waznosci', [now(), now()->addDays(30)])
            ->count();
            
        // Niskie stany
        $niskieStany = StanMagazynu::where('ilosc_dostepna', '<', 10)->count();
        
        return [
            Stat::make('Produkty w magazynie', number_format($produktyWMagazynie) . ' szt')
                ->description('Łączna ilość produktów')
                ->descriptionIcon('heroicon-m-cube')
                ->color('success'),
                
            Stat::make('Partie w magazynie', $partieWMagazynie)
                ->description('Aktywne partie produktów')
                ->descriptionIcon('heroicon-m-cube-transparent')
                ->color('info'),
                
            Stat::make('Wartość magazynu', number_format($wartoscMagazynu, 2) . ' PLN')
                ->description('Łączna wartość zapasów')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('primary'),
                
            Stat::make('Pozycje surowców', $surowceWMagazynie)
                ->description('Różne surowce w magazynie')
                ->descriptionIcon('heroicon-m-beaker')
                ->color('gray'),
                
            Stat::make('Niskie stany', $niskieStany)
                ->description('Pozycje z ilością < 10')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($niskieStany > 0 ? 'warning' : 'success'),
                
            Stat::make('Przeterminowane', $przeterminowanePartie)
                ->description('Partie po terminie ważności')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color($przeterminowanePartie > 0 ? 'danger' : 'success'),
                
            Stat::make('Wkrótce przeterminowane', $wkrotcePrzeterminowane)
                ->description('Partie (30 dni)')
                ->descriptionIcon('heroicon-m-clock')
                ->color($wkrotcePrzeterminowane > 0 ? 'warning' : 'success'),
        ];
    }
}