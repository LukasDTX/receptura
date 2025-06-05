<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opakowanie', function (Blueprint $table) {
            // Dodanie kolumny pojemnosc typu decimal z 3 miejscami po przecinku
            $table->decimal('pojemnosc', 10, 0)->default(0)->after('opis')->comment('Pojemność opakowania w gramach');
        });
    }

    public function down(): void
    {
        Schema::table('opakowanie', function (Blueprint $table) {
            // Usunięcie kolumny pojemnosc
            $table->dropColumn('pojemnosc');
        });
    }
};
