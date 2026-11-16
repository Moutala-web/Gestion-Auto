<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mouvements_stock', function (Blueprint $table) {
            $table->id();
            $table->foreignId('piece_id')->constrained('pieces')->restrictOnDelete();
            $table->enum('type', ['entree', 'sortie', 'ajustement']);
            $table->integer('quantite'); // négatif autorisé uniquement pour un ajustement
            $table->decimal('prix_achat', 15, 2)->nullable(); // renseigné sur les entrées, sert au calcul de marge
            $table->string('motif', 255)->nullable();
            $table->string('reference_operation', 100)->nullable();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['piece_id', 'created_at']);
            $table->index('reference_operation');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mouvements_stock');
    }
};