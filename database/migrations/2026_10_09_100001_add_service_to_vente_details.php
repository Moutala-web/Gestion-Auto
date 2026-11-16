<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'])) {
            return;
        }

        $this->supprimerContrainte();

        DB::statement("ALTER TABLE vente_details MODIFY type_article ENUM('vehicule', 'moto', 'piece', 'service') NOT NULL");

        // Un service n'est lié à aucun article : seule la quantité doit être positive
        DB::statement("ALTER TABLE vente_details ADD CONSTRAINT chk_vente_details_un_article CHECK (
            (type_article = 'vehicule' AND vehicule_id IS NOT NULL AND moto_id IS NULL AND piece_id IS NULL AND quantite = 1) OR
            (type_article = 'moto' AND moto_id IS NOT NULL AND vehicule_id IS NULL AND piece_id IS NULL AND quantite = 1) OR
            (type_article = 'piece' AND piece_id IS NOT NULL AND vehicule_id IS NULL AND moto_id IS NULL AND quantite > 0) OR
            (type_article = 'service' AND vehicule_id IS NULL AND moto_id IS NULL AND piece_id IS NULL AND quantite > 0)
        )");
    }

    public function down(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'])) {
            return;
        }

        // Impossible de revenir en arrière s'il existe déjà des lignes de service
        DB::table('vente_details')->where('type_article', 'service')->delete();

        $this->supprimerContrainte();

        DB::statement("ALTER TABLE vente_details MODIFY type_article ENUM('vehicule', 'moto', 'piece') NOT NULL");

        DB::statement("ALTER TABLE vente_details ADD CONSTRAINT chk_vente_details_un_article CHECK (
            (type_article = 'vehicule' AND vehicule_id IS NOT NULL AND moto_id IS NULL AND piece_id IS NULL AND quantite = 1) OR
            (type_article = 'moto' AND moto_id IS NOT NULL AND vehicule_id IS NULL AND piece_id IS NULL AND quantite = 1) OR
            (type_article = 'piece' AND piece_id IS NOT NULL AND vehicule_id IS NULL AND moto_id IS NULL AND quantite > 0)
        )");
    }

    // MariaDB et MySQL récents acceptent DROP CONSTRAINT ; les anciens MySQL 8 demandent DROP CHECK
    private function supprimerContrainte(): void
    {
        try {
            DB::statement('ALTER TABLE vente_details DROP CONSTRAINT chk_vente_details_un_article');
        } catch (\Throwable $e) {
            DB::statement('ALTER TABLE vente_details DROP CHECK chk_vente_details_un_article');
        }
    }
};