@extends('layouts.app')
@section('title', __('Évaluations'))

@section('content')
<x-ui.page-header :title="__('Évaluations & notes')" :description="__('Contrôles, examens et moyennes.')">
    @can('create', \App\Models\Evaluation::class)
        <a href="{{ route('evaluations.create') }}" class="ec-btn-primary"><x-icon name="plus" class="h-4 w-4" /> {{ __('Nouvelle évaluation') }}</a>
    @endcan
</x-ui.page-header>
<x-ui.card class="mb-4">
    <form method="GET" class="grid gap-3 md:grid-cols-3">
        {{-- Filtre de classe masqué quand une seule classe est attribuable. --}}
        @if($classes->count() > 1)
            <select name="class_id" class="ec-select">
                <option value="">{{ auth()->user()->isTeacher() ? __('Toutes mes classes') : __('Toutes les classes') }}</option>
                @foreach($classes as $class)
                    <option value="{{ $class->id }}" @selected(request('class_id')==$class->id)>{{ $class->perimeter_label }}</option>
                @endforeach
            </select>
        @endif
        <select name="type" class="ec-select">
            <option value="">{{ __('Tous les types') }}</option>
            @foreach(['exam'=>'Examen','control'=>'Contrôle','homework'=>'Devoir','oral'=>'Oral','project'=>'Projet','participation'=>'Participation'] as $k=>$v)
                <option value="{{ $k }}" @selected(request('type')===$k)>{{ __($v) }}</option>
            @endforeach
        </select>
        <button class="ec-btn-secondary">{{ __('Filtrer') }}</button>
    </form>
</x-ui.card>
<div class="ec-table-wrap">
    <table class="ec-table">
        <thead><tr><th>{{ __('Titre') }}</th><th>{{ __('Classe') }}</th><th>{{ __('Type') }}</th><th>{{ __('Date') }}</th><th>{{ __('Statut') }}</th><th></th></tr></thead>
        <tbody>
            @forelse($evaluations as $evaluation)
                <tr>
                    <td class="font-medium">{{ $evaluation->title }}</td>
                    <td>{{ $evaluation->classRoom?->perimeter_label }}</td>
                    <td>{{ $evaluation->type_label }}</td>
                    <td>{{ format_date($evaluation->evaluation_date, 'd/m/Y') }}</td>
                    <td><x-status-badge type="evaluation" :status="$evaluation->status" /></td>
                    <td class="text-end">
                        @unless(auth()->user()->isStudent())
                            <a href="{{ route('evaluations.show', $evaluation) }}" class="ec-btn-ghost ec-btn-sm">{{ __('Notes') }}</a>
                        @endunless
                    </td>
                </tr>
            @empty
                <tr><td colspan="6"><x-ui.empty :title="__('Aucune évaluation')" /></td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@if($evaluations->hasPages())<div class="mt-4">{{ $evaluations->links() }}</div>@endif
@endsection
