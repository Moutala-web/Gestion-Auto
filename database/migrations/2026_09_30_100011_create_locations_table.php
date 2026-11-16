<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 50)->unique();
            $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
            $table->foreignId('vehicule_id')->constrained('vehicules')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('date_depart');
            $table->dateTime('date_retour_prevue');
            $table->dateTime('date_retour_reelle')->nullable();
            $table->decimal('tarif', 15, 2);            // montant réellement convenu pour la période
            $table->decimal('caution', 15, 2)->default(0);
            $table->unsignedInteger('kilometrage_depart');
            $table->unsignedInteger('kilometrage_retour')->nullable();
            $table->text('etat_depart')->nullable();
            $table->text('etat_retour')->nullable();
            $table->decimal('retenue_caution', 15, 2)->default(0);
            $table->decimal('caution_remboursee', 15, 2)->default(0);
            $table->enum('statut', ['reservee', 'en_cours', 'terminee', 'annulee'])->default('reservee');
            $table->text('notes')->nullable();
            $table->timestamps();

            // sert à la vérification de chevauchement (faite en transaction côté Laravel)
            $table->index(['vehicule_id', 'date_depart', 'date_retour_prevue']);
            $table->index('statut');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'])) {
            DB::statement('ALTER TABLE locations ADD CONSTRAINT chk_locations_dates CHECK (
                date_retour_prevue > date_depart
            )');
            DB::statement('ALTER TABLE locations ADD CONSTRAINT chk_locations_caution CHECK (
                retenue_caution >= 0 AND caution_remboursee >= 0
                AND retenue_caution + caution_remboursee <= caution
            )');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};