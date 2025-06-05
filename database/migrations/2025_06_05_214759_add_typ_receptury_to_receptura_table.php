<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receptura', function (Blueprint $table) {
            // Dodanie kolumny typ_receptury
            $table->enum('typ_receptury', ['gramy', 'mililitry'])
                  ->default('gramy')
                  ->after('kod')
                  ->comment('Określa czy receptura jest liczona w gramach czy mililitrach');
        });
    }

    public function down(): void
    {
        Schema::table('receptura', function (Blueprint $table) {
            $table->dropColumn('typ_receptury');
        });
    }
};