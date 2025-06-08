<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('magazyn', function (Blueprint $table) {
            $table->id();
            $table->string('typ_towaru'); // 'surowiec' lub 'produkt'
            $table->unsignedBigInteger('towar_id'); // ID surowca lub produktu
            $table->string('numer_partii')->nullable(); // Numer partii (dla produktów i niektórych surowców)
            $table->decimal('ilosc_dostepna', 10, 3)->default(0);
            $table->string('jednostka', 10);
            $table->decimal('wartosc', 10, 2)->default(0);
            $table->date('data_waznosci')->nullable();
            $table->string('lokalizacja', 100)->nullable(); // Regał, półka itp.
            $table->timestamps();
            
            $table->index(['typ_towaru', 'towar_id']);
            $table->index(['numer_partii']);
            $table->index(['data_waznosci']);
            $table->unique(['typ_towaru', 'towar_id', 'numer_partii'], 'magazyn_unique_item');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('magazyn');
    }
};