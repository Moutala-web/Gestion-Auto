@foreach (['succes' => ['flash-success', 'check-circle'], 'erreur' => ['flash-error', 'warning']] as $cle => [$classe, $icone])
    @if (session($cle))
        <div x-data="{ ouvert: true }" x-init="setTimeout(() => ouvert = false, 6000)" x-show="ouvert" x-transition.opacity
             class="flash {{ $classe }}">
            <x-icon :name="$icone" />
            <span>{{ session($cle) }}</span>
        </div>
    @endif
@endforeach