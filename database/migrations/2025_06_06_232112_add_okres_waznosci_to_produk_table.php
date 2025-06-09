<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produkt', function (Blueprint $table) {
            // Okres ważności jako enum z opcjami 12M, 24M, 36M
            $table->enum('okres_waznosci', ['12M', '24M', '36M'])
                  ->default('12M')
                  ->after('cena_sprzedazy')
                  ->comment('Okres ważności produktu: 12M, 24M lub 36M');
        });
    }

    public function down(): void
    {
        Schema::table('produkt', function (Blueprint $table) {
            $table->dropColumn('okres_waznosci');
        });
    }
};