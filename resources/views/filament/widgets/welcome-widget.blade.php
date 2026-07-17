<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex items-center justify-between gap-x-4">
            <div>
                <h2 class="text-lg font-bold tracking-tight">
                    {{ $this->getGreeting() }}, {{ auth()->user()->name }}
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    @if ($role = $this->getRoleLabel())
                        Here's your {{ $role }} snapshot for {{ now()->format('l, F j') }}.
                    @else
                        Here's what's happening today, {{ now()->format('l, F j') }}.
                    @endif
                </p>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
