<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION clyvero_assert_tenant_integrity()
RETURNS trigger AS $$
DECLARE
    expected_school uuid;
    related_school uuid;
BEGIN
    IF TG_TABLE_NAME = 'students' THEN
        SELECT school_id INTO related_school FROM academic_years WHERE id = NEW.academic_year_id;
        IF related_school IS DISTINCT FROM NEW.school_id THEN
            RAISE EXCEPTION 'Student academic year belongs to another school';
        END IF;
        IF NEW.class_id IS NOT NULL THEN
            SELECT ay.school_id INTO related_school
              FROM school_classes sc JOIN academic_years ay ON ay.id = sc.academic_year_id
             WHERE sc.id = NEW.class_id;
            IF related_school IS DISTINCT FROM NEW.school_id THEN
                RAISE EXCEPTION 'Student class belongs to another school';
            END IF;
        END IF;
        IF NEW.parent_id IS NOT NULL THEN
            SELECT school_id INTO related_school FROM parents WHERE id = NEW.parent_id;
            IF related_school IS DISTINCT FROM NEW.school_id THEN
                RAISE EXCEPTION 'Student parent belongs to another school';
            END IF;
        END IF;
    ELSIF TG_TABLE_NAME = 'teachers' THEN
        IF NEW.user_id IS NOT NULL THEN
            SELECT school_id INTO related_school FROM users WHERE id = NEW.user_id;
            IF related_school IS DISTINCT FROM NEW.school_id THEN
                RAISE EXCEPTION 'Teacher login belongs to another school';
            END IF;
        END IF;
    ELSIF TG_TABLE_NAME = 'parents' THEN
        IF NEW.user_id IS NOT NULL THEN
            SELECT school_id INTO related_school FROM users WHERE id = NEW.user_id;
            IF related_school IS DISTINCT FROM NEW.school_id THEN
                RAISE EXCEPTION 'Parent login belongs to another school';
            END IF;
        END IF;
    ELSIF TG_TABLE_NAME = 'school_classes' THEN
        SELECT school_id INTO expected_school FROM academic_years WHERE id = NEW.academic_year_id;
        IF NEW.form_master_id IS NOT NULL THEN
            SELECT school_id INTO related_school FROM teachers WHERE id = NEW.form_master_id;
            IF related_school IS DISTINCT FROM expected_school THEN
                RAISE EXCEPTION 'Class form master belongs to another school';
            END IF;
        END IF;
    ELSIF TG_TABLE_NAME = 'student_enrollments' THEN
        SELECT school_id INTO expected_school FROM students WHERE id = NEW.student_id;
        SELECT school_id INTO related_school FROM academic_years WHERE id = NEW.academic_year_id;
        IF expected_school IS DISTINCT FROM NEW.school_id OR related_school IS DISTINCT FROM NEW.school_id THEN
            RAISE EXCEPTION 'Cross-school student enrollment is forbidden';
        END IF;
        SELECT ay.school_id INTO related_school
          FROM school_classes sc JOIN academic_years ay ON ay.id = sc.academic_year_id
         WHERE sc.id = NEW.class_id;
        IF related_school IS DISTINCT FROM NEW.school_id THEN
            RAISE EXCEPTION 'Enrollment class belongs to another school';
        END IF;
    ELSIF TG_TABLE_NAME = 'class_subject' THEN
        SELECT ay.school_id INTO expected_school
          FROM school_classes sc JOIN academic_years ay ON ay.id = sc.academic_year_id
         WHERE sc.id = NEW.class_id;
        SELECT school_id INTO related_school FROM subjects WHERE id = NEW.subject_id;
        IF expected_school IS DISTINCT FROM related_school THEN
            RAISE EXCEPTION 'Cross-school class subject is forbidden';
        END IF;
        IF NEW.teacher_id IS NOT NULL THEN
            SELECT school_id INTO related_school FROM teachers WHERE id = NEW.teacher_id;
            IF expected_school IS DISTINCT FROM related_school THEN
                RAISE EXCEPTION 'Cross-school teacher assignment is forbidden';
            END IF;
        END IF;
    ELSIF TG_TABLE_NAME = 'parent_student' THEN
        SELECT school_id INTO expected_school FROM parents WHERE id = NEW.parent_id;
        SELECT school_id INTO related_school FROM students WHERE id = NEW.student_id;
        IF expected_school IS DISTINCT FROM related_school THEN
            RAISE EXCEPTION 'Cross-school parent/student link is forbidden';
        END IF;
    ELSIF TG_TABLE_NAME = 'attendance_sessions' THEN
        SELECT ay.school_id INTO expected_school
          FROM school_classes sc JOIN academic_years ay ON ay.id = sc.academic_year_id
         WHERE sc.id = NEW.class_id;
        IF NEW.term_id IS NOT NULL THEN
            SELECT ay.school_id INTO related_school
              FROM terms t JOIN academic_years ay ON ay.id = t.academic_year_id
             WHERE t.id = NEW.term_id;
            IF related_school IS DISTINCT FROM expected_school THEN
                RAISE EXCEPTION 'Attendance term belongs to another school';
            END IF;
        END IF;
    ELSIF TG_TABLE_NAME = 'attendance_records' THEN
        SELECT ay.school_id INTO expected_school
          FROM attendance_sessions ats
          JOIN school_classes sc ON sc.id = ats.class_id
          JOIN academic_years ay ON ay.id = sc.academic_year_id
         WHERE ats.id = NEW.session_id;
        SELECT school_id INTO related_school FROM students WHERE id = NEW.student_id;
        IF related_school IS DISTINCT FROM expected_school THEN
            RAISE EXCEPTION 'Attendance student belongs to another school';
        END IF;
    ELSIF TG_TABLE_NAME = 'assessments' THEN
        SELECT ay.school_id INTO expected_school
          FROM school_classes sc JOIN academic_years ay ON ay.id = sc.academic_year_id
         WHERE sc.id = NEW.class_id;
        SELECT ay.school_id INTO related_school
          FROM terms t JOIN academic_years ay ON ay.id = t.academic_year_id
         WHERE t.id = NEW.term_id;
        IF related_school IS DISTINCT FROM expected_school THEN
            RAISE EXCEPTION 'Assessment term belongs to another school';
        END IF;
        SELECT school_id INTO related_school FROM subjects WHERE id = NEW.subject_id;
        IF related_school IS DISTINCT FROM expected_school THEN
            RAISE EXCEPTION 'Assessment subject belongs to another school';
        END IF;
        IF NEW.teacher_id IS NOT NULL THEN
            SELECT school_id INTO related_school FROM teachers WHERE id = NEW.teacher_id;
            IF related_school IS DISTINCT FROM expected_school THEN
                RAISE EXCEPTION 'Assessment teacher belongs to another school';
            END IF;
        END IF;
    ELSIF TG_TABLE_NAME = 'grade_entries' THEN
        SELECT ay.school_id INTO expected_school
          FROM assessments a
          JOIN school_classes sc ON sc.id = a.class_id
          JOIN academic_years ay ON ay.id = sc.academic_year_id
         WHERE a.id = NEW.assessment_id;
        SELECT school_id INTO related_school FROM students WHERE id = NEW.student_id;
        IF related_school IS DISTINCT FROM expected_school THEN
            RAISE EXCEPTION 'Grade student belongs to another school';
        END IF;
    ELSIF TG_TABLE_NAME = 'fee_structures' THEN
        SELECT school_id INTO expected_school FROM academic_years WHERE id = NEW.academic_year_id;
        IF NEW.class_id IS NOT NULL THEN
            SELECT ay.school_id INTO related_school
              FROM school_classes sc JOIN academic_years ay ON ay.id = sc.academic_year_id
             WHERE sc.id = NEW.class_id AND sc.academic_year_id = NEW.academic_year_id;
            IF related_school IS DISTINCT FROM expected_school THEN
                RAISE EXCEPTION 'Fee class and academic year do not match';
            END IF;
        END IF;
    ELSIF TG_TABLE_NAME = 'invoices' THEN
        SELECT school_id INTO expected_school FROM students WHERE id = NEW.student_id;
        SELECT school_id INTO related_school FROM academic_years WHERE id = NEW.academic_year_id;
        IF expected_school IS DISTINCT FROM related_school THEN
            RAISE EXCEPTION 'Cross-school invoice is forbidden';
        END IF;
    ELSIF TG_TABLE_NAME = 'invoice_items' THEN
        IF NEW.fee_structure_id IS NOT NULL THEN
            SELECT academic_year_id INTO expected_school FROM invoices WHERE id = NEW.invoice_id;
            SELECT academic_year_id INTO related_school FROM fee_structures WHERE id = NEW.fee_structure_id;
            IF related_school IS DISTINCT FROM expected_school THEN
                RAISE EXCEPTION 'Invoice item fee belongs to another academic year';
            END IF;
        END IF;
    ELSIF TG_TABLE_NAME = 'payments' THEN
        SELECT s.school_id INTO expected_school FROM students s WHERE s.id = NEW.student_id;
        SELECT s.school_id INTO related_school
          FROM invoices i JOIN students s ON s.id = i.student_id
         WHERE i.id = NEW.invoice_id AND i.student_id = NEW.student_id;
        IF expected_school IS NULL OR expected_school IS DISTINCT FROM related_school THEN
            RAISE EXCEPTION 'Payment student and invoice do not match';
        END IF;
    ELSIF TG_TABLE_NAME = 'timetable_slots' THEN
        SELECT ay.school_id INTO expected_school
          FROM school_classes sc JOIN academic_years ay ON ay.id = sc.academic_year_id
         WHERE sc.id = NEW.class_id;
        IF NEW.subject_id IS NOT NULL THEN
            SELECT school_id INTO related_school FROM subjects WHERE id = NEW.subject_id;
            IF related_school IS DISTINCT FROM expected_school THEN
                RAISE EXCEPTION 'Timetable subject belongs to another school';
            END IF;
        END IF;
        IF NEW.teacher_id IS NOT NULL THEN
            SELECT school_id INTO related_school FROM teachers WHERE id = NEW.teacher_id;
            IF related_school IS DISTINCT FROM expected_school THEN
                RAISE EXCEPTION 'Timetable teacher belongs to another school';
            END IF;
        END IF;
    ELSIF TG_TABLE_NAME = 'promotion_policies' THEN
        IF NEW.academic_year_id IS NOT NULL THEN
            SELECT school_id INTO related_school FROM academic_years WHERE id = NEW.academic_year_id;
            IF related_school IS DISTINCT FROM NEW.school_id THEN
                RAISE EXCEPTION 'Promotion policy year belongs to another school';
            END IF;
        END IF;
    ELSIF TG_TABLE_NAME = 'academic_decisions' THEN
        SELECT school_id INTO expected_school FROM students WHERE id = NEW.student_id;
        SELECT school_id INTO related_school FROM academic_years WHERE id = NEW.academic_year_id;
        IF expected_school IS DISTINCT FROM NEW.school_id OR related_school IS DISTINCT FROM NEW.school_id THEN
            RAISE EXCEPTION 'Academic decision crosses school boundaries';
        END IF;
        SELECT school_id INTO related_school FROM student_enrollments
         WHERE id = NEW.enrollment_id AND student_id = NEW.student_id AND academic_year_id = NEW.academic_year_id;
        IF related_school IS DISTINCT FROM NEW.school_id THEN
            RAISE EXCEPTION 'Academic decision enrollment does not match';
        END IF;
        SELECT school_id INTO related_school FROM promotion_policies WHERE id = NEW.promotion_policy_id;
        IF related_school IS DISTINCT FROM NEW.school_id THEN
            RAISE EXCEPTION 'Academic decision policy belongs to another school';
        END IF;
    ELSIF TG_TABLE_NAME = 'school_year_transitions' THEN
        SELECT school_id INTO expected_school FROM academic_years WHERE id = NEW.from_academic_year_id;
        SELECT school_id INTO related_school FROM academic_years WHERE id = NEW.to_academic_year_id;
        IF expected_school IS DISTINCT FROM NEW.school_id OR related_school IS DISTINCT FROM NEW.school_id THEN
            RAISE EXCEPTION 'School year transition crosses school boundaries';
        END IF;
    ELSIF TG_TABLE_NAME = 'notifications' THEN
        SELECT school_id INTO expected_school FROM users WHERE id = NEW.user_id;
        IF NEW.sender_id IS NOT NULL THEN
            SELECT school_id INTO related_school FROM users WHERE id = NEW.sender_id;
            IF related_school IS NOT NULL AND expected_school IS DISTINCT FROM related_school THEN
                RAISE EXCEPTION 'Notification sender belongs to another school';
            END IF;
        END IF;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER student_enrollments_tenant_guard
BEFORE INSERT OR UPDATE ON student_enrollments
FOR EACH ROW EXECUTE FUNCTION clyvero_assert_tenant_integrity();

CREATE TRIGGER students_tenant_guard
BEFORE INSERT OR UPDATE ON students
FOR EACH ROW EXECUTE FUNCTION clyvero_assert_tenant_integrity();

CREATE TRIGGER teachers_tenant_guard
BEFORE INSERT OR UPDATE ON teachers
FOR EACH ROW EXECUTE FUNCTION clyvero_assert_tenant_integrity();

CREATE TRIGGER parents_tenant_guard
BEFORE INSERT OR UPDATE ON parents
FOR EACH ROW EXECUTE FUNCTION clyvero_assert_tenant_integrity();

CREATE TRIGGER school_classes_tenant_guard
BEFORE INSERT OR UPDATE ON school_classes
FOR EACH ROW EXECUTE FUNCTION clyvero_assert_tenant_integrity();

CREATE TRIGGER class_subject_tenant_guard
BEFORE INSERT OR UPDATE ON class_subject
FOR EACH ROW EXECUTE FUNCTION clyvero_assert_tenant_integrity();

CREATE TRIGGER parent_student_tenant_guard
BEFORE INSERT OR UPDATE ON parent_student
FOR EACH ROW EXECUTE FUNCTION clyvero_assert_tenant_integrity();

CREATE TRIGGER attendance_sessions_tenant_guard
BEFORE INSERT OR UPDATE ON attendance_sessions
FOR EACH ROW EXECUTE FUNCTION clyvero_assert_tenant_integrity();

CREATE TRIGGER attendance_records_tenant_guard
BEFORE INSERT OR UPDATE ON attendance_records
FOR EACH ROW EXECUTE FUNCTION clyvero_assert_tenant_integrity();

CREATE TRIGGER assessments_tenant_guard
BEFORE INSERT OR UPDATE ON assessments
FOR EACH ROW EXECUTE FUNCTION clyvero_assert_tenant_integrity();

CREATE TRIGGER grade_entries_tenant_guard
BEFORE INSERT OR UPDATE ON grade_entries
FOR EACH ROW EXECUTE FUNCTION clyvero_assert_tenant_integrity();

CREATE TRIGGER fee_structures_tenant_guard
BEFORE INSERT OR UPDATE ON fee_structures
FOR EACH ROW EXECUTE FUNCTION clyvero_assert_tenant_integrity();

CREATE TRIGGER invoices_tenant_guard
BEFORE INSERT OR UPDATE ON invoices
FOR EACH ROW EXECUTE FUNCTION clyvero_assert_tenant_integrity();

CREATE TRIGGER invoice_items_tenant_guard
BEFORE INSERT OR UPDATE ON invoice_items
FOR EACH ROW EXECUTE FUNCTION clyvero_assert_tenant_integrity();

CREATE TRIGGER payments_tenant_guard
BEFORE INSERT OR UPDATE ON payments
FOR EACH ROW EXECUTE FUNCTION clyvero_assert_tenant_integrity();

CREATE TRIGGER timetable_slots_tenant_guard
BEFORE INSERT OR UPDATE ON timetable_slots
FOR EACH ROW EXECUTE FUNCTION clyvero_assert_tenant_integrity();

CREATE TRIGGER promotion_policies_tenant_guard
BEFORE INSERT OR UPDATE ON promotion_policies
FOR EACH ROW EXECUTE FUNCTION clyvero_assert_tenant_integrity();

CREATE TRIGGER academic_decisions_tenant_guard
BEFORE INSERT OR UPDATE ON academic_decisions
FOR EACH ROW EXECUTE FUNCTION clyvero_assert_tenant_integrity();

CREATE TRIGGER school_year_transitions_tenant_guard
BEFORE INSERT OR UPDATE ON school_year_transitions
FOR EACH ROW EXECUTE FUNCTION clyvero_assert_tenant_integrity();

CREATE TRIGGER notifications_tenant_guard
BEFORE INSERT OR UPDATE ON notifications
FOR EACH ROW EXECUTE FUNCTION clyvero_assert_tenant_integrity();
SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
DROP TRIGGER IF EXISTS payments_tenant_guard ON payments;
DROP TRIGGER IF EXISTS notifications_tenant_guard ON notifications;
DROP TRIGGER IF EXISTS school_year_transitions_tenant_guard ON school_year_transitions;
DROP TRIGGER IF EXISTS academic_decisions_tenant_guard ON academic_decisions;
DROP TRIGGER IF EXISTS promotion_policies_tenant_guard ON promotion_policies;
DROP TRIGGER IF EXISTS timetable_slots_tenant_guard ON timetable_slots;
DROP TRIGGER IF EXISTS invoice_items_tenant_guard ON invoice_items;
DROP TRIGGER IF EXISTS invoices_tenant_guard ON invoices;
DROP TRIGGER IF EXISTS fee_structures_tenant_guard ON fee_structures;
DROP TRIGGER IF EXISTS grade_entries_tenant_guard ON grade_entries;
DROP TRIGGER IF EXISTS assessments_tenant_guard ON assessments;
DROP TRIGGER IF EXISTS attendance_records_tenant_guard ON attendance_records;
DROP TRIGGER IF EXISTS attendance_sessions_tenant_guard ON attendance_sessions;
DROP TRIGGER IF EXISTS parent_student_tenant_guard ON parent_student;
DROP TRIGGER IF EXISTS class_subject_tenant_guard ON class_subject;
DROP TRIGGER IF EXISTS student_enrollments_tenant_guard ON student_enrollments;
DROP TRIGGER IF EXISTS school_classes_tenant_guard ON school_classes;
DROP TRIGGER IF EXISTS parents_tenant_guard ON parents;
DROP TRIGGER IF EXISTS teachers_tenant_guard ON teachers;
DROP TRIGGER IF EXISTS students_tenant_guard ON students;
DROP FUNCTION IF EXISTS clyvero_assert_tenant_integrity();
SQL);
    }
};
