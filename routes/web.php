<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\ZlecenieController;

// Definiujemy trasę do drukowania zlecenia
Route::get('/zlecenia/{zlecenie}/drukuj', [ZlecenieController::class, 'drukuj'])->name('zlecenie.drukuj');
Route::get('/debug/receptura/{id}', [App\Http\Controllers\DebugController::class, 'debugSumaProcentowa']);
Route::get('/', function () {
    return view('welcome');
});
Route::post('/clear-temp-session', function () {
    session()->forget('temp_surowce_potrzebne');
    return response()->json(['status' => 'cleared']);
})->middleware('web');
Route::post('/test-session-data', function () {
    $tempSurowce = session('temp_surowce_potrzebne');
    return response()->json([
        'temp_surowce' => empty($tempSurowce) ? 'EMPTY' : 'COUNT_' . count($tempSurowce),
        'session_id' => session()->getId(),
        'all_session_keys' => array_keys(session()->all())
    ]);
})->middleware('web');

// Route dla drukowania zlecenia
Route::get('/zlecenie/{zlecenie}/drukuj', [ZlecenieController::class, 'drukuj'])
    ->name('zlecenie.drukuj')
    ->middleware('auth');