<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('strings', function (Blueprint $table) {
            $table->id();
            $table->text('value')->unique();
            $table->string('sha256_hash')->unique();
            $table->json('properties');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('strings');
    }
};
