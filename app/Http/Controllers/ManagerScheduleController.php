<?php

namespace App\Http\Controllers;

use App\Models\Manager;
use App\Models\Meeting;
use App\Support\Time;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ManagerScheduleController extends Controller
{
    /**
     * GET /managers/{id}/schedule?date=2030-01-07
     * "date" is a calendar day in Asia/Almaty (the managers' working day); times are returned in UTC.
     */
    public function __invoke(Request $request, Manager $manager): JsonResponse
    {
        $request->validate(['date' => ['required', 'date_format:Y-m-d']]);

        $tz = config('crm.timezone');
        $dayStart = CarbonImmutable::createFromFormat('!Y-m-d', $request->query('date'), $tz);
        $from = $dayStart->utc();
        $to = $dayStart->addDay()->utc();

        $meetings = Meeting::query()
            ->with('company:id,name')
            ->where('manager_id', $manager->id)
            ->where('status', Meeting::SCHEDULED)
            ->where('starts_at', '>=', $from)
            ->where('starts_at', '<', $to)
            ->orderBy('starts_at')
            ->get();

        return response()->json(['data' => [
            'manager' => ['id' => $manager->id, 'name' => $manager->name],
            'date' => $request->query('date'),
            'timezone' => $tz,
            'meetings' => $meetings->map(fn (Meeting $m) => [
                'id' => $m->id,
                'company' => ['id' => $m->company->id, 'name' => $m->company->name],
                'starts_at' => Time::iso($m->starts_at),
                'ends_at' => Time::iso($m->ends_at),
                'local_time' => $m->starts_at->setTimezone($tz)->format('H:i').'-'.$m->ends_at->setTimezone($tz)->format('H:i'),
            ]),
        ]]);
    }
}
