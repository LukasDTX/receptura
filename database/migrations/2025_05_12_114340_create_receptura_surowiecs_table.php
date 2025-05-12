<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receptura_surowiec', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receptura_id')->constrained('receptura')->onDelete('cascade');
            $table->foreignId('surowiec_id')->constrained('surowiec')->onDelete('cascade');
            $table->decimal('ilosc', 10, 3);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receptura_surowiec');
    }
};