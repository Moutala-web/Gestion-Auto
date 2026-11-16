<?php

namespace App\Console\Commands;

use App\Services\SauvegardeService;
use Illuminate\Console\Command;

class CreerSauvegarde extends Command
{
    protected $signature = 'sauvegarde:creer';

    protected $description = 'Crée une sauvegarde complète (base de données et logo) dans storage/app/backups';

    public function handle(SauvegardeService $service): int
    {
        try {
            $nom = $service->creer();
        } catch (\Throwable $e) {
            $this->error('Échec de la sauvegarde : ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->info("Sauvegarde créée : {$nom}");

        return self::SUCCESS;
    }
}