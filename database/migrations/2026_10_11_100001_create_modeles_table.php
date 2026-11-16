<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modeles', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['voiture', 'moto']);
            $table->string('marque', 100);
            $table->string('modele', 100);
            $table->string('categorie', 100)->nullable();
            $table->decimal('prix_vente_indicatif', 15, 2)->nullable();
            $table->unsignedSmallInteger('cylindree')->nullable(); // motos, en cm³
            $table->string('carburant', 30)->nullable();           // voitures
            $table->string('boite', 30)->nullable();               // voitures
            $table->timestamps();

            $table->unique(['type', 'marque', 'modele']);
        });

        Schema::table('vehicules', function (Blueprint $table) {
            $table->foreignId('modele_id')->nullable()->after('reference')->constrained('modeles')->restrictOnDelete();
            $table->string('numero_chassis', 50)->nullable()->unique()->after('immatriculation');
        });

        Schema::table('motos', function (Blueprint $table) {
            $table->foreignId('modele_id')->nullable()->after('reference')->constrained('modeles')->restrictOnDelete();
            $table->string('numero_chassis', 50)->nullable()->unique()->after('couleur');
        });

        // Reprise : un modèle du catalogue pour chaque couple marque / modèle déjà saisi
        $this->reprendre('vehicules', 'voiture');
        $this->reprendre('motos', 'moto');
    }

    public function down(): void
    {
        Schema::table('motos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('modele_id');
            $table->dropColumn('numero_chassis');
        });

        Schema::table('vehicules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('modele_id');
            $table->dropColumn('numero_chassis');
        });

        Schema::dropIfExists('modeles');
    }

    private function reprendre(string $table, string $type): void
    {
        $couples = DB::table($table)->select('marque', 'modele')->distinct()->get();

        foreach ($couples as $couple) {
            $existant = DB::table('modeles')
                ->where('type', $type)
                ->where('marque', $couple->marque)
                ->where('modele', $couple->modele)
                ->first();

            if (! $existant) {
                $id = DB::table('modeles')->insertGetId([
                    'type' => $type,
                    'marque' => $couple->marque,
                    'modele' => $couple->modele,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $existant = DB::table('modeles')->find($id);
            }

            // Les fiches reprennent l'écriture exacte du catalogue (« toyota » et « Toyota » ne font plus qu'un)
            DB::table($table)
                ->where('marque', $couple->marque)
                ->where('modele', $couple->modele)
                ->update([
                    'modele_id' => $existant->id,
                    'marque' => $existant->marque,
                    'modele' => $existant->modele,
                ]);
        }
    }
};