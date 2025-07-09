@php
    use Carbon\Carbon;
@endphp

<div class="space-y-6">
    <div class="bg-gray-50 p-4 rounded-lg">
        <h3 class="text-lg font-semibold text-gray-800 mb-2">Podsumowanie zlecenia</h3>
        <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
                <span class="font-medium">Numer zlecenia:</span> {{ $record->numer }}
            </div>
            <div>
                <span class="font-medium">Produkt:</span> {{ $record->produkt->nazwa }}
            </div>
            <div>
                <span class="font-medium">Ilość:</span> {{ $record->ilosc }} szt.
            </div>
            <div>
                <span class="font-medium">Status:</span> 
                <span class="px-2 py-1 rounded-full text-xs bg-blue-100 text-blue-800">
                    {{ ucfirst($record->status) }}
                </span>
            </div>
        </div>
    </div>

    <div class="space-y-4">
        <h4 class="text-md font-semibold text-gray-800">Pobrane surowce</h4>
        
        @if(empty($podsumowanie))
            <div class="text-center py-8 text-gray-500">
                <p>Brak pobranych surowców dla tego zlecenia.</p>
            </div>
        @else
            @foreach($podsumowanie as $item)
                <div class="border border-gray-200 rounded-lg p-4 bg-white">
                    <div class="flex justify-between items-start mb-3">
                        <div class="flex-1">
                            <h5 class="font-semibold text-gray-800">
                                {{ $item['surowiec']->nazwa }}
                            </h5>
                            @if($item['surowiec']->nazwa_naukowa)
                                <p class="text-sm text-gray-600">
                                    {{ $item['surowiec']->nazwa_naukowa }}
                                </p>
                            @endif
                            <p class="text-sm text-gray-500">
                                Kod: {{ $item['surowiec']->kod }}
                            </p>
                        </div>
                        <div class="text-right">
                            <div class="text-lg font-bold text-green-600">
                                {{ number_format($item['calkowita_masa'], 3, ',', ' ') }} kg
                            </div>
                            <div class="text-sm text-gray-500">
                                Łącznie pobrano
                            </div>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <h6 class="text-sm font-medium text-gray-700">Szczegóły pobran:</h6>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-2 text-left">Partia</th>
                                        <th class="px-3 py-2 text-left">Magazyn</th>
                                        <th class="px-3 py-2 text-right">Masa</th>
                                        <th class="px-3 py-2 text-left">Data</th>
                                        <th class="px-3 py-2 text-left">Lokalizacja</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    @foreach($item['partie'] as $partia)
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-3 py-2 font-medium">
                                                {{ $partia['numer_partii'] }}
                                            </td>
                                            <td class="px-3 py-2">
                                                <span class="inline-flex items-center">
                                                    {{ $partia['ikona_magazynu'] }}
                                                    <span class="ml-1">{{ $partia['typ_magazynu'] }}</span>
                                                </span>
                                            </td>
                                            <td class="px-3 py-2 text-right font-medium">
                                                {{ number_format($partia['masa_pobrana'], 3, ',', ' ') }} kg
                                            </td>
                                            <td class="px-3 py-2 text-gray-600">
                                                {{ Carbon::parse($partia['data_pobrania'])->format('d.m.Y H:i') }}
                                            </td>
                                            <td class="px-3 py-2 text-gray-600">
                                                {{ $partia['lokalizacja_przed'] }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endforeach
        @endif
    </div>

    @if(!empty($podsumowanie))
        <div class="bg-green-50 border border-green-200 rounded-lg p-4">
            <div class="flex items-center">
                <svg class="w-5 h-5 text-green-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                </svg>
                <div>
                    <h4 class="font-semibold text-green-800">
                        Surowce zostały pomyślnie pobrane
                    </h4>
                    <p class="text-sm text-green-700">
                        Łącznie pobrano {{ count($podsumowanie) }} rodzajów surowców z magazynu.
                    </p>
                </div>
            </div>
        </div>
    @endif
</div>