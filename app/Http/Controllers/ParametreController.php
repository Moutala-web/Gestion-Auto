<?php

namespace App\Http\Controllers;

use App\Models\Parametre;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ParametreController extends Controller
{
    public function edit(): View
    {
        $parametre = Parametre::first() ?? new Parametre(['devise' => 'FCFA']);

        return view('parametres.edit', compact('parametre'));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nom_entreprise' => ['required', 'string', 'max:150'],
            'telephone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'adresse' => ['nullable', 'string'],
            'devise' => ['required', 'string', 'max:10'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
            'supprimer_logo' => ['nullable', 'boolean'],
        ], [
            'nom_entreprise.required' => 'Le nom de l\'entreprise est obligatoire.',
            'logo.image' => 'Le logo doit être une image (JPG ou PNG).',
            'logo.max' => 'Le logo ne doit pas dépasser 2 Mo.',
        ]);

        $parametre = Parametre::first() ?? new Parametre();

        if ($request->hasFile('logo')) {
            $this->supprimerLogo($parametre);

            $fichier = $request->file('logo');
            $nom = 'logo-' . time() . '.' . $fichier->extension();
            $fichier->move(public_path('uploads'), $nom);

            $data['logo'] = 'uploads/' . $nom;
        } elseif ($request->boolean('supprimer_logo')) {
            $this->supprimerLogo($parametre);
            $data['logo'] = null;
        }

        unset($data['supprimer_logo']);

        $parametre->fill($data)->save();

        return redirect()->route('parametres.edit')->with('succes', 'Paramètres enregistrés.');
    }

    private function supprimerLogo(Parametre $parametre): void
    {
        if ($parametre->logo && is_file(public_path($parametre->logo))) {
            unlink(public_path($parametre->logo));
        }
    }
}