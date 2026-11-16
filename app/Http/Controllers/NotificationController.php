<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $utilisateur = $request->user();
        $filtre = $request->query('filtre') === 'non-lues' ? 'non-lues' : 'toutes';

        $requete = $filtre === 'non-lues' ? $utilisateur->unreadNotifications() : $utilisateur->notifications();

        $notifications = $requete->latest()->paginate(20)->withQueryString();
        $nonLues = $utilisateur->unreadNotifications()->count();

        return view('notifications.index', compact('notifications', 'filtre', 'nonLues'));
    }

    // Ouvre la page concernée et marque la notification comme lue
    public function ouvrir(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return redirect($notification->data['url'] ?? route('notifications.index', [], false));
    }

    public function toutLire(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }

    // Seuls les événements lus sont supprimés : une alerte disparaît d'elle-même quand la situation est réglée
    public function vider(Request $request): RedirectResponse
    {
        $request->user()->notifications()
            ->where('type', 'evenement')
            ->whereNotNull('read_at')
            ->delete();

        return back()->with('succes', 'Les événements lus ont été supprimés.');
    }
}