<?php

namespace App\Http\Controllers;

use App\Models\Zlecenie;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

class ZlecenieController extends Controller
{
    /**
     * Generuje widok do drukowania zlecenia
     * 
     * @param Zlecenie $zlecenie
     * @return \Illuminate\View\View
     */
    public function drukuj(Zlecenie $zlecenie)
    {
        $zlecenie->load('produkt.receptura', 'produkt.opakowanie');
        
        // Upewnij się, że mamy aktualne dane o potrzebnych surowcach
        if (empty($zlecenie->surowce_potrzebne)) {
            $zlecenie->obliczPotrzebneSurowce();
        }
        
        return view('zlecenia.drukuj', compact('zlecenie'));
    }
}