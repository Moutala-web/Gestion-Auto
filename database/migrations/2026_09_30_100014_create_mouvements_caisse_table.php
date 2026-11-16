<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mouvements_caisse', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->enum('type', ['entree', 'sortie']);
            // categories : vente, location, caution, remboursement_caution, achat, frais_vehicule, depense...
            // 'caution' et 'remboursement_caution' sont exclues du chiffre d'affaires
            $table->string('categorie', 100);
            $table->decimal('montant', 15, 2);
            $table->enum('mode_paiement', ['especes', 'orange_money']);
            $table->string('reference_type', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->dateTime('date_operation');
            $table->text('description')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('date_operation');
            $table->index(['reference_type', 'reference_id']);
            $table->index(['type', 'categorie']);
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'])) {
            DB::statement('ALTER TABLE mouvements_caisse ADD CONSTRAINT chk_caisse_montant CHECK (montant > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mouvements_caisse');
    }
};