<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opakowanie', function (Blueprint $table) {
            // Dodanie kolumny jednostka
            $table->enum('jednostka', ['g', 'ml'])
                  ->default('g')
                  ->after('pojemnosc')
                  ->comment('Jednostka pojemności opakowania - gramy lub mililitry');
        });
    }

    public function down(): void
    {
        Schema::table('opakowanie', function (Blueprint $table) {
            $table->dropColumn('jednostka');
        });
    }
};