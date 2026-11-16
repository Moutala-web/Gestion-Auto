<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use ZipArchive;

class SauvegardeService
{
    // Nombre de sauvegardes conservées : les plus anciennes sont supprimées automatiquement
    public const CONSERVER = 30;

    // Les sauvegardes sont stockées hors du dossier public : elles ne sont jamais accessibles par une adresse web
    public function dossier(): string
    {
        $dossier = storage_path('app/backups');

        if (! is_dir($dossier)) {
            mkdir($dossier, 0755, true);
        }

        return $dossier;
    }

    /** Crée une sauvegarde complète (base de données + logo) et renvoie le nom du fichier. */
    public function creer(): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('L\'extension PHP « zip » est nécessaire pour créer une sauvegarde.');
        }

        @set_time_limit(300);

        $nom = 'sauvegarde-' . now()->format('Ymd-His') . '.zip';
        $chemin = $this->dossier() . DIRECTORY_SEPARATOR . $nom;
        $sql = tempnam(sys_get_temp_dir(), 'sql');

        try {
            $this->exporterBase($sql);

            $zip = new ZipArchive();

            if ($zip->open($chemin, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Impossible de créer le fichier de sauvegarde.');
            }

            $zip->addFile($sql, 'base-de-donnees.sql');
            $this->ajouterDossier($zip, public_path('uploads'), 'uploads');
            $zip->close();
        } finally {
            @unlink($sql);
        }

        $this->purger();

        return $nom;
    }

    /** Liste des sauvegardes, la plus récente en premier. */
    public function lister(): array
    {
        $fichiers = glob($this->dossier() . DIRECTORY_SEPARATOR . 'sauvegarde-*.zip') ?: [];

        $liste = array_map(fn (string $f) => [
            'nom' => basename($f),
            'taille' => filesize($f),
            'date' => Carbon::createFromTimestamp(filemtime($f)),
        ], $fichiers);

        usort($liste, fn ($a, $b) => $b['date']->timestamp <=> $a['date']->timestamp);

        return $liste;
    }

    public function derniere(): ?Carbon
    {
        return $this->lister()[0]['date'] ?? null;
    }

    // Le nom est strictement contrôlé : impossible de sortir du dossier des sauvegardes
    public function chemin(string $nom): ?string
    {
        if (! preg_match('/^sauvegarde-\d{8}-\d{6}\.zip$/', $nom)) {
            return null;
        }

        $chemin = $this->dossier() . DIRECTORY_SEPARATOR . $nom;

        return is_file($chemin) ? $chemin : null;
    }

    public function supprimer(string $nom): bool
    {
        $chemin = $this->chemin($nom);

        return $chemin !== null && unlink($chemin);
    }

    private function purger(): void
    {
        foreach (array_slice($this->lister(), self::CONSERVER) as $ancienne) {
            $this->supprimer($ancienne['nom']);
        }
    }

    /** Exporte toutes les tables (structure et données) dans un fichier SQL réimportable. */
    private function exporterBase(string $fichier): void
    {
        $pdo = DB::connection()->getPdo();
        $f = fopen($fichier, 'w');

        fwrite($f, "-- Sauvegarde du " . now()->format('d/m/Y H:i:s') . "\n");
        fwrite($f, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");

        $tables = array_map(
            fn ($ligne) => array_values((array) $ligne)[0],
            DB::select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")
        );

        foreach ($tables as $table) {
            $creation = (array) DB::selectOne("SHOW CREATE TABLE `{$table}`");
            $structure = $creation['Create Table'] ?? array_values($creation)[1];

            fwrite($f, "DROP TABLE IF EXISTS `{$table}`;\n{$structure};\n\n");

            $colonnes = null;
            $lot = [];

            foreach (DB::table($table)->cursor() as $ligne) {
                $ligne = (array) $ligne;
                $colonnes ??= '`' . implode('`, `', array_keys($ligne)) . '`';

                $valeurs = array_map(
                    fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v),
                    $ligne
                );
                $lot[] = '(' . implode(', ', $valeurs) . ')';

                if (count($lot) >= 200) {
                    fwrite($f, "INSERT INTO `{$table}` ({$colonnes}) VALUES\n" . implode(",\n", $lot) . ";\n");
                    $lot = [];
                }
            }

            if ($lot) {
                fwrite($f, "INSERT INTO `{$table}` ({$colonnes}) VALUES\n" . implode(",\n", $lot) . ";\n");
            }

            fwrite($f, "\n");
        }

        fwrite($f, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($f);
    }

    private function ajouterDossier(ZipArchive $zip, string $source, string $prefixe): void
    {
        if (! is_dir($source)) {
            return;
        }

        $racine = realpath($source);

        $iterateur = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($racine, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterateur as $fichier) {
            if ($fichier->isFile()) {
                $relatif = str_replace('\\', '/', substr($fichier->getRealPath(), strlen($racine) + 1));
                $zip->addFile($fichier->getRealPath(), $prefixe . '/' . $relatif);
            }
        }
    }
}