<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('depenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('categorie', 100);
            $table->string('libelle', 255);
            $table->decimal('montant', 15, 2);
            $table->dateTime('date_depense');
            $table->enum('mode_paiement', ['especes', 'orange_money'])->default('especes');
            $table->string('reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('date_depense');
            $table->index('categorie');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'])) {
            DB::statement('ALTER TABLE depenses ADD CONSTRAINT chk_depenses_montant CHECK (montant > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('depenses');
    }
};