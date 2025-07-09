{{-- resources/views/filament/modals/sync-report.blade.php --}}
<div class="space-y-4">
    <h2 class="text-lg font-bold">Podsumowanie synchronizacji</h2>

    <table class="min-w-full text-sm border border-gray-200">
        <tbody>
            <tr>
                <td class="font-semibold p-2">Łączna liczba produktów</td>
                <td class="p-2">{{ $report['total_products'] }}</td>
            </tr>
            <tr>
                <td class="font-semibold p-2">Gotowe do eksportu</td>
                <td class="p-2">{{ $report['ready_for_export'] }}</td>
            </tr>
            <tr>
                <td class="font-semibold p-2">W BaseLinker</td>
                <td class="p-2">{{ $report['in_baselinker'] }}</td>
            </tr>
            <tr>
                <td class="font-semibold p-2">Synchronizacja włączona</td>
                <td class="p-2">{{ $report['sync_enabled'] }}</td>
            </tr>
            <tr>
                <td class="font-semibold p-2">Rozbieżności cen</td>
                <td class="p-2">{{ $report['price_mismatches'] }}</td>
            </tr>
            <tr>
                <td class="font-semibold p-2">Rozbieżności stanów</td>
                <td class="p-2">{{ $report['stock_mismatches'] }}</td>
            </tr>
            <tr>
                <td class="font-semibold p-2">Łączne rozbieżności</td>
                <td class="p-2">{{ $report['data_mismatches'] }}</td>
            </tr>
            <tr>
                <td class="font-semibold p-2">Procent eksportu</td>
                <td class="p-2">{{ $report['export_percentage'] }}%</td>
            </tr>
            <tr>
                <td class="font-semibold p-2">Procent synchronizacji</td>
                <td class="p-2">{{ $report['sync_percentage'] }}%</td>
            </tr>
        </tbody>
    </table>
</div>
