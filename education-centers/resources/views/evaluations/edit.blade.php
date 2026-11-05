@extends('layouts.app')
@section('title', __('Modifier l’évaluation'))

@section('content')
<x-ui.page-header :title="__('Modifier l’évaluation')" :description="$evaluation->title" />
<form method="POST" action="{{ route('evaluations.update', $evaluation) }}" class="ec-card max-w-2xl space-y-4 p-5">
    @csrf
    @method('PUT')
    <div>
        <label class="ec-label">{{ __('Titre') }}</label>
        <input name="title" class="ec-input" value="{{ old('title', $evaluation->title) }}" required>
    </div>
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="ec-label">{{ __('Type') }}</label>
            <select name="type" class="ec-select">
                @foreach(['exam'=>'Examen','control'=>'Contrôle','homework'=>'Devoir','oral'=>'Oral','project'=>'Projet','participation'=>'Participation'] as $k=>$v)
                    <option value="{{ $k }}" @selected(old('type', $evaluation->type)===$k)>{{ __($v) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="ec-label">{{ __('Modalité') }}</label>
            <select name="modality" class="ec-select">
                <option value="in_person" @selected(old('modality', $evaluation->modality ?? 'in_person')==='in_person')>{{ __('Présentiel') }}</option>
                <option value="online" @selected(old('modality', $evaluation->modality)==='online')>{{ __('À distance') }}</option>
            </select>
            @error('modality')<p class="ec-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="ec-label">{{ __('Date') }}</label>
            <input type="date" name="evaluation_date" class="ec-input" value="{{ old('evaluation_date', optional($evaluation->evaluation_date)->format('Y-m-d')) }}">
        </div>
        <div>
            <label class="ec-label">{{ __('Note max') }}</label>
            <input type="number" step="0.01" name="max_grade" class="ec-input" value="{{ old('max_grade', $evaluation->max_grade) }}">
        </div>
        <div>
            <label class="ec-label">{{ __('Coefficient') }}</label>
            <input type="number" step="0.1" name="coefficient" class="ec-input" value="{{ old('coefficient', $evaluation->coefficient) }}">
        </div>
    </div>
    <div>
        <label class="ec-label">{{ __('Statut') }}</label>
        <select name="status" class="ec-select">
            @foreach(['draft'=>'Brouillon','published'=>'Publiée','closed'=>'Clôturée'] as $k=>$v)
                <option value="{{ $k }}" @selected(old('status', $evaluation->status)===$k)>{{ __($v) }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="ec-label">{{ __('Description') }}</label>
        <textarea name="description" class="ec-textarea" rows="3">{{ old('description', $evaluation->description) }}</textarea>
    </div>
    <div class="flex gap-2">
        <a href="{{ route('evaluations.show', $evaluation) }}" class="ec-btn-secondary">{{ __('Annuler') }}</a>
        <button class="ec-btn-primary">{{ __('Enregistrer') }}</button>
    </div>
</form>
@endsection
