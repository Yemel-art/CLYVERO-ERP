<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $teacherRoleIds = DB::table('roles')->where('name', 'teacher')->pluck('id');

        DB::table('users')
            ->whereIn('role_id', $teacherRoleIds)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get(['id', 'school_id', 'email'])
            ->each(function (object $user): void {
                if (DB::table('teachers')->where('user_id', $user->id)->exists()) {
                    return;
                }

                $matches = DB::table('teachers')
                    ->where('school_id', $user->school_id)
                    ->whereNull('user_id')
                    ->whereNull('deleted_at')
                    ->whereRaw('LOWER(email) = ?', [strtolower(trim((string) $user->email))])
                    ->pluck('id');

                if ($matches->count() === 1) {
                    DB::table('teachers')->where('id', $matches->first())->update([
                        'user_id' => $user->id,
                        'updated_at' => now(),
                    ]);
                }
            });

        // A teacher selected directly on a timetable slot must also own the
        // matching class-subject assignment used by grades and the portal.
        // Only repair unambiguous legacy assignments (one teacher per pair).
        DB::table('timetable_slots')
            ->whereNotNull('teacher_id')
            ->whereNotNull('subject_id')
            ->get(['class_id', 'subject_id', 'teacher_id'])
            ->groupBy(fn (object $slot): string => $slot->class_id . '|' . $slot->subject_id)
            ->each(function ($slots): void {
                $teacherIds = $slots->pluck('teacher_id')->unique()->values();
                if ($teacherIds->count() !== 1) {
                    return;
                }

                $slot = $slots->first();
                DB::table('class_subject')
                    ->where('class_id', $slot->class_id)
                    ->where('subject_id', $slot->subject_id)
                    ->whereNull('teacher_id')
                    ->update([
                        'teacher_id' => $teacherIds->first(),
                        'updated_at' => now(),
                    ]);
            });
    }

    public function down(): void
    {
        // Intentional no-op: these are recovered relationship links, not a
        // schema change, and removing them would recreate orphaned accounts.
    }
};
