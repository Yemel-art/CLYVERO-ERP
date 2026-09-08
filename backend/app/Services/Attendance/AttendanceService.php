<?php

declare(strict_types=1);

namespace App\Services\Attendance;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Term;
use App\Services\BaseService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceService extends BaseService
{
    /**
     * Open an attendance session for a class on a date (idempotent by
     * class+date+period — returns the existing one if already open).
     */
    public function openSession(
        SchoolClass $class,
        CarbonImmutable $date,
        ?string $period = null,
        ?Term $term = null,
    ): AttendanceSession {
        return $this->transaction(function () use ($class, $date, $period, $term): AttendanceSession {
            return AttendanceSession::firstOrCreate(
                [
                    'class_id' => $class->id,
                    'date'     => $date->toDateString(),
                    'period'   => $period,
                ],
                [
                    'term_id'  => $term?->id ?? Term::active()->value('id'),
                    'taken_by' => Auth::id(),
                    'status'   => AttendanceSession::STATUS_OPEN,
                ],
            );
        });
    }

    /**
     * Bulk-record attendance. Existing entries for the same student in
     * the same session are updated; new ones are created.
     *
     * @param array<int, array{student_id:string, status:string, notes?:?string}> $entries
     */
    public function recordBulk(AttendanceSession $session, array $entries): AttendanceSession
    {
        return $this->transaction(function () use ($session, $entries): AttendanceSession {
            if ($session->status === AttendanceSession::STATUS_CLOSED) {
                throw ValidationException::withMessages([
                    'session' => ['Cannot record attendance on a closed session.'],
                ]);
            }

            $studentIds = collect($entries)->pluck('student_id')->unique()->values();
            if (Student::query()->where('class_id', $session->class_id)
                ->whereIn('id', $studentIds)->count() !== $studentIds->count()) {
                throw ValidationException::withMessages([
                    'entries' => ['Every attendance entry must belong to a student in the session class.'],
                ]);
            }

            $now = now();
            $rows = collect($entries)->map(fn (array $entry): array => [
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'session_id' => $session->id,
                'student_id' => $entry['student_id'],
                'status' => $entry['status'],
                'notes' => $entry['notes'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();
            if ($rows !== []) {
                DB::table('attendance_records')->upsert(
                    $rows,
                    ['session_id', 'student_id'],
                    ['status', 'notes', 'updated_at'],
                );
            }
            return $session->load('records.student');
        });
    }

    public function closeSession(AttendanceSession $session): AttendanceSession
    {
        $session->update(['status' => AttendanceSession::STATUS_CLOSED]);
        return $session->fresh() ?? $session;
    }

    public function reopenSession(AttendanceSession $session): AttendanceSession
    {
        $session->update(['status' => AttendanceSession::STATUS_OPEN]);
        return $session->fresh() ?? $session;
    }

    /**
     * Stats for a session.
     *
     * @return array<string, int>
     */
    public function sessionStats(AttendanceSession $session): array
    {
        $rows = $session->records()->select('status', DB::raw('COUNT(*) as count'))->groupBy('status')->get();
        $stats = ['present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0];
        foreach ($rows as $row) {
            $status = $row->status instanceof \BackedEnum ? $row->status->value : (string) $row->status;
            $stats[$status] = (int) $row->count;
        }
        return $stats;
    }

    /**
     * Per-student attendance summary across a term.
     *
     * @return array{present:int, absent:int, late:int, excused:int, total:int, rate:float}
     */
    public function studentSummary(string $studentId, ?string $termId = null): array
    {
        $q = AttendanceRecord::query()->where('student_id', $studentId);
        if ($termId) {
            $q->whereHas('session', fn ($s) => $s->where('term_id', $termId));
        }
        $rows = $q->select('status', DB::raw('COUNT(*) as count'))->groupBy('status')->get();
        $out = ['present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0];
        foreach ($rows as $r) {
            $status = $r->status instanceof \BackedEnum ? $r->status->value : (string) $r->status;
            $out[$status] = (int) $r->count;
        }
        $total = array_sum($out);
        $rate  = $total > 0 ? round(($out['present'] + $out['late']) / $total * 100, 1) : 0.0;
        return $out + ['total' => $total, 'rate' => $rate];
    }
}
