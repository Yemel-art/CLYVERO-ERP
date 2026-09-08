<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Attendance\OpenSessionRequest;
use App\Http\Requests\Attendance\RecordAttendanceRequest;
use App\Http\Resources\AttendanceSessionResource;
use App\Models\AttendanceSession;
use App\Models\SchoolClass;
use App\Models\Term;
use App\Models\ParentGuardian;
use App\Services\Attendance\AttendanceService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AttendanceController extends ApiController
{
    public function __construct(
        private readonly AttendanceService $attendance,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('attendance.view'), 403);
        abort_if($request->user()?->isParent(), 403);
        $q = AttendanceSession::query()->with(['takenBy']);
        if ($request->filled('class_id')) $q->where('class_id', $request->input('class_id'));
        if ($request->filled('from'))     $q->where('date', '>=', $request->input('from'));
        if ($request->filled('to'))       $q->where('date', '<=', $request->input('to'));

        $sessions = $q->orderByDesc('date')->limit(50)->get();
        return $this->ok(AttendanceSessionResource::collection($sessions), 'Sessions retrieved.');
    }

    public function open(OpenSessionRequest $request): JsonResponse
    {
        /** @var SchoolClass $class */
        $class   = SchoolClass::findOrFail($request->input('class_id'));
        $date    = CarbonImmutable::parse($request->input('date'));
        $period  = $request->input('period');
        $term    = Term::active()->first();

        $session = $this->attendance->openSession($class, $date, $period, $term)
            ->load(['records.student', 'takenBy']);

        return $this->ok(new AttendanceSessionResource($session), 'Session ready.');
    }

    public function show(AttendanceSession $session): JsonResponse
    {
        abort_unless(request()->user()?->hasPermission('attendance.view'), 403);
        abort_if(request()->user()?->isParent(), 403);
        return $this->ok(
            new AttendanceSessionResource($session->load(['records.student', 'takenBy'])),
            'Session retrieved.',
        );
    }

    public function record(RecordAttendanceRequest $request, AttendanceSession $session): JsonResponse
    {
        $session = $this->attendance->recordBulk($session, $request->input('entries'));
        return $this->ok(
            new AttendanceSessionResource($session->load(['records.student', 'takenBy'])),
            'Attendance saved.',
        );
    }

    public function close(Request $request, AttendanceSession $session): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('attendance.record'), 403);
        return $this->ok(
            new AttendanceSessionResource($this->attendance->closeSession($session)->load(['records.student', 'takenBy'])),
            'Session closed.',
        );
    }

    public function reopen(Request $request, AttendanceSession $session): JsonResponse
    {
        abort_unless($request->user()?->isAdministrator(), 403);
        return $this->ok(
            new AttendanceSessionResource($this->attendance->reopenSession($session)->load(['records.student', 'takenBy'])),
            'Session reopened.',
        );
    }

    public function stats(Request $request, AttendanceSession $session): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('attendance.view'), 403);
        abort_if($request->user()?->isParent(), 403);
        return $this->ok($this->attendance->sessionStats($session), 'Statistics.');
    }

    public function studentSummary(Request $request, string $studentId): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('attendance.view'), 403);
        if ($request->user()?->isParent()) {
            abort_unless(ParentGuardian::where('user_id', $request->user()->id)
                ->whereHas('students', fn ($query) => $query->where('students.id', $studentId))
                ->exists(), 403);
        }
        return $this->ok(
            $this->attendance->studentSummary($studentId, $request->input('term_id')),
            'Student attendance summary.',
        );
    }
}
