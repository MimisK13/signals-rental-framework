<div>
    <div class="mb-8 grid grid-cols-4 gap-4 max-lg:grid-cols-2 max-sm:grid-cols-1">
        <div class="kpi-card">
            <div class="kpi-label">Active Opportunities</div>
            <div class="kpi-value-row">
                <span class="kpi-value">{{ number_format($metrics['active_opportunities']['total']) }}</span>
            </div>
            <div class="kpi-footer">
                {{ number_format($metrics['active_opportunities']['quotations']) }} quotations,
                {{ number_format($metrics['active_opportunities']['orders']) }} orders
            </div>
        </div>

        <div class="kpi-card">
            <div class="kpi-label">Revenue</div>
            <div class="kpi-value-row">
                <span class="kpi-value">{{ $formatter->money($metrics['revenue']['current_minor'], $metrics['revenue']['currency_code']) }}</span>
                @if ($metrics['revenue']['change_percent'] !== null)
                    <span @class([
                        'kpi-change',
                        'up' => $metrics['revenue']['change_percent'] >= 0,
                        'down' => $metrics['revenue']['change_percent'] < 0,
                    ])>
                        {{ $metrics['revenue']['change_percent'] >= 0 ? '+' : '' }}{{ $formatter->number($metrics['revenue']['change_percent'], 1) }}%
                    </span>
                @endif
            </div>
            <div class="kpi-footer">
                vs {{ $formatter->money($metrics['revenue']['previous_minor'], $metrics['revenue']['currency_code']) }} last month
            </div>
        </div>

        <div class="kpi-card">
            <div class="kpi-label">On Hire</div>
            <div class="kpi-value-row">
                <span class="kpi-value">{{ number_format($metrics['on_hire']['units']) }}</span>
            </div>
            <div class="kpi-footer">
                Across {{ number_format($metrics['on_hire']['active_jobs']) }} active jobs
            </div>
        </div>

        <div class="kpi-card">
            <div class="kpi-label">Overdue</div>
            <div class="kpi-value-row">
                <span class="kpi-value">{{ $formatter->money($metrics['overdue']['total_outstanding_minor'], $metrics['overdue']['currency_code']) }}</span>
            </div>
            <div class="kpi-footer">
                {{ number_format($metrics['overdue']['invoice_count']) }} invoices{{ $metrics['overdue']['average_days_overdue'] !== null ? ', avg '.$formatter->number((float) $metrics['overdue']['average_days_overdue'], 0).' days overdue' : '' }}
            </div>
        </div>
    </div>

    <span class="mb-6 block font-mono text-[9px] tracking-[0.3px] text-[var(--text-muted)]">
        Last updated {{ $formatter->dateTime($cachedAt) }}
    </span>
</div>
