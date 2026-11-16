<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 100);
            $table->string('prenom', 100)->nullable();
            $table->string('telephone', 30);
            $table->string('email', 150)->nullable();
            $table->text('adresse')->nullable();
            $table->enum('type', ['client', 'prospect'])->default('client');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('telephone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};