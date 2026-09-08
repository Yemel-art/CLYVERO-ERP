<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\AttendanceStatus;
use App\Enums\StudentStatus;
use App\Enums\TeacherStatus;
use App\Http\Controllers\Api\ApiController;
use App\Models\AcademicYear;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Invoice;
use App\Models\ParentGuardian;
use App\Models\Payment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Term;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class DashboardController extends ApiController
{
    /**
     * Administrator dashboard — full school overview.
     */
    public function admin(Request $request): JsonResponse
    {
        abort_unless($request->user()?->isAdministrator(), 403);

        $activeYear  = AcademicYear::where('status', AcademicYear::STATUS_ACTIVE)->first();
        $activeTerm  = Term::where('status', Term::STATUS_ACTIVE)->first();
        $today       = Carbon::today();

        // Headline counts
        $studentsActive   = Student::where('status', StudentStatus::Active->value)->count();
        $teachersActive   = Teacher::where('status', TeacherStatus::Active->value)->count();
        $parentsActive    = ParentGuardian::where('is_active', true)->count();
        $classesActive    = SchoolClass::where('is_active', true)
            ->when($activeYear, fn ($q) => $q->where('academic_year_id', $activeYear->id))->count();

        // Today's attendance summary across all sessions
        $todaySessions = AttendanceSession::whereDate('date', $today)->pluck('id');
        $todayStats = AttendanceRecord::whereIn('session_id', $todaySessions)
            ->select('status', DB::raw('COUNT(*) as c'))->groupBy('status')->pluck('c', 'status');
        $todayAttendance = [
            'present' => (int) ($todayStats[AttendanceStatus::Present->value] ?? 0),
            'absent'  => (int) ($todayStats[AttendanceStatus::Absent->value] ?? 0),
            'late'    => (int) ($todayStats[AttendanceStatus::Late->value] ?? 0),
            'excused' => (int) ($todayStats[AttendanceStatus::Excused->value] ?? 0),
        ];

        // Finance — current academic year
        $financeQuery = Invoice::query()
            ->when($activeYear, fn ($q) => $q->where('academic_year_id', $activeYear->id))
            ->whereNotIn('status', [Invoice::STATUS_CANCELLED]);
        $finance = [
            'billed'      => (float) $financeQuery->clone()->sum('total'),
            'collected'   => (float) $financeQuery->clone()->sum('paid'),
            'outstanding' => (float) $financeQuery->clone()->sum('balance'),
            'overdue'     => (float) Invoice::where('status', Invoice::STATUS_OVERDUE)
                ->when($activeYear, fn ($q) => $q->where('academic_year_id', $activeYear->id))
                ->sum('balance'),
        ];

        // Recent payments
        $recentPayments = Payment::with(['student:id,first_name,last_name,admission_number'])
            ->orderByDesc('paid_at')->limit(8)->get()
            ->map(fn ($p) => [
                'id'             => $p->id,
                'receipt_number' => $p->receipt_number,
                'paid_at'        => $p->paid_at->toDateString(),
                'amount'         => (float) $p->amount,
                'method'         => $p->method,
                'student'        => $p->student ? [
                    'id' => $p->student->id, 'full_name' => $p->student->full_name,
                    'admission_number' => $p->student->admission_number,
                ] : null,
            ])->all();

        return $this->ok([
            'active_year'  => $activeYear ? ['id' => $activeYear->id, 'title' => $activeYear->title] : null,
            'active_term'  => $activeTerm ? ['id' => $activeTerm->id, 'name'  => $activeTerm->name]  : null,
            'counts'       => [
                'students' => $studentsActive,
                'teachers' => $teachersActive,
                'parents'  => $parentsActive,
                'classes'  => $classesActive,
            ],
            'attendance_today' => $todayAttendance,
            'finance'          => $finance,
            'recent_payments'  => $recentPayments,
        ], 'Admin dashboard.');
    }

    /**
     * Secretary dashboard — operational view focused on enrollment & finance.
     */
    public function secretary(Request $request): JsonResponse
    {
        abort_unless($request->user()?->isSecretary() || $request->user()?->isAdministrator(), 403);

        // New enrollments this week / month
        $startOfWeek  = Carbon::now()->startOfWeek();
        $startOfMonth = Carbon::now()->startOfMonth();

        return $this->ok([
            'enrollments' => [
                'this_week'  => Student::where('created_at', '>=', $startOfWeek)->count(),
                'this_month' => Student::where('created_at', '>=', $startOfMonth)->count(),
                'archived'   => Student::onlyTrashed()->count(),
            ],
            'pending_invoices' => Invoice::whereIn('status', [Invoice::STATUS_ISSUED, Invoice::STATUS_PARTIALLY_PAID])->count(),
            'overdue_invoices' => Invoice::where('status', Invoice::STATUS_OVERDUE)->count(),
            'recent_students'  => Student::orderByDesc('created_at')->limit(6)->get()
                ->map(fn ($s) => [
                    'id' => $s->id, 'full_name' => $s->full_name,
                    'admission_number' => $s->admission_number,
                    'created_at' => $s->created_at?->toDateString(),
                ])->all(),
        ], 'Secretary dashboard.');
    }

    /**
     * Teacher dashboard — own classes, today's slots, recent assessments.
     */
    public function teacher(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user?->isTeacher(), 403);

        $teacher = Teacher::resolveForUser($user);
        if (! $teacher) {
            return $this->ok([
                'teacher' => null,
                'form_master_classes' => [],
                'teaching' => [],
            ], 'No teacher record linked to this account.');
        }

        $formMasterClasses = SchoolClass::where('form_master_id', $teacher->id)
            ->where('is_active', true)
            ->with('academicYear:id,title')
            ->withCount('students')->get()
            ->map(fn ($c) => [
                'id' => $c->id, 'name' => $c->name, 'grade_level' => $c->grade_level,
                'speciality' => $c->speciality,
                'cycle' => $c->cycle,
                'language' => $c->language,
                'academic_year' => $c->academicYear?->title,
                'students_count' => $c->students_count,
            ])->all();

        $teachingClasses = DB::table('class_subject')
            ->where('teacher_id', $teacher->id)
            ->join('school_classes', 'school_classes.id', '=', 'class_subject.class_id')
            ->join('subjects', 'subjects.id', '=', 'class_subject.subject_id')
            ->join('academic_years', 'academic_years.id', '=', 'school_classes.academic_year_id')
            ->whereNull('school_classes.deleted_at')
            ->where('school_classes.is_active', true)
            ->where('academic_years.school_id', $user->school_id)
            ->select(
                'school_classes.id as class_id',
                'school_classes.name as class_name',
                'school_classes.grade_level',
                'school_classes.speciality',
                'school_classes.cycle',
                'school_classes.language',
                'academic_years.title as academic_year',
                'subjects.id as subject_id',
                'subjects.name as subject_name',
                'subjects.color as subject_color',
                'class_subject.coefficient',
                'class_subject.weekly_frequency',
            )->get();

        // Timetable rows created before assignment synchronization are also
        // valid teaching assignments and must remain visible to the teacher.
        $timetableAssignments = DB::table('timetable_slots')
            ->where('timetable_slots.teacher_id', $teacher->id)
            ->whereNotNull('timetable_slots.subject_id')
            ->join('school_classes', 'school_classes.id', '=', 'timetable_slots.class_id')
            ->join('subjects', 'subjects.id', '=', 'timetable_slots.subject_id')
            ->join('academic_years', 'academic_years.id', '=', 'school_classes.academic_year_id')
            ->whereNull('school_classes.deleted_at')
            ->where('school_classes.is_active', true)
            ->where('academic_years.school_id', $user->school_id)
            ->select(
                'school_classes.id as class_id',
                'school_classes.name as class_name',
                'school_classes.grade_level',
                'school_classes.speciality',
                'school_classes.cycle',
                'school_classes.language',
                'academic_years.title as academic_year',
                'subjects.id as subject_id',
                'subjects.name as subject_name',
                'subjects.color as subject_color',
                'subjects.coefficient',
                DB::raw('NULL AS weekly_frequency'),
            )->get();

        $teachingClasses = $teachingClasses
            ->concat($timetableAssignments)
            ->unique(fn ($row) => $row->class_id . '|' . $row->subject_id)
            ->values()
            ->all();

        return $this->ok([
            'teacher' => [
                'id' => $teacher->id, 'full_name' => $teacher->full_name,
                'employee_number' => $teacher->employee_number,
            ],
            'form_master_classes' => $formMasterClasses,
            'teaching'            => $teachingClasses,
        ], 'Teacher dashboard.');
    }

    /**
     * Parent dashboard — children, balances, recent grades.
     */
    public function parent(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user?->isParent(), 403);

        $parent = ParentGuardian::where('user_id', $user->id)->with('students.schoolClass')->first();
        if (! $parent) return $this->ok(['children' => []], 'No parent record linked.');

        $children = $parent->students->map(function ($s) {
            $balance = (float) Invoice::where('student_id', $s->id)
                ->whereNotIn('status', [Invoice::STATUS_CANCELLED])->sum('balance');
            return [
                'id'                => $s->id,
                'full_name'         => $s->full_name,
                'admission_number'  => $s->admission_number,
                'photo_url'         => $s->photo_url,
                'class'             => $s->schoolClass?->name,
                'balance'           => $balance,
            ];
        });

        return $this->ok([
            'parent' => ['id' => $parent->id, 'full_name' => $parent->full_name],
            'children' => $children->all(),
        ], 'Parent dashboard.');
    }
}
