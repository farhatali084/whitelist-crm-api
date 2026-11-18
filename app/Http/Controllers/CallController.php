<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Http\Requests\StoreCallRequest;
use App\Http\Resources\CallResource;
use App\Models\Call;
use App\Models\Company;
use App\Models\Meeting;
use App\Support\Time;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CallController extends Controller
{
    public function store(StoreCallRequest $request): JsonResponse
    {
        $data = $request->validated();

        $call = DB::transaction(function () use ($data) {
            if ($data['result'] === 'meeting_set') {
                $this->assertMeetingFitsCall(Meeting::findOrFail($data['meeting_id']), $data);
            }

            $call = Call::create([
                'company_id' => $data['company_id'],
                'manager_id' => $data['manager_id'],
                'result' => $data['result'],
                'meeting_id' => $data['meeting_id'] ?? null,
                'comment' => $data['comment'] ?? null,
                'called_at' => isset($data['called_at']) ? Time::parseUtc($data['called_at']) : CarbonImmutable::now(),
            ]);

            // The company's status is the result of its latest call (used by GET /companies?status=).
            Company::whereKey($data['company_id'])->update(['status' => $data['result']]);

            return $call;
        });

        return CallResource::make($call)->response()->setStatusCode(201);
    }

    /** Rule 5: "meeting_set" is valid only with a real, active meeting of the same company. */
    private function assertMeetingFitsCall(Meeting $meeting, array $data): void
    {
        $fail = fn (string $code, string $message) => throw ApiException::unprocessable($code, $message, ['meeting_id' => $meeting->id]);

        if ($meeting->company_id !== (int) $data['company_id']) {
            $fail('meeting_company_mismatch', "Meeting {$meeting->id} belongs to company {$meeting->company_id}, not {$data['company_id']}.");
        }
        if (! $meeting->isActive()) {
            $fail('meeting_not_active', "Meeting {$meeting->id} is cancelled; a call cannot be logged as meeting_set for it.");
        }
        if (Call::where('meeting_id', $meeting->id)->exists()) {
            $fail('meeting_already_linked', "Meeting {$meeting->id} is already linked to another call.");
        }
    }
}
