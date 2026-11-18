<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Company;
use App\Models\Manager;
use App\Models\Meeting;
use App\Support\Time;
use App\Support\WorkingHours;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * All meeting business rules live here: duration, working hours, no overlaps,
 * history on reschedule/cancel. Controllers only translate HTTP <-> these calls.
 */
class MeetingScheduler
{
    private WorkingHours $hours;

    public function __construct(?WorkingHours $hours = null)
    {
        $this->hours = $hours ?? WorkingHours::fromConfig();
    }

    public function book(Company $company, Manager $manager, CarbonImmutable $startsAt): Meeting
    {
        // One transaction: the conflict check and the insert cannot be split by another booking
        // (SQLite transaction_mode = IMMEDIATE takes the write lock at BEGIN).
        return DB::transaction(function () use ($company, $manager, $startsAt) {
            [$start, $end] = $this->slot($startsAt);
            $this->assertBookable($start, $end, $manager->id, $company->id);

            $meeting = Meeting::create([
                'company_id' => $company->id,
                'manager_id' => $manager->id,
                'starts_at' => $start,
                'ends_at' => $end,
                'status' => Meeting::SCHEDULED,
                'original_starts_at' => $start,
            ]);
            $meeting->events()->create([
                'type' => 'created',
                'new_starts_at' => $start,
                'new_manager_id' => $manager->id,
            ]);

            return $meeting->load('events');
        });
    }

    /** Move to a new time and/or another manager. The original slot and every move stay in history. */
    public function reschedule(Meeting $meeting, ?CarbonImmutable $startsAt, ?Manager $manager): Meeting
    {
        return DB::transaction(function () use ($meeting, $startsAt, $manager) {
            $meeting->refresh(); // re-read inside the lock
            $this->assertModifiable($meeting);

            [$start, $end] = $this->slot($startsAt ?? $meeting->starts_at);
            $managerId = $manager?->id ?? $meeting->manager_id;

            if ($start->eq($meeting->starts_at) && $managerId === $meeting->manager_id) {
                return $meeting->load('events'); // nothing changes, no history entry
            }

            $this->assertBookable($start, $end, $managerId, $meeting->company_id, exceptMeetingId: $meeting->id);

            $meeting->events()->create([
                'type' => 'rescheduled',
                'old_starts_at' => $meeting->starts_at,
                'new_starts_at' => $start,
                'old_manager_id' => $meeting->manager_id,
                'new_manager_id' => $managerId,
            ]);
            $meeting->update(['starts_at' => $start, 'ends_at' => $end, 'manager_id' => $managerId]);

            return $meeting->load('events');
        });
    }

    public function cancel(Meeting $meeting): Meeting
    {
        return DB::transaction(function () use ($meeting) {
            $meeting->refresh();
            $this->assertModifiable($meeting);

            $meeting->update(['status' => Meeting::CANCELLED, 'cancelled_at' => CarbonImmutable::now()]);
            $meeting->events()->create([
                'type' => 'cancelled',
                'old_starts_at' => $meeting->starts_at,
                'old_manager_id' => $meeting->manager_id,
            ]);

            return $meeting->load('events');
        });
    }

    /** @return array{CarbonImmutable, CarbonImmutable} UTC start and end (rule 1: fixed duration). */
    private function slot(CarbonImmutable $startsAt): array
    {
        $start = $startsAt->utc();

        return [$start, $start->addMinutes(config('crm.meeting_minutes'))];
    }

    private function assertModifiable(Meeting $meeting): void
    {
        if (! $meeting->isActive()) {
            throw ApiException::conflict('meeting_cancelled', "Meeting {$meeting->id} is cancelled and can no longer be changed.");
        }
        if ($meeting->starts_at->lte(CarbonImmutable::now())) {
            throw ApiException::conflict('meeting_started', "Meeting {$meeting->id} has already started or taken place and can no longer be changed.");
        }
    }

    private function assertBookable(
        CarbonImmutable $start,
        CarbonImmutable $end,
        int $managerId,
        int $companyId,
        ?int $exceptMeetingId = null,
    ): void {
        if ($start->lte(CarbonImmutable::now())) {
            throw ApiException::unprocessable('starts_in_past', 'A meeting can only be booked in the future.', [
                'starts_at' => Time::iso($start),
            ]);
        }

        if ($reason = $this->hours->violation($start, $end)) {
            throw ApiException::unprocessable('outside_working_hours', $reason, [
                'starts_at' => Time::iso($start),
                'ends_at' => Time::iso($end),
            ]);
        }

        // Rule 3: one manager, no overlapping active meetings.
        $managerClash = Meeting::query()->overlapping($start, $end)
            ->where('manager_id', $managerId)
            ->when($exceptMeetingId, fn ($q) => $q->where('id', '!=', $exceptMeetingId))
            ->first();
        if ($managerClash) {
            throw ApiException::conflict('manager_busy', "Manager {$managerId} already has a meeting at this time.", [
                'conflicting_meeting' => $this->brief($managerClash),
            ]);
        }

        // Rule 4: one company, no overlapping active meetings, even with different managers.
        $companyClash = Meeting::query()->overlapping($start, $end)
            ->where('company_id', $companyId)
            ->when($exceptMeetingId, fn ($q) => $q->where('id', '!=', $exceptMeetingId))
            ->first();
        if ($companyClash) {
            throw ApiException::conflict('company_busy', "Company {$companyId} already has an active meeting at this time.", [
                'conflicting_meeting' => $this->brief($companyClash),
            ]);
        }
    }

    private function brief(Meeting $m): array
    {
        return [
            'id' => $m->id,
            'manager_id' => $m->manager_id,
            'company_id' => $m->company_id,
            'starts_at' => Time::iso($m->starts_at),
            'ends_at' => Time::iso($m->ends_at),
        ];
    }
}
