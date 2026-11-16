<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('motos', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 50)->unique();
            $table->string('marque', 100);
            $table->string('modele', 100);
            $table->year('annee')->nullable();
            $table->string('couleur', 50)->nullable();
            $table->unsignedInteger('kilometrage')->default(0);
            $table->decimal('prix_achat', 15, 2);
            $table->decimal('prix_vente', 15, 2)->nullable();
            $table->enum('statut', ['disponible', 'reserve', 'vendu'])->default('disponible');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('statut');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('motos');
    }
};