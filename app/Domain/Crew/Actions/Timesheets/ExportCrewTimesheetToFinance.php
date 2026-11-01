<?php

declare(strict_types=1);

namespace App\Domain\Crew\Actions\Timesheets;

use App\Domain\Crew\Data\Timesheets\CrewTimesheetData;
use App\Domain\Crew\Data\Timesheets\ExportToFinanceData;
use App\Domain\Crew\Models\CrewTimesheet;
use App\Domain\Crew\Services\CrewTimesheetEditor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class ExportCrewTimesheetToFinance
{
    public function __construct(private CrewTimesheetEditor $editor) {}

    public function __invoke(CrewTimesheet $sheet, ExportToFinanceData $data): CrewTimesheetData
    {
        Gate::authorize('export', $sheet);
        Validator::make($data->toArray(), ['finance_reference' => ['required', 'string', 'max:80']])->validate();

        return DB::transaction(function () use ($sheet, $data): CrewTimesheetData {
            $sheet = $this->editor->lock($sheet, 'export', $data->version);
            if ($sheet->exported_to_finance_at !== null) {
                if ($sheet->finance_reference !== $data->finance_reference) {
                    throw ValidationException::withMessages(['finance_reference' => 'The exported finance reference is immutable.']);
                }

                return CrewTimesheetData::fromModel($sheet);
            }
            $sheet->fill(['exported_to_finance_at' => now(), 'finance_reference' => $data->finance_reference]);
            $this->editor->save($sheet, 'crew.timesheet.exported_to_finance', ['finance_reference' => $data->finance_reference, 'amount_minor' => $sheet->total_pay_minor, 'currency_code' => $sheet->currency_code]);

            return CrewTimesheetData::fromModel($sheet);
        });
    }
}
