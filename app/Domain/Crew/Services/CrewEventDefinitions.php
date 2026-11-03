<?php

declare(strict_types=1);

namespace App\Domain\Crew\Services;

use App\Domain\Notifications\Enums\NotificationAudience;

final class CrewEventDefinitions
{
    public const WEBHOOKS = [
        'crew.member.created', 'crew.member.updated', 'crew.member.archived', 'crew.member.restored',
        'crew.request.created', 'crew.request.published', 'crew.request.cancelled', 'crew.request.filled',
        'crew.assignment.proposed', 'crew.assignment.invited', 'crew.assignment.confirmed', 'crew.assignment.rejected', 'crew.assignment.cancelled', 'crew.assignment.substituted',
        'crew.shift.created', 'crew.shift.updated', 'crew.shift.confirmed', 'crew.shift.cancelled',
        'crew.availability.approved', 'crew.timesheet.submitted', 'crew.timesheet.approved', 'crew.timesheet.rejected', 'crew.timesheet.locked', 'crew.timesheet.exported_to_finance',
        'crew.expense.submitted', 'crew.expense.approved', 'crew.expense.rejected', 'crew.expense.reimbursed', 'crew.shortage.detected',
    ];

    public const NOTIFICATIONS = [
        'crew.assignment.invited', 'crew.assignment.confirmed', 'crew.assignment.cancelled', 'crew.assignment.substituted',
        'crew.request.published', 'crew.request.filled', 'crew.availability.requested', 'crew.availability.approved', 'crew.availability.rejected',
        'crew.expense.submitted', 'crew.expense.approved', 'crew.expense.rejected',
        'crew.qualification.expiring', 'crew.qualification.expired', 'crew.shift.updated', 'crew.shift.cancelled', 'crew.timesheet.submitted', 'crew.timesheet.approved', 'crew.timesheet.rejected', 'crew.shortage.detected',
    ];

    /**
     * @return array<string, array{category: string, name: string, description: string, audience: NotificationAudience, available_channels: list<string>, default_channels: list<string>, default_recipients: list<array<string, string>>, is_system: bool}>
     */
    public static function notifications(): array
    {
        $definitions = [];
        foreach (self::NOTIFICATIONS as $name) {
            $title = (string) str($name)->replace('.', ' ')->headline();
            $approval = in_array($name, ['crew.expense.submitted', 'crew.qualification.expiring', 'crew.qualification.expired', 'crew.timesheet.submitted', 'crew.availability.requested', 'crew.request.published', 'crew.request.filled', 'crew.shortage.detected'], true);
            $definitions[$name] = [
                'category' => 'Crew', 'name' => $title, 'description' => $title.'.',
                'audience' => NotificationAudience::Internal,
                'available_channels' => ['database', 'mail'], 'default_channels' => ['database'],
                'default_recipients' => [['type' => $approval ? 'crew_coordinators' : 'crew_member']],
                'is_system' => true,
            ];
        }

        return $definitions;
    }
}
