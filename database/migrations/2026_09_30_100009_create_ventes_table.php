<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ventes', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 50)->unique();
            $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('date_vente');
            $table->decimal('sous_total', 15, 2);
            $table->decimal('remise', 15, 2)->default(0);
            $table->decimal('total', 15, 2);
            $table->enum('statut', ['validee', 'annulee'])->default('validee');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('date_vente');
            $table->index('statut');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'])) {
            DB::statement('ALTER TABLE ventes ADD CONSTRAINT chk_ventes_total CHECK (
                remise >= 0 AND remise <= sous_total AND total = sous_total - remise
            )');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ventes');
    }
};