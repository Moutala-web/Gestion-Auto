<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paiements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vente_id')->nullable()->constrained('ventes')->restrictOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->restrictOnDelete();
            $table->enum('type', ['vente', 'location', 'caution']);
            $table->enum('mode', ['especes', 'orange_money']);
            $table->decimal('montant', 15, 2);
            $table->string('reference_transaction', 100)->nullable();
            $table->dateTime('date_paiement');
            $table->enum('statut', ['valide', 'annule'])->default('valide');
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('date_paiement');
            $table->index('reference_transaction');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'])) {
            DB::statement("ALTER TABLE paiements ADD CONSTRAINT chk_paiements_lien CHECK (
                montant > 0 AND (
                    (type = 'vente' AND vente_id IS NOT NULL AND location_id IS NULL) OR
                    (type IN ('location', 'caution') AND location_id IS NOT NULL AND vente_id IS NULL)
                )
            )");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('paiements');
    }
};