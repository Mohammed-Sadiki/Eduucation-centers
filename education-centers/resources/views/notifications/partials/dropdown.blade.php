@php
    $svc = app(\App\Services\NotificationService::class);
    $unreadCount = $svc->getUnreadCount($user);
    $recentList = \App\Models\AppNotification::where('user_id', $user->id)
        ->latest()
        ->take((int) config('notifications.dropdown_limit', 8))
        ->get();
@endphp

<div data-notif-body data-unread="{{ $unreadCount }}"
     class="absolute end-0 mt-2 w-80 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg dark:border-slate-700 dark:bg-slate-900"
     role="menu" aria-label="{{ __('Notifications') }}">
    {{-- En-tête : titre + compteur + action globale --}}
    <div class="flex items-center justify-between gap-2 border-b border-slate-100 px-4 py-2.5 dark:border-slate-800">
        <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">
            {{ __('Notifications') }}
            @if($unreadCount > 0)
                <span class="ms-1 inline-flex items-center justify-center rounded-full bg-red-600 px-1.5 py-0.5 text-2xs font-bold text-white">{{ $unreadCount }}</span>
            @endif
        </p>
        @if($unreadCount > 0)
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                @method('PATCH')
                <button type="submit" class="text-xs font-medium text-brand-700 hover:underline dark:text-brand-300">{{ __('Tout marquer comme lu') }}</button>
            </form>
        @endif
    </div>

    {{-- Liste --}}
    <ul class="max-h-80 overflow-y-auto">
        @forelse($recentList as $n)
            <li class="border-b border-slate-50 px-4 py-3 text-sm last:border-0 dark:border-slate-800 {{ $n->isRead() ? '' : 'bg-brand-50/40 dark:bg-brand-500/5' }}">
                <div class="flex items-start gap-3">
                    <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-300">
                        <x-icon :name="\App\Models\AppNotification::iconForType($n->type)" class="h-4 w-4" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <a href="{{ $n->link ?? route('notifications.index') }}"
                           class="block {{ $n->isRead() ? 'font-medium text-slate-600 dark:text-slate-300' : 'font-semibold text-slate-900 dark:text-white' }}">
                            <span class="line-clamp-1">{{ $n->title }}</span>
                            <span class="mt-0.5 line-clamp-2 block text-xs font-normal text-slate-500 dark:text-slate-400">{{ $n->message }}</span>
                            <span class="mt-1 block text-xs text-slate-400">{{ $n->created_at?->diffForHumans() }}</span>
                        </a>
                        @unless($n->isRead())
                            <form method="POST" action="{{ route('notifications.read', $n) }}" class="mt-1">
                                @csrf @method('PATCH')
                                <button type="submit" class="text-xs font-medium text-brand-700 hover:underline dark:text-brand-300">{{ __('Marquer comme lu') }}</button>
                            </form>
                        @endunless
                    </div>
                    @unless($n->isRead())
                        <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-brand-500" aria-hidden="true"></span>
                    @endunless
                </div>
            </li>
        @empty
            <li class="px-4 py-8 text-center text-sm text-slate-500 dark:text-slate-400">{{ __('Aucune notification pour le moment.') }}</li>
        @endforelse
    </ul>

    {{-- Pied : page complète --}}
    <a href="{{ route('notifications.index') }}" class="block border-t border-slate-100 bg-slate-50 px-4 py-2 text-center text-xs font-medium text-brand-700 hover:bg-slate-100 dark:border-slate-800 dark:bg-slate-800 dark:text-brand-300 dark:hover:bg-slate-700">
        {{ __('Voir toutes les notifications') }}
    </a>
</div>
