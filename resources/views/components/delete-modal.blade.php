@props([
    'action',
    'titre' => 'Supprimer cet élément ?',
    'message' => 'Cette action est définitive.',
])

@php($nom = 'supprimer-' . substr(md5($action), 0, 12))

<span x-data class="inline-flex">
    <button type="button" title="Supprimer" x-on:click.prevent="$dispatch('open-modal', '{{ $nom }}')" class="icon-btn icon-btn-danger">
        <x-icon name="trash" />
    </button>

    <x-modal :name="$nom" maxWidth="md">
        <form method="POST" action="{{ $action }}" class="p-6 text-center">
            @csrf
            @method('DELETE')

            <div class="confirm-icon"><x-icon name="trash" /></div>
            <h2 class="modal-title" style="margin-bottom:.5rem">{{ $titre }}</h2>
            <p class="muted" style="font-size:.9rem">{{ $message }}</p>

            <div class="mt-6 flex justify-center gap-3">
                <x-secondary-button x-on:click="$dispatch('close')">Annuler</x-secondary-button>
                <button type="submit" class="btn btn-danger">Supprimer</button>
            </div>
        </form>
    </x-modal>
</span>