<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vente_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vente_id')->constrained('ventes')->cascadeOnDelete();
            $table->enum('type_article', ['vehicule', 'moto', 'piece']);
            $table->foreignId('vehicule_id')->nullable()->constrained('vehicules')->restrictOnDelete();
            $table->foreignId('moto_id')->nullable()->constrained('motos')->restrictOnDelete();
            $table->foreignId('piece_id')->nullable()->constrained('pieces')->restrictOnDelete();
            $table->string('designation', 255);
            $table->unsignedInteger('quantite')->default(1);
            $table->decimal('prix_unitaire', 15, 2);
            $table->decimal('total', 15, 2);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['type_article', 'vehicule_id']);
            $table->index(['type_article', 'moto_id']);
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'])) {
            DB::statement("ALTER TABLE vente_details ADD CONSTRAINT chk_vente_details_un_article CHECK (
                (type_article = 'vehicule' AND vehicule_id IS NOT NULL AND moto_id IS NULL AND piece_id IS NULL AND quantite = 1) OR
                (type_article = 'moto' AND moto_id IS NOT NULL AND vehicule_id IS NULL AND piece_id IS NULL AND quantite = 1) OR
                (type_article = 'piece' AND piece_id IS NOT NULL AND vehicule_id IS NULL AND moto_id IS NULL AND quantite > 0)
            )");
            DB::statement('ALTER TABLE vente_details ADD CONSTRAINT chk_vente_details_total CHECK (
                total = prix_unitaire * quantite
            )');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vente_details');
    }
};