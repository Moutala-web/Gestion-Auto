@if (old('_modal') === $modal && $errors->any())
    <div class="flash flash-error" style="align-items:flex-start">
        <x-icon name="warning" />
        <ul>
            @foreach ($errors->all() as $erreur)
                <li>{{ $erreur }}</li>
            @endforeach
        </ul>
    </div>
@endif