<x-layouts.app :title="__('Calendar Package Sandbox')">
    @php
        $monthStart = today()->startOfMonth();
        $configuredFirstDay = config('calendar.first_day_of_week', 'Monday');
        $firstDayOfWeek = is_int($configuredFirstDay)
            ? $configuredFirstDay
            : match (strtolower((string) $configuredFirstDay)) {
                'sunday' => \Carbon\Carbon::SUNDAY,
                'monday' => \Carbon\Carbon::MONDAY,
                'tuesday' => \Carbon\Carbon::TUESDAY,
                'wednesday' => \Carbon\Carbon::WEDNESDAY,
                'thursday' => \Carbon\Carbon::THURSDAY,
                'friday' => \Carbon\Carbon::FRIDAY,
                'saturday' => \Carbon\Carbon::SATURDAY,
                default => \Carbon\Carbon::MONDAY,
            };
        $lastDayOfWeek = ($firstDayOfWeek + 6) % 7;
        $cursor = $monthStart->copy()->startOfWeek($firstDayOfWeek);
        $monthEnd = $monthStart->copy()->endOfMonth()->endOfWeek($lastDayOfWeek);
        $days = [];

        while ($cursor->lessThanOrEqualTo($monthEnd)) {
            $days[] = [
                'date' => $cursor->toDateString(),
                'label' => $cursor->format('j'),
                'in_current_period' => $cursor->month === $monthStart->month,
            ];
            $cursor = $cursor->addDay();
        }

        $events = [
            ['id' => 1, 'date' => $monthStart->copy()->addDays(1)->toDateString(), 'starts_at' => $monthStart->copy()->addDays(1)->setTime(9, 0)->toDateTimeString(), 'ends_at' => $monthStart->copy()->addDays(1)->setTime(11, 0)->toDateTimeString(), 'label' => 'Equipment delivery'],
            ['id' => 2, 'date' => $monthStart->copy()->addDays(8)->toDateString(), 'starts_at' => $monthStart->copy()->addDays(8)->setTime(13, 0)->toDateTimeString(), 'ends_at' => $monthStart->copy()->addDays(8)->setTime(15, 30)->toDateTimeString(), 'label' => 'Customer collection'],
            ['id' => 3, 'date' => $monthStart->copy()->addDays(15)->toDateString(), 'starts_at' => $monthStart->copy()->addDays(15)->setTime(10, 30)->toDateTimeString(), 'ends_at' => $monthStart->copy()->addDays(15)->setTime(12, 0)->toDateTimeString(), 'label' => 'Maintenance window'],
        ];

        $plannerStart = today()->startOfDay();
        $plannerEvents = [
            [
                'id' => 1,
                'title' => 'JOB-001 — Equipment delivery',
                'subtitle' => 'Delivery window',
                'description' => 'Athens',
                'badges' => ['Confirmed'],
                'start' => $plannerStart->copy()->addDay()->toDateString(),
                'end' => $plannerStart->copy()->addDays(3)->toDateString(),
                'colour' => '#2563eb',
                'segments' => [
                    ['start' => $plannerStart->copy()->addDay()->toDateString(), 'end' => $plannerStart->copy()->addDay()->toDateString(), 'type' => 'delivery', 'label' => 'Del', 'colour' => 'rgba(255,255,255,0.35)'],
                    ['start' => $plannerStart->copy()->addDays(2)->toDateString(), 'end' => $plannerStart->copy()->addDays(3)->toDateString(), 'type' => 'in-use', 'label' => 'Use', 'colour' => 'rgba(255,255,255,0.18)'],
                ],
                'meta' => ['location' => 'Athens'],
            ],
            [
                'id' => 2,
                'title' => 'JOB-002 — Customer collection',
                'subtitle' => 'Collection window',
                'description' => 'Piraeus',
                'badges' => ['Draft'],
                'start' => $plannerStart->copy()->addDays(5)->toDateString(),
                'end' => $plannerStart->copy()->addDays(7)->toDateString(),
                'colour' => '#0f766e',
                'segments' => [
                    ['start' => $plannerStart->copy()->addDays(5)->toDateString(), 'end' => $plannerStart->copy()->addDays(6)->toDateString(), 'type' => 'in-use', 'label' => 'Use', 'colour' => 'rgba(255,255,255,0.18)'],
                    ['start' => $plannerStart->copy()->addDays(7)->toDateString(), 'end' => $plannerStart->copy()->addDays(7)->toDateString(), 'type' => 'collection', 'label' => 'Coll', 'colour' => 'rgba(255,255,255,0.42)'],
                ],
                'meta' => ['location' => 'Piraeus'],
            ],
        ];
    @endphp

    <div class="flex w-full flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8">
        <div>
            <flux:heading size="xl">{{ __('Calendar Package Sandbox') }}</flux:heading>
            <flux:text class="mt-2">
                {{ __('Visual progress page for the reusable Blade and Livewire calendar package.') }}
            </flux:text>
        </div>

        <div class="flex flex-col gap-6">
            <section class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('Plain Blade') }}</flux:heading>
                <flux:text class="mt-1 mb-4">{{ __('Anonymous package view.') }}</flux:text>

                <x-calendar::timeline :days="$days" :events="$events" view="month" />
            </section>

            <section class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('Livewire') }}</flux:heading>
                <flux:text class="mt-1 mb-4">{{ __('Interactive day, week, month and year views.') }}</flux:text>

                <livewire:calendar :events="$events" />
            </section>

            <section class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <flux:heading size="lg">{{ __('Original application calendar grid') }}</flux:heading>
                        <flux:text class="mt-1">{{ __('The production calendar grid kept here for direct comparison with the package.') }}</flux:text>
                    </div>
                    <a href="{{ route('calendar.index') }}" wire:navigate.hover class="text-sm font-medium text-blue-600 hover:underline dark:text-blue-400">{{ __('Open full calendar') }}</a>
                </div>

                <iframe
                    src="{{ route('calendar.index') }}"
                    title="{{ __('Original application calendar grid') }}"
                    class="mt-4 h-[72vh] w-full border border-zinc-200 dark:border-zinc-700"
                ></iframe>
            </section>

            <section class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('Original application Planner grid') }}</flux:heading>
                <flux:text class="mt-1 mb-4">{{ __('The production Planner grid kept here for direct comparison with the package.') }}</flux:text>

                <livewire:planner />
            </section>

            <section class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('Package Planner timeline') }}</flux:heading>
                <flux:text class="mt-1 mb-4">{{ __('The reusable package Planner UI and its extracted row-building pipeline.') }}</flux:text>

                <livewire:calendar-planner :events="$plannerEvents" />
            </section>
        </div>
    </div>
</x-layouts.app>
