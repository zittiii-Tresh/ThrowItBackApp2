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
        {{--
            Each notification is its own rounded card instead of a flush list —
            hover lights up the border + adds a ring, no background fill,
            so text contrast stays the same in both themes.
        --}}
        <ul class="space-y-2">
            @foreach ($events as $event)
                @php
                    // Dot color by severity level.
                    $dotColor = match ($event->level->value) {
                        'error'   => 'bg-rose-500',
                        'warning' => 'bg-amber-500',
                        default   => 'bg-emerald-500',
                    };
                    // Matching glow color on hover — ring-*-300 picks up the level.
                    $ringColor = match ($event->level->value) {
                        'error'   => 'hover:ring-rose-300 dark:hover:ring-rose-700 hover:border-rose-300 dark:hover:border-rose-700',
                        'warning' => 'hover:ring-amber-300 dark:hover:ring-amber-700 hover:border-amber-300 dark:hover:border-amber-700',
                        default   => 'hover:ring-emerald-300 dark:hover:ring-emerald-700 hover:border-emerald-300 dark:hover:border-emerald-700',
                    };
                    $isRead = $event->read_at !== null;
                @endphp

                <li
                    wire:click="markRead({{ $event->id }})"
                    class="group flex cursor-pointer items-start gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition
                           hover:ring-2 hover:ring-offset-0 hover:shadow-md
                           dark:border-gray-800 dark:bg-gray-900
                           {{ $ringColor }}"
                >
                    <span class="mt-1.5 inline-block h-2 w-2 shrink-0 rounded-full {{ $dotColor }} {{ $isRead ? 'opacity-50' : '' }}"></span>

                    <div class="min-w-0 flex-1">
                        <p @class([
                            'text-sm',
                            'font-medium text-gray-900 dark:text-gray-100' => ! $isRead,
                            'text-gray-500 dark:text-gray-400'             => $isRead,
                        ])>
                            {{ $event->message }}
                        </p>
                        <p @class([
                            'mt-0.5 text-xs',
                            'text-gray-500 dark:text-gray-400' => ! $isRead,
                            'text-gray-400 dark:text-gray-500' => $isRead,
                        ])>
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
