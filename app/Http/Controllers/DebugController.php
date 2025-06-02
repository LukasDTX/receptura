<?php

namespace App\Http\Controllers;

use App\Models\Receptura;
use Illuminate\Http\Request;

class DebugController extends Controller
{
    public function debugSumaProcentowa($recepturaId)
    {
        $receptura = Receptura::with('surowce')->findOrFail($recepturaId);
        
        // Ręcznie obliczamy sumę procentową
        $sumaProcentowa = 0;
        $dane = [];
        
        foreach ($receptura->surowce as $surowiec) {
            $ilosc = $surowiec->pivot->ilosc;
            $jednostka = $surowiec->jednostka_miary;
            
            // Przeliczenie ilości na gramy i procenty
            $iloscWGramach = 0;
            if ($jednostka === 'g') {
                $iloscWGramach = $ilosc;
            } elseif ($jednostka === 'kg') {
                $iloscWGramach = $ilosc * 1000;
            } elseif ($jednostka === 'ml') {
                $iloscWGramach = $ilosc;
            } elseif ($jednostka === 'l') {
                $iloscWGramach = $ilosc * 1000;
            }
            
            $procent = ($iloscWGramach / 1000) * 100;
            $sumaProcentowa += $procent;
            
            $dane[] = [
                'nazwa' => $surowiec->nazwa,
                'ilosc' => $ilosc,
                'jednostka' => $jednostka,
                'ilosc_w_gramach' => $iloscWGramach,
                'procent' => $procent,
            ];
        }
        
        // Aktualizujemy metadane receptury
        $receptura->update([
            'meta' => json_encode(['suma_procentowa' => $sumaProcentowa])
        ]);
        
        return response()->json([
            'receptura' => $receptura->toArray(),
            'surowce' => $dane,
            'suma_procentowa' => $sumaProcentowa,
            'meta_przed' => $receptura->getOriginal('meta'),
            'meta_po' => $receptura->meta,
        ]);
    }
}