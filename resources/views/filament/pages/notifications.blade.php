{{--
    Notifications feed — Admin Screen 6.
    Backed by App\Filament\Pages\Notifications (markRead / markAllRead
    Livewire actions).
--}}
<x-filament-panels::page>
    @php
        $events = $this->getEvents();
        $unreadCount = $events->whereNull('read_at')->count();
    @endphp

    @if ($unreadCount > 0)
        <div class="flex justify-end -mt-2 mb-2">
            <button
                type="button"
                wire:click="markAllRead"
                class="text-xs font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400"
            >
                Mark all as read ({{ $unreadCount }})
            </button>
        </div>
    @endif

    @if ($events->isEmpty())
        <div class="rounded-xl border border-dashed border-gray-300 bg-white p-12 text-center dark:border-gray-700 dark:bg-gray-900">
            <x-filament::icon icon="heroicon-o-bell-slash" class="mx-auto h-10 w-10 text-gray-400"/>
            <h3 class="mt-4 text-base font-semibold text-gray-900 dark:text-gray-100">No notifications yet</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Crawl completions, failures, and storage warnings will appear here.
            </p>
        </div>
    @else
        <ul class="divide-y divide-gray-100 rounded-xl border border-gray-200 bg-white dark:divide-gray-800 dark:border-gray-800 dark:bg-gray-900">
            @foreach ($events as $event)
                @php
                    // Dot color by severity level.
                    $dotColor = match ($event->level->value) {
                        'error'   => 'bg-rose-500',
                        'warning' => 'bg-amber-500',
                        default   => 'bg-emerald-500',
                    };
                    $isRead = $event->read_at !== null;
                @endphp

                <li
                    wire:click="markRead({{ $event->id }})"
                    class="flex cursor-pointer items-start gap-3 p-4 transition hover:bg-gray-50 dark:hover:bg-gray-800/50
                           {{ $isRead ? 'opacity-60' : '' }}"
                >
                    <span class="mt-1.5 inline-block h-2 w-2 shrink-0 rounded-full {{ $dotColor }}"></span>

                    <div class="min-w-0 flex-1">
                        <p class="text-sm {{ $isRead ? 'text-gray-600 dark:text-gray-400' : 'font-medium text-gray-900 dark:text-gray-100' }}">
                            {{ $event->message }}
                        </p>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                            {{ $event->created_at->format('M j, Y · g:i A') }}
                            @if ($event->site)
                                · <a
                                    href="{{ \App\Filament\Resources\SiteResource::getUrl('edit', ['record' => $event->site]) }}"
                                    class="text-primary-600 hover:underline dark:text-primary-400"
                                    wire:click.stop
                                >{{ $event->site->name }}</a>
                            @endif
                        </p>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</x-filament-panels::page>
