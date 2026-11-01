<div class="min-w-0 w-full space-y-4 px-3 py-4 sm:px-5 md:px-6">
    @include('crew::partials.navigation')
    <flux:heading size="xl">Timesheets and approvals</flux:heading>
    <flux:select
        label="Status"
        wire:model.live="status"
        class="max-w-sm"
    >
        <option value="">All statuses</option>
        @foreach (\App\Domain\Crew\Enums\CrewTimesheetStatus::cases() as $state)
            <option value="{{ $state->value }}">{{ $state->label() }}</option>
        @endforeach
    </flux:select>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="border-b">
                    <th class="p-2">Member</th>
                    <th class="p-2">Project</th>
                    <th class="p-2">Period</th>
                    <th class="p-2">Status</th>
                    <th class="p-2 text-right">Minutes</th>
                    @can('crew.rates.view')
                        <th class="p-2 text-right">Pay</th>
                    @endcan

                </tr>
            </thead>
            <tbody>
                @forelse($this->timesheets as $timesheet)
                    <tr
                        wire:key="timesheet-{{ $timesheet->id }}"
                        class="border-b"
                    >
                        <td class="p-2">
                            <a
                                class="underline"
                                href="{{ route('crew.timesheets.show', $timesheet) }}"
                                wire:navigate.hover
                            >{{ $timesheet->crewMember->name }}</a>
                        </td>
                        <td class="p-2">{{ $timesheet->project?->name }}</td>
                        <td class="p-2">{{ $timesheet->period_start->format('Y-m-d') }} –
                            {{ $timesheet->period_end->format('Y-m-d') }}</td>
                        <td class="p-2">{{ $timesheet->status->label() }}</td>
                        <td class="p-2 text-right tabular-nums">{{ $timesheet->total_minutes }}</td>
                        @can('crew.rates.view')
                            <td class="p-2 text-right tabular-nums">{{ $timesheet->formatMoneyCost('total_pay_minor') }}
                            </td>
                        @endcan

                    </tr>
                @empty
                    <tr>
                        <td
                            colspan="6"
                            class="p-6 text-center"
                        >No timesheets match this status.</td>
                    </tr>
                @endforelse

            </tbody>
        </table>
    </div>{{ $this->timesheets->links() }}
</div>
