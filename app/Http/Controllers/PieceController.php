<?php

namespace App\Http\Controllers;

use App\Models\Achat;
use App\Models\Piece;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PieceController extends Controller
{
    public function index(Request $request): View
    {
        $recherche = trim((string) $request->query('q', ''));
        $alerte = $request->boolean('alerte');
        $categorie = $request->query('categorie');
        $famille = in_array($request->query('famille'), array_keys(Piece::FAMILLES), true) ? $request->query('famille') : null;

        $pieces = Piece::query()
            ->when($famille, fn ($q) => $q->where('famille', $famille))
            ->when($categorie, fn ($q) => $q->where('categorie', $categorie))
            ->when($recherche !== '', function ($query) use ($recherche) {
                $query->where(function ($q) use ($recherche) {
                    $q->where('reference', 'like', "%{$recherche}%")
                        ->orWhere('designation', 'like', "%{$recherche}%")
                        ->orWhere('categorie', 'like', "%{$recherche}%")
                        ->orWhere('marque', 'like', "%{$recherche}%");
                });
            })
            ->when($alerte, fn ($query) => $query->enAlerte()) // stock au niveau d'alerte ou en dessous
            ->orderBy('designation')
            ->paginate(15)
            ->withQueryString();

        // Nombre d'articles dans chaque onglet
        $compteurs = [
            '' => Piece::count(),
            'piece' => Piece::where('famille', 'piece')->count(),
            'divers' => Piece::where('famille', 'divers')->count(),
        ];

        // Catégories pour le filtre (de la famille affichée)
        $categoriesFiltre = Piece::query()
            ->when($famille, fn ($q) => $q->where('famille', $famille))
            ->whereNotNull('categorie')->where('categorie', '!=', '')
            ->distinct()->orderBy('categorie')->pluck('categorie');

        return view('pieces.index', compact('pieces', 'recherche', 'alerte', 'famille', 'categorie', 'compteurs', 'categoriesFiltre'));
    }

    public function show(Piece $piece): View
    {
        $mouvements = $piece->mouvements()
            ->with('user')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15);

        return view('pieces.show', compact('piece', 'mouvements'));
    }

    // Une pièce naît d'un achat et son stock n'évolue que par achat, vente ou sortie.
    // On ne modifie ici que ce qui lui est propre : prix de vente, seuil d'alerte, emplacement, description.
    public function update(Request $request, Piece $piece): RedirectResponse
    {
        $data = $request->validate([
            'prix_vente' => ['required', 'numeric', 'min:0'],
            'seuil_alerte' => ['nullable', 'integer', 'min:0'],
            'emplacement' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
        ]);

        $data['seuil_alerte'] = $data['seuil_alerte'] ?? 0;

        $piece->update($data);

        return back()->with('succes', 'Modifications enregistrées.');
    }

    // On ne supprime jamais un article qui a un historique
    public function destroy(Piece $piece): RedirectResponse
    {
        if ($piece->mouvements()->exists()
            || $piece->venteDetails()->exists()
            || Achat::where('piece_id', $piece->id)->exists()) {
            return redirect()->route('pieces.index', ['famille' => $piece->famille])
                ->with('erreur', 'Cet article a un historique (stock, achats ou ventes) : suppression impossible.');
        }

        $famille = $piece->famille;
        $piece->delete();

        return redirect()->route('pieces.index', ['famille' => $famille])->with('succes', 'Article supprimé.');
    }
}