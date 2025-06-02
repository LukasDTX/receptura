<div class="flex justify-between items-center px-4 py-3 border-t">
    <div class="text-sm text-gray-500">
        * Procenty są obliczone na podstawie wagi składników, zakładając że 1kg = 100%. Dla sztuk procent nie jest wyświetlany.
    </div>
    <div class="font-medium">
        Suma procentowa: <span class="{{ $kolor }}">{{ number_format($sumaProcentowa, 2) }}%{{ $informacja }}</span>
    </div>
</div>