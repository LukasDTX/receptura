<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receptura', function (Blueprint $table) {
            // Dodanie kolumny meta typu JSON do przechowywania metadanych receptury
            $table->json('meta')->nullable()->after('koszt_calkowity');
        });
    }

    public function down(): void
    {
        Schema::table('receptura', function (Blueprint $table) {
            // Usunięcie kolumny meta
            $table->dropColumn('meta');
        });
    }
};
