<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\ZlecenieController;

// Definiujemy trasę do drukowania zlecenia
Route::get('/zlecenia/{zlecenie}/drukuj', [ZlecenieController::class, 'drukuj'])->name('zlecenie.drukuj');
Route::get('/debug/receptura/{id}', [App\Http\Controllers\DebugController::class, 'debugSumaProcentowa']);
Route::get('/', function () {
    return view('welcome');
});
