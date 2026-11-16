<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicule_frais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicule_id')->constrained('vehicules')->restrictOnDelete();
            $table->string('libelle', 150);
            $table->string('categorie', 100);
            $table->decimal('montant', 15, 2);
            $table->enum('mode_paiement', ['especes', 'orange_money'])->default('especes');
            $table->date('date_frais');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('categorie');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicule_frais');
    }
};