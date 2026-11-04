@extends('layouts.app')
@section('title', __('Nouvelle évaluation'))

@section('content')
<x-ui.page-header :title="__('Nouvelle évaluation')" />
<form method="POST" action="{{ route('evaluations.store') }}" class="ec-card max-w-2xl space-y-4 p-5">
    @csrf
    @if(auth()->user()->isSuperAdmin())
        <div>
            <label class="ec-label">{{ __('Centre') }}</label>
            <select name="center_id" class="ec-select" required>
                @foreach($centers as $c)
                    <option value="{{ $c->id }}" @selected($centerId==$c->id)>{{ $c->translated('name') }}</option>
                @endforeach
            </select>
        </div>
    @endif
    <div>
        <label class="ec-label" for="class_id">{{ __('Classe') }} <span class="text-red-600">*</span></label>
        <select id="class_id" name="class_id" class="ec-select @error('class_id') is-invalid @enderror" required>
            <option value="">{{ __('Sélectionner') }}</option>
            @foreach($classes as $class)
                <option value="{{ $class->id }}" @selected(old('class_id')==$class->id)>{{ $class->perimeter_label }}</option>
            @endforeach
        </select>
        @error('class_id')<p class="ec-error">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="ec-label" for="title">{{ __('Titre') }} <span class="text-red-600">*</span></label>
        <input id="title" name="title" class="ec-input" value="{{ old('title') }}" required>
        @error('title')<p class="ec-error">{{ $message }}</p>@enderror
    </div>
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="ec-label">{{ __('Type') }}</label>
            <select name="type" class="ec-select" required>
                @foreach(['exam'=>'Examen','control'=>'Contrôle','homework'=>'Devoir','oral'=>'Oral','project'=>'Projet','participation'=>'Participation'] as $k=>$v)
                    <option value="{{ $k }}" @selected(old('type')===$k)>{{ __($v) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="ec-label">{{ __('Date') }}</label>
            <input type="date" name="evaluation_date" class="ec-input" value="{{ old('evaluation_date', now()->toDateString()) }}" required>
        </div>
        <div>
            <label class="ec-label">{{ __('Note max') }}</label>
            <input type="number" step="0.01" name="max_grade" class="ec-input" value="{{ old('max_grade', 20) }}" required>
        </div>
        <div>
            <label class="ec-label">{{ __('Coefficient') }}</label>
            <input type="number" step="0.1" name="coefficient" class="ec-input" value="{{ old('coefficient', 1) }}" required>
        </div>
    </div>
    <div>
        <label class="ec-label">{{ __('Statut') }}</label>
        <select name="status" class="ec-select" required>
            <option value="draft">{{ __('Brouillon') }}</option>
            <option value="published">{{ __('Publiée') }}</option>
            <option value="closed">{{ __('Clôturée') }}</option>
        </select>
    </div>
    <div>
        <label class="ec-label">{{ __('Description') }}</label>
        <textarea name="description" class="ec-textarea" rows="3">{{ old('description') }}</textarea>
    </div>
    <button class="ec-btn-primary">{{ __('Créer') }}</button>
</form>
@endsection
