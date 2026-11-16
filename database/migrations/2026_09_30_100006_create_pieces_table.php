<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pieces', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 50)->unique();
            $table->string('designation', 150);
            $table->string('categorie', 100)->nullable();
            $table->string('marque', 100)->nullable();
            $table->decimal('prix_achat', 15, 2);
            $table->decimal('prix_vente', 15, 2);
            $table->unsignedInteger('quantite_stock')->default(0); // unsigned : pas de stock négatif
            $table->unsignedInteger('seuil_alerte')->default(0);
            $table->string('emplacement', 100)->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pieces');
    }
};