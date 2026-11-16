<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('achats', function (Blueprint $table) {
            $table->id();
            $table->enum('type_article', ['vehicule', 'moto', 'piece']);
            $table->foreignId('vehicule_id')->nullable()->constrained('vehicules')->restrictOnDelete();
            $table->foreignId('moto_id')->nullable()->constrained('motos')->restrictOnDelete();
            $table->foreignId('piece_id')->nullable()->constrained('pieces')->restrictOnDelete();
            $table->string('fournisseur', 150)->nullable();
            $table->unsignedInteger('quantite')->default(1);
            $table->decimal('prix_unitaire', 15, 2);
            $table->decimal('montant', 15, 2);
            $table->enum('mode_paiement', ['especes', 'orange_money'])->default('especes');
            $table->string('reference_transaction', 100)->nullable();
            $table->dateTime('date_achat');
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('date_achat');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'])) {
            DB::statement("ALTER TABLE achats ADD CONSTRAINT chk_achats_un_article CHECK (
                (type_article = 'vehicule' AND vehicule_id IS NOT NULL AND moto_id IS NULL AND piece_id IS NULL) OR
                (type_article = 'moto' AND moto_id IS NOT NULL AND vehicule_id IS NULL AND piece_id IS NULL) OR
                (type_article = 'piece' AND piece_id IS NOT NULL AND vehicule_id IS NULL AND moto_id IS NULL)
            )");
            DB::statement('ALTER TABLE achats ADD CONSTRAINT chk_achats_montant CHECK (montant >= 0 AND quantite > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('achats');
    }
};