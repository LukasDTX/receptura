<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ruchy_magazynowe', function (Blueprint $table) {
            $table->id();
            $table->string('numer_dokumentu')->nullable();
            $table->enum('typ_ruchu', ['przyjecie', 'wydanie', 'korekta_plus', 'korekta_minus', 'transfer', 'produkcja']);
            $table->string('typ_towaru'); // 'surowiec' lub 'produkt'
            $table->unsignedBigInteger('towar_id');
            $table->string('numer_partii')->nullable();
            $table->decimal('ilosc', 10, 3);
            $table->string('jednostka', 10);
            $table->decimal('cena_jednostkowa', 10, 2)->default(0);
            $table->decimal('wartosc', 10, 2)->default(0);
            $table->date('data_ruchu');
            $table->string('zrodlo_docelowe')->nullable(); // Dostawca, klient, zlecenie itp.
            $table->text('uwagi')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            
            $table->index(['typ_towaru', 'towar_id']);
            $table->index(['numer_partii']);
            $table->index(['data_ruchu']);
            $table->index(['typ_ruchu']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ruchy_magazynowe');
    }
};