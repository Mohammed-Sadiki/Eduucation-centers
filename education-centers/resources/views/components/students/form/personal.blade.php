@props(['f' => []])

@php
    // Mode création : $student = null → tous les champs sont vides.
    // Mode modification : old() garde la saisie en cas d'erreur, la valeur
    // enregistrée sert de préremplissage.
    $student = $f['student'] ?? null;
@endphp

<x-students.form.card num="1" icon="user" :title="__('Informations personnelles')">
    <div class="grid gap-5 p-6 sm:grid-cols-2">
        <div>
            <label for="last_name" class="ec-label text-base font-semibold">{{ __('Nom') }} <span class="text-red-500">*</span></label>
            <input type="text" id="last_name" name="last_name" class="ec-input text-base @error('last_name') is-invalid @enderror"
                   value="{{ old('last_name', $student?->last_name) }}" required autocomplete="family-name">
            @error('last_name') <p class="ec-error text-sm">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="first_name" class="ec-label text-base font-semibold">{{ __('Prénom') }} <span class="text-red-500">*</span></label>
            <input type="text" id="first_name" name="first_name" class="ec-input text-base @error('first_name') is-invalid @enderror"
                   value="{{ old('first_name', $student?->first_name) }}" required autocomplete="given-name">
            @error('first_name') <p class="ec-error text-sm">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="date_of_birth" class="ec-label text-base font-semibold">{{ __('Date de naissance') }}</label>
            <input type="date" id="date_of_birth" name="date_of_birth" class="ec-input text-base @error('date_of_birth') is-invalid @enderror"
                   value="{{ old('date_of_birth', $student?->date_of_birth?->format('Y-m-d')) }}">
            @error('date_of_birth') <p class="ec-error text-sm">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="nationality" class="ec-label text-base font-semibold">{{ __('Nationalité') }}</label>
            <input type="text" id="nationality" name="nationality" class="ec-input text-base @error('nationality') is-invalid @enderror"
                   value="{{ old('nationality', $student?->nationality) }}">
            @error('nationality') <p class="ec-error text-sm">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="phone" class="ec-label text-base font-semibold">{{ __('Numéro de téléphone') }}</label>
            <input type="tel" id="phone" name="phone" class="ec-input text-base @error('phone') is-invalid @enderror"
                   value="{{ old('phone', $student?->phone) }}" placeholder="+212 XXXXXXXXX">
            @error('phone') <p class="ec-error text-sm">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="whatsapp" class="ec-label text-base font-semibold">{{ __('Numéro de téléphone WhatsApp') }}</label>
            <input type="tel" id="whatsapp" name="whatsapp" class="ec-input text-base @error('whatsapp') is-invalid @enderror"
                   value="{{ old('whatsapp', $student?->whatsapp) }}" placeholder="+212 XXXXXXXXX">
            @error('whatsapp') <p class="ec-error text-sm">{{ $message }}</p> @enderror
        </div>
        <div class="sm:col-span-2">
            <label for="emergency_contact_phone" class="ec-label text-base font-semibold">{{ __('Numéro de téléphone d\'urgence') }}</label>
            <input type="tel" id="emergency_contact_phone" name="emergency_contact_phone" class="ec-input text-base @error('emergency_contact_phone') is-invalid @enderror"
                   value="{{ old('emergency_contact_phone', $student?->emergency_contact_phone) }}" placeholder="+212 XXXXXXXXX">
            @error('emergency_contact_phone') <p class="ec-error text-sm">{{ $message }}</p> @enderror
        </div>
    </div>
</x-students.form.card>
