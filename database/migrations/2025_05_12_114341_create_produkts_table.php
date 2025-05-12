<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('produkt', function (Blueprint $table) {
            $table->id();
            $table->string('nazwa');
            $table->string('kod')->unique();
            $table->foreignId('receptura_id')->constrained('receptura');
            $table->foreignId('opakowanie_id')->constrained('opakowanie');
            $table->text('opis')->nullable();
            $table->decimal('koszt_calkowity', 10, 2)->default(0);
            $table->decimal('cena_sprzedazy', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produkt');
    }
};
