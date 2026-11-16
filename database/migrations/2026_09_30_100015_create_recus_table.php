<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recus', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 50)->unique();
            $table->foreignId('vente_id')->nullable()->constrained('ventes')->restrictOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->restrictOnDelete();
            $table->foreignId('paiement_id')->constrained('paiements')->restrictOnDelete();
            $table->dateTime('date_generation');
            $table->string('chemin_fichier', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'])) {
            DB::statement('ALTER TABLE recus ADD CONSTRAINT chk_recus_lien CHECK (
                (vente_id IS NOT NULL AND location_id IS NULL) OR
                (vente_id IS NULL AND location_id IS NOT NULL)
            )');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('recus');
    }
};