<?php
// app/Filament/Resources/BaseLinkerOrderResource/Pages/ViewBaseLinkerOrder.php
namespace App\Filament\Resources\BaseLinkerOrderResource\Pages;

use App\Filament\Resources\BaseLinkerOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewBaseLinkerOrder extends ViewRecord
{
    protected static string $resource = BaseLinkerOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('open_in_baselinker')
                ->label('Otwórz w BaseLinker')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('primary')
                ->url(fn () => $this->getRecord()->order_page)
                ->openUrlInNewTab()
                ->visible(fn () => !empty($this->getRecord()->order_page)),

            Actions\Action::make('copy_email')
                ->label('Kopiuj email')
                ->icon('heroicon-o-envelope')
                ->color('gray')
                ->action(function () {
                    // JavaScript copy functionality
                })
                ->visible(fn () => !empty($this->getRecord()->email)),

            Actions\Action::make('copy_phone')
                ->label('Kopiuj telefon')
                ->icon('heroicon-o-phone')
                ->color('gray')
                ->action(function () {
                    // JavaScript copy functionality
                })
                ->visible(fn () => !empty($this->getRecord()->phone)),

            Actions\Action::make('track_package')
                ->label('Śledź przesyłkę')
                ->icon('heroicon-o-truck')
                ->color('info')
                ->url(function () {
                    $trackingNumber = $this->getRecord()->delivery_package_nr;
                    // Tutaj można dodać logikę dla różnych kurierów
                    if (str_contains($trackingNumber, 'DPD')) {
                        return "https://tracktrace.dpd.com.pl/parcelDetails?p1={$trackingNumber}";
                    }
                    if (str_contains($trackingNumber, 'INP')) {
                        return "https://inpost.pl/sledzenie-przesylek?number={$trackingNumber}";
                    }
                    // Domyślnie Poczta Polska
                    return "https://emonitoring.poczta-polska.pl/?numer={$trackingNumber}";
                })
                ->openUrlInNewTab()
                ->visible(fn () => !empty($this->getRecord()->delivery_package_nr)),
        ];
    }
}