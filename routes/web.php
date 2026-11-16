<?php

use App\Http\Controllers\AchatController;
use App\Http\Controllers\ArrivageController;
use App\Http\Controllers\CaisseController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepenseController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\ModeleController;
use App\Http\Controllers\MotoController;
use App\Http\Controllers\MouvementStockController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ParametreController;
use App\Http\Controllers\PieceController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RecuController;
use App\Http\Controllers\SauvegardeController;
use App\Http\Controllers\ServiceDiversController;
use App\Http\Controllers\VehiculeController;
use App\Http\Controllers\VehiculeFraisController;
use App\Http\Controllers\VenteController;
use Illuminate\Support\Facades\Route;

// Page d'accueil : on envoie vers le tableau de bord (la connexion est demandée si besoin)
Route::redirect('/', '/dashboard');

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Gestion des clients (création et modification dans des fenêtres modales)
    Route::resource('clients', ClientController::class)->except(['create', 'edit']);

    // Catalogue : la seule place où l'on DÉFINIT voitures, motos, pièces et articles divers (fenêtres modales)
    Route::resource('modeles', ModeleController::class)->except(['create', 'edit', 'show']);

    // Véhicules, motos, pièces : on consulte le stock et on complète les informations de chaque fiche.
    // Aucune création manuelle : les fiches naissent d'un achat.
    Route::resource('vehicules', VehiculeController::class)->only(['index', 'show', 'update', 'destroy']);
    Route::post('vehicules/{vehicule}/frais', [VehiculeFraisController::class, 'store'])
        ->name('vehicules.frais.store');
    Route::delete('vehicules/{vehicule}/frais/{frais}', [VehiculeFraisController::class, 'destroy'])
        ->name('vehicules.frais.destroy');

    Route::resource('motos', MotoController::class)->only(['index', 'update', 'destroy']);

    Route::resource('pieces', PieceController::class)->only(['index', 'show', 'update', 'destroy']);
    Route::post('pieces/{piece}/mouvements', [MouvementStockController::class, 'store'])
        ->name('pieces.mouvements.store');

    // Ventes : une vente ne se modifie pas, on l'annule (l'historique est conservé)
    Route::resource('ventes', VenteController::class)->only(['index', 'store', 'show']);
    Route::post('ventes/{vente}/annuler', [VenteController::class, 'annuler'])->name('ventes.annuler');

    // Prestations : les services vendus (lignes de type « service » des ventes)
    Route::get('services', [ServiceDiversController::class, 'index'])->name('services.index');

    // Locations : réservation, départ, paiements, retour, annulation
    Route::resource('locations', LocationController::class)->only(['index', 'store', 'show']);
    Route::post('locations/{location}/demarrer', [LocationController::class, 'demarrer'])->name('locations.demarrer');
    Route::post('locations/{location}/paiements', [LocationController::class, 'paiement'])->name('locations.paiements.store');
    Route::post('locations/{location}/terminer', [LocationController::class, 'terminer'])->name('locations.terminer');
    Route::post('locations/{location}/annuler', [LocationController::class, 'annuler'])->name('locations.annuler');

    // Finances : journal de caisse (lecture seule), dépenses et achats.
    // L'achat est la seule porte d'entrée du stock : modèle du catalogue + quantité.
    Route::get('caisse', [CaisseController::class, 'index'])->name('caisse.index');
    Route::get('caisse/export', [CaisseController::class, 'exporter'])->name('caisse.export');
    Route::resource('depenses', DepenseController::class)->only(['index', 'store']);
    Route::post('depenses/{depense}/annuler', [DepenseController::class, 'annuler'])->name('depenses.annuler');
    Route::get('achats', [AchatController::class, 'index'])->name('achats.index');
    Route::post('achats/arrivage', [ArrivageController::class, 'store'])->name('achats.arrivage');
    Route::post('achats/{achat}/annuler', [AchatController::class, 'annuler'])->name('achats.annuler');

    // Reçus PDF et paramètres de l'entreprise
    Route::get('recus/{recu}/pdf', [RecuController::class, 'pdf'])->name('recus.pdf');
    Route::get('parametres', [ParametreController::class, 'edit'])->name('parametres.edit');
    Route::put('parametres', [ParametreController::class, 'update'])->name('parametres.update');

    // Sauvegardes (le nom du fichier est strictement contrôlé)
    Route::get('sauvegardes', [SauvegardeController::class, 'index'])->name('sauvegardes.index');
    Route::post('sauvegardes', [SauvegardeController::class, 'store'])->name('sauvegardes.store');
    Route::get('sauvegardes/{fichier}', [SauvegardeController::class, 'telecharger'])
        ->where('fichier', 'sauvegarde-[0-9]{8}-[0-9]{6}\.zip')
        ->name('sauvegardes.telecharger');
    Route::delete('sauvegardes/{fichier}', [SauvegardeController::class, 'destroy'])
        ->where('fichier', 'sauvegarde-[0-9]{8}-[0-9]{6}\.zip')
        ->name('sauvegardes.destroy');

    // Notifications (la cloche)
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('notifications/{id}/ouvrir', [NotificationController::class, 'ouvrir'])->name('notifications.ouvrir');
    Route::post('notifications/tout-lire', [NotificationController::class, 'toutLire'])->name('notifications.tout-lire');
    Route::post('notifications/vider', [NotificationController::class, 'vider'])->name('notifications.vider');
});

require __DIR__.'/auth.php';