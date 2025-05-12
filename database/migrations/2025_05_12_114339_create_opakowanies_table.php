<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opakowanie', function (Blueprint $table) {
            $table->id();
            $table->string('nazwa');
            $table->string('kod')->unique();
            $table->text('opis')->nullable();
            $table->decimal('cena', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opakowanie');
    }
};
