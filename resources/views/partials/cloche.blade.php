<div class="bell" x-data="{ ouvert: false }" x-on:click.outside="ouvert = false" x-on:keydown.escape.window="ouvert = false">
    <button type="button" class="bell-btn" x-on:click="ouvert = ! ouvert" aria-label="Notifications">
        <x-icon name="bell" />
        @if ($nonLues > 0)
            <span class="bell-count">{{ $nonLues > 99 ? '99+' : $nonLues }}</span>
        @endif
    </button>

    <div class="bell-panel" x-show="ouvert" x-cloak x-transition.opacity.duration.150ms>
        <div class="bell-head">
            <strong>Notifications</strong>

            @if ($nonLues > 0)
                <form method="POST" action="{{ route('notifications.tout-lire') }}">
                    @csrf
                    <button type="submit" class="bell-link"><x-icon name="check-double" /> Tout marquer comme lu</button>
                </form>
            @endif
        </div>

        <div class="bell-list">
            @forelse ($notifications as $notification)
                @php($d = $notification->data)

                <a href="{{ route('notifications.ouvrir', $notification->id) }}"
                   class="bell-item {{ $notification->read_at ? '' : 'is-unread' }}">
                    <span class="bell-ico bell-{{ $d['niveau'] ?? 'info' }}"><x-icon :name="$d['icone'] ?? 'bell'" /></span>
                    <span class="bell-body">
                        <span class="bell-title">{{ $d['titre'] ?? 'Notification' }}</span>
                        <span class="bell-msg">{{ $d['message'] ?? '' }}</span>
                        <span class="bell-time">{{ $notification->created_at->diffForHumans() }}</span>
                    </span>
                </a>
            @empty
                <div class="bell-empty">
                    <x-icon name="bell" />
                    Aucune notification
                </div>
            @endforelse
        </div>

        <a href="{{ route('notifications.index') }}" class="bell-foot">Voir toutes les notifications</a>
    </div>
</div>