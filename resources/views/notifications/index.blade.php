<x-app-layout>
    <x-slot name="header">
        <div class="page-head">
            <div>
                <h1 class="page-title">Notifications</h1>
                <p class="page-sub">{{ $nonLues }} non lue(s)</p>
            </div>
            <div class="page-actions">
                @if ($nonLues > 0)
                    <form method="POST" action="{{ route('notifications.tout-lire') }}">
                        @csrf
                        <button type="submit" class="btn btn-ghost"><x-icon name="check-double" /> Tout marquer comme lu</button>
                    </form>
                @endif

                <form method="POST" action="{{ route('notifications.vider') }}">
                    @csrf
                    <button type="submit" class="btn btn-ghost"><x-icon name="trash" /> Supprimer les événements lus</button>
                </form>
            </div>
        </div>
    </x-slot>

    @include('partials.messages')

    <div class="toolbar">
        <a href="{{ route('notifications.index') }}" class="btn {{ $filtre === 'toutes' ? 'btn-primary' : 'btn-ghost' }}">Toutes</a>
        <a href="{{ route('notifications.index', ['filtre' => 'non-lues']) }}" class="btn {{ $filtre === 'non-lues' ? 'btn-primary' : 'btn-ghost' }}">Non lues</a>
    </div>

    <div class="card notif-page" style="overflow:hidden">
        @forelse ($notifications as $notification)
            @php($d = $notification->data)

            <a href="{{ route('notifications.ouvrir', $notification->id) }}"
               class="bell-item {{ $notification->read_at ? '' : 'is-unread' }}">
                <span class="bell-ico bell-{{ $d['niveau'] ?? 'info' }}"><x-icon :name="$d['icone'] ?? 'bell'" /></span>
                <span class="bell-body">
                    <span class="bell-title">
                        {{ $d['titre'] ?? 'Notification' }}
                        <span class="badge {{ $notification->type === 'alerte' ? 'badge-yellow' : 'badge-gray' }}">
                            {{ $notification->type === 'alerte' ? 'Alerte' : 'Événement' }}
                        </span>
                    </span>
                    <span class="bell-msg">{{ $d['message'] ?? '' }}</span>
                    <span class="bell-time">{{ $notification->created_at->format('d/m/Y à H:i') }} · {{ $notification->created_at->diffForHumans() }}</span>
                </span>
            </a>
        @empty
            <div class="bell-empty">
                <x-icon name="bell" />
                {{ $filtre === 'non-lues' ? 'Aucune notification non lue.' : 'Aucune notification pour l\'instant.' }}
            </div>
        @endforelse
    </div>

    <p class="muted" style="font-size:.78rem;margin-top:.6rem">
        Les alertes (retards, stock bas, départs et retours proches) disparaissent d'elles-mêmes quand la situation est réglée.
        Les événements (ventes, annulations, locations) sont conservés 60 jours.
    </p>

    <div class="mt-4">{{ $notifications->links() }}</div>
</x-app-layout>