<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
Schema::create('surowiec', function (Blueprint $table) {
    $table->id();
    $table->string('nazwa');
    $table->string('nazwa_naukowa')->nullable();
    $table->string('kod')->unique();
    $table->text('opis')->nullable();
    $table->decimal('cena_jednostkowa', 10, 2)->default(0);
    $table->enum('jednostka_miary', ['g','ml','kg', 'litr'])->default('g');
    $table->timestamps();
});
    }

    public function down(): void
    {
        Schema::dropIfExists('surowiec');
    }
};
