{{-- resources/views/filament/columns/barcode.blade.php --}}
<div class="flex flex-col items-center space-y-2 py-1">
    @if($getRecord()->ean)
        {{-- Graficzna reprezentacja kodu kreskowego --}}
        <div class="barcode-container relative">
            <svg width="80" height="24" class="barcode">
                {{-- Generowanie losowych pasków dla efektu wizualnego --}}
                @php
                    $barWidth = 2;
                    $x = 2;
                @endphp
                @for($i = 0; $i < 25; $i++)
                    @php
                        $height = rand(16, 20);
                        $width = rand(1, 3);
                    @endphp
                    <rect x="{{ $x }}" y="2" width="{{ $width }}" height="{{ $height }}" 
                          fill="currentColor" class="text-gray-900 dark:text-gray-100"/>
                    @php $x += $width + 1; @endphp
                @endfor
            </svg>
        </div>
        
        {{-- Kod EAN pod kreską --}}
        <div class="text-center">
            <span class="text-xs font-mono text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded">
                {{ $getRecord()->ean }}
            </span>
        </div>
    @else
        {{-- Placeholder gdy brak EAN --}}
        <div class="flex flex-col items-center space-y-2 text-gray-400 py-2">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                      d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <span class="text-xs">Brak EAN</span>
        </div>
    @endif
</div>

<style>
.barcode {
    filter: contrast(1.2);
}
.barcode-container {
    background: linear-gradient(90deg, transparent 0%, rgba(0,0,0,0.02) 50%, transparent 100%);
    border-radius: 4px;
    padding: 2px 4px;
}
</style>