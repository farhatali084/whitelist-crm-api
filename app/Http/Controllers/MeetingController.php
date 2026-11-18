<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMeetingRequest;
use App\Http\Requests\UpdateMeetingRequest;
use App\Http\Resources\MeetingResource;
use App\Models\Company;
use App\Models\Manager;
use App\Models\Meeting;
use App\Services\MeetingScheduler;
use App\Support\Time;
use Illuminate\Http\JsonResponse;

class MeetingController extends Controller
{
    public function __construct(private readonly MeetingScheduler $scheduler) {}

    public function store(StoreMeetingRequest $request): JsonResponse
    {
        $meeting = $this->scheduler->book(
            Company::findOrFail($request->integer('company_id')),
            Manager::findOrFail($request->integer('manager_id')),
            Time::parseUtc((string) $request->input('starts_at')),
        );

        return MeetingResource::make($meeting)->response()->setStatusCode(201);
    }

    public function show(Meeting $meeting): MeetingResource
    {
        return MeetingResource::make($meeting->load('events'));
    }

    public function update(UpdateMeetingRequest $request, Meeting $meeting): MeetingResource
    {
        if ($request->isCancellation()) {
            return MeetingResource::make($this->scheduler->cancel($meeting));
        }

        return MeetingResource::make($this->scheduler->reschedule(
            $meeting,
            $request->has('starts_at') ? Time::parseUtc((string) $request->input('starts_at')) : null,
            $request->has('manager_id') ? Manager::findOrFail($request->integer('manager_id')) : null,
        ));
    }
}
