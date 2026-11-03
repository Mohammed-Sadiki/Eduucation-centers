@props([
    'cols' => 2,
])

{{--
    Grille responsive pour les sections de formulaires administratifs.
    - Desktop (lg+)  : 2 colonnes
    - Tablette (md)  : 2 colonnes (si le contenu le permet)
    - Mobile         : 1 colonne

    Utilisation :
        <x-ui.form-grid>
            <x-ui.form-section>Nom court</x-ui.form-section>
            <x-ui.form-section>Nom court</x-ui.form-section>
            <x-ui.form-section span="full">Section longue (span 2)</x-ui.form-section>
        </x-ui.form-grid>
--}}
<div {{ $attributes->merge(['class' => 'grid grid-cols-1 gap-6 ' . ($cols === 2 ? 'lg:grid-cols-2' : '')]) }}>
    {{ $slot }}
</div>
