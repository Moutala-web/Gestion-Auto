<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Le catalogue définit aussi les pièces détachées et les articles divers
        DB::statement("ALTER TABLE modeles MODIFY type ENUM('voiture', 'moto', 'piece', 'divers') NOT NULL");

        Schema::table('modeles', function (Blueprint $table) {
            $table->unsignedInteger('seuil_alerte')->default(0)->after('prix_vente_indicatif');
        });

        Schema::table('pieces', function (Blueprint $table) {
            $table->foreignId('modele_id')->nullable()->after('reference')->constrained('modeles')->restrictOnDelete();
        });

        // Reprise : un « modèle » du catalogue pour chaque pièce ou article déjà enregistré
        foreach (DB::table('pieces')->get() as $piece) {
            $marque = (string) ($piece->marque ?? '');

            $modele = DB::table('modeles')
                ->where('type', $piece->famille)
                ->where('marque', $marque)
                ->where('modele', $piece->designation)
                ->first();

            $id = $modele
                ? $modele->id
                : DB::table('modeles')->insertGetId([
                    'type' => $piece->famille,
                    'marque' => $marque,
                    'modele' => $piece->designation,
                    'categorie' => $piece->categorie,
                    'prix_vente_indicatif' => $piece->prix_vente,
                    'seuil_alerte' => $piece->seuil_alerte,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            DB::table('pieces')->where('id', $piece->id)->update(['modele_id' => $id]);
        }
    }

    public function down(): void
    {
        Schema::table('pieces', function (Blueprint $table) {
            $table->dropConstrainedForeignId('modele_id');
        });

        DB::table('modeles')->whereIn('type', ['piece', 'divers'])->delete();

        Schema::table('modeles', function (Blueprint $table) {
            $table->dropColumn('seuil_alerte');
        });

        DB::statement("ALTER TABLE modeles MODIFY type ENUM('voiture', 'moto') NOT NULL");
    }
};