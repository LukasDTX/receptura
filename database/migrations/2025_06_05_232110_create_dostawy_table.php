<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dostawy', function (Blueprint $table) {
            $table->id();
            $table->string('numer_dostawy')->unique();
            $table->string('dostawca');
            $table->date('data_zamowienia');
            $table->date('planowana_data_dostawy')->nullable();
            $table->date('rzeczywista_data_dostawy')->nullable();
            $table->enum('status', ['zamowiona', 'w_transporcie', 'dostarczona', 'anulowana'])->default('zamowiona');
            $table->decimal('wartosc_calkowita', 10, 2)->default(0);
            $table->text('uwagi')->nullable();
            $table->timestamps();
            
            $table->index(['status', 'planowana_data_dostawy']);
            $table->index(['dostawca']);
        });
        
        Schema::create('dostawa_pozycje', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dostawa_id')->constrained('dostawy')->onDelete('cascade');
            $table->foreignId('surowiec_id')->constrained('surowiec')->onDelete('restrict');
            $table->decimal('ilosc_zamowiona', 10, 3);
            $table->decimal('ilosc_dostarczona', 10, 3)->default(0);
            $table->string('jednostka', 10);
            $table->decimal('cena_jednostkowa', 10, 2);
            $table->decimal('wartosc', 10, 2);
            $table->string('numer_partii_dostawcy')->nullable();
            $table->date('data_waznosci')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dostawa_pozycje');
        Schema::dropIfExists('dostawy');
    }
};