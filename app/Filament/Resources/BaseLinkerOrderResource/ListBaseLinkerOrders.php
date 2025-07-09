<?php
// app/Filament/Resources/BaseLinkerOrderResource/Pages/ListBaseLinkerOrders.php
namespace App\Filament\Resources\BaseLinkerOrderResource\Pages;

use App\Filament\Resources\BaseLinkerOrderResource;
use App\Services\BaseLinkerOrderService;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;

class ListBaseLinkerOrders extends ListRecords
{
    protected static string $resource = BaseLinkerOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('sync_orders')
                ->label('Synchronizuj z BaseLinker')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Synchronizacja zamówień')
                ->modalDescription('Pobierze najnowsze zamówienia z BaseLinker z ostatnich 7 dni.')
                ->action(function () {
                    $service = app(BaseLinkerOrderService::class);
                    $result = $service->syncRecentOrders(7);

                    if ($result['success']) {
                        Notification::make()
                            ->title('Synchronizacja zakończona')
                            ->body("Zsynchronizowano {$result['synced_count']} z {$result['total_orders']} zamówień")
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Błąd synchronizacji')
                            ->body($result['error'])
                            ->danger()
                            ->send();
                    }
                }),

            Actions\Action::make('sync_all_unconfirmed')
                ->label('Sync niepotwierdzone')
                ->icon('heroicon-o-exclamation-triangle')
                ->color('warning')
                ->requiresConfirmation()
                ->action(function () {
                    $service = app(BaseLinkerOrderService::class);
                    $result = $service->syncUnconfirmedOrders();

                    if ($result['success']) {
                        Notification::make()
                            ->title('Synchronizacja zakończona')
                            ->body("Zsynchronizowano {$result['synced_count']} niepotwerdzonych zamówień")
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Błąd synchronizacji')
                            ->body($result['error'])
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Wszystkie')
                ->badge(fn () => $this->getModel()::count()),
            
            'unconfirmed' => Tab::make('Niepotwierdzone')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('confirmed', false))
                ->badge(fn () => $this->getModel()::where('confirmed', false)->count())
                ->badgeColor('warning'),
            
            'confirmed' => Tab::make('Potwierdzone')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('confirmed', true))
                ->badge(fn () => $this->getModel()::where('confirmed', true)->count())
                ->badgeColor('success'),
            
            'paid' => Tab::make('Opłacone')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('payment_done', true))
                ->badge(fn () => $this->getModel()::where('payment_done', true)->count())
                ->badgeColor('success'),
            
            'unpaid' => Tab::make('Nieopłacone')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('payment_done', false))
                ->badge(fn () => $this->getModel()::where('payment_done', false)->count())
                ->badgeColor('danger'),
            
            'shipped' => Tab::make('Wysłane')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('delivery_package_nr')->where('delivery_package_nr', '!=', ''))
                ->badge(fn () => $this->getModel()::whereNotNull('delivery_package_nr')->where('delivery_package_nr', '!=', '')->count())
                ->badgeColor('info'),
            
            'recent' => Tab::make('Ostatnie 7 dni')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('date_add', '>', now()->subDays(7)))
                ->badge(fn () => $this->getModel()::where('date_add', '>', now()->subDays(7))->count())
                ->badgeColor('primary'),
        ];
    }
}