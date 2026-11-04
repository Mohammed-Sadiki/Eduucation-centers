@extends('layouts.app')
@section('title', __('Notifications'))

@php
    $filters = [
        'all'    => __('Toutes'),
        'unread' => __('Non lues'),
        'read'   => __('Lues'),
    ];
@endphp

@section('content')
<x-ui.page-header :title="__('Notifications')" :description="__('Événements et alertes destinés à votre compte.')">
    @if($unreadCount > 0)
        <form method="POST" action="{{ route('notifications.read-all') }}">
            @csrf
            @method('PATCH')
            <button class="ec-btn-secondary">{{ __('Tout marquer comme lu') }}</button>
        </form>
    @endif
</x-ui.page-header>

{{-- Filtres Toutes / Non lues / Lues --}}
<nav class="mb-4 flex flex-wrap items-center gap-2" aria-label="{{ __('Filtrer les notifications') }}">
    @foreach($filters as $key => $label)
        <a href="{{ route('notifications.index', ['filter' => $key]) }}"
           class="rounded-full px-3.5 py-1.5 text-sm font-medium transition
                  {{ $filter === $key
                        ? 'bg-brand-600 text-white shadow-2xs'
                        : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700 dark:hover:bg-slate-700' }}">
            {{ $label }}
            @if($key === 'unread' && $unreadCount > 0)
                <span class="ms-1 inline-flex items-center justify-center rounded-full bg-red-600 px-1.5 text-xs font-bold text-white">{{ $unreadCount }}</span>
            @endif
        </a>
    @endforeach
</nav>

<div class="space-y-3">
    @forelse($notifications as $n)
        <article class="ec-card flex items-start justify-between gap-4 p-4 {{ $n->isRead() ? '' : 'border-brand-200 bg-brand-50/40 dark:bg-brand-500/5' }}">
            <div class="flex items-start gap-3">
                <span class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-300">
                    <x-icon :name="\App\Models\AppNotification::iconForType($n->type)" class="h-5 w-5" />
                </span>
                <div class="min-w-0">
                    @if($n->link)
                        <a href="{{ $n->link }}" class="font-medium text-slate-900 hover:underline dark:text-white">{{ $n->title }}</a>
                    @else
                        <p class="font-medium text-slate-900 dark:text-white">{{ $n->title }}</p>
                    @endif
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ $n->message }}</p>
                    <p class="mt-2 text-xs text-slate-400">{{ format_date($n->created_at, 'd/m/Y H:i') }} · {{ $n->created_at?->diffForHumans() }} · {{ $n->isRead() ? __('Lue') : __('Non lue') }}</p>
                </div>
            </div>
            <div class="flex shrink-0 gap-2">
                @unless($n->isRead())
                    <form method="POST" action="{{ route('notifications.read', $n) }}">
                        @csrf @method('PATCH')
                        <button class="ec-btn-ghost ec-btn-sm">{{ __('Marquer comme lu') }}</button>
                    </form>
                @endunless
                <form method="POST" action="{{ route('notifications.destroy', $n) }}">
                    @csrf @method('DELETE')
                    <button class="ec-btn-danger ec-btn-sm">{{ __('Supprimer') }}</button>
                </form>
            </div>
        </article>
    @empty
        <x-ui.empty :title="__('Aucune notification pour le moment.')" />
    @endforelse
</div>

@if($notifications->hasPages())<div class="mt-4">{{ $notifications->links() }}</div>@endif
@endsection
