# The Laureates · File Tree

Generated overview of every file in the monorepo. Use this as a map when navigating the codebase.

## Backend (Laravel 12)

```
backend/
├── app/
│   ├── DTO/                            Plain data carriers between layers
│   │   ├── Academic/
│   │   ├── Auth/
│   │   ├── ParentGuardian/
│   │   ├── Student/
│   │   └── Teacher/
│   ├── Enums/
│   │   ├── AttendanceStatus.php
│   │   ├── AuditAction.php
│   │   ├── StudentStatus.php
│   │   ├── TeacherStatus.php
│   │   └── UserRole.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/ApiController.php   Standard envelope helpers (ok, created, error)
│   │   │   └── Api/V1/
│   │   │       ├── AcademicController.php
│   │   │       ├── AttendanceController.php
│   │   │       ├── AuditController.php
│   │   │       ├── AuthController.php
│   │   │       ├── DashboardController.php
│   │   │       ├── FinanceController.php
│   │   │       ├── GradesController.php
│   │   │       ├── NotificationController.php
│   │   │       ├── ParentController.php
│   │   │       ├── ReportCardController.php
│   │   │       ├── SettingsController.php
│   │   │       ├── StudentController.php
│   │   │       ├── TeacherController.php
│   │   │       ├── TimetableController.php
│   │   │       └── UserManagementController.php
│   │   ├── Middleware/
│   │   ├── Requests/                   FormRequests grouped by module
│   │   │   ├── Academic/StoreYearRequest.php
│   │   │   ├── Academic/StoreTermRequest.php
│   │   │   ├── Academic/StoreSubjectRequest.php
│   │   │   ├── Academic/StoreClassRequest.php
│   │   │   ├── Academic/AttachSubjectRequest.php
│   │   │   ├── Attendance/OpenSessionRequest.php
│   │   │   ├── Attendance/RecordAttendanceRequest.php
│   │   │   ├── Auth/LoginRequest.php
│   │   │   ├── Finance/StoreFeeRequest.php
│   │   │   ├── Finance/GenerateInvoiceRequest.php
│   │   │   ├── Finance/RecordPaymentRequest.php
│   │   │   ├── Grades/StoreAssessmentRequest.php
│   │   │   ├── Grades/RecordGradesRequest.php
│   │   │   ├── ParentGuardian/*.php
│   │   │   ├── Student/*.php
│   │   │   ├── Teacher/*.php
│   │   │   └── Timetable/StoreSlotRequest.php
│   │   └── Resources/
│   │       ├── AcademicYearResource.php
│   │       ├── AssessmentResource.php
│   │       ├── AttendanceSessionResource.php
│   │       ├── FeeStructureResource.php
│   │       ├── InvoiceResource.php
│   │       ├── PaymentResource.php
│   │       ├── ParentGuardianResource.php
│   │       ├── SchoolClassResource.php
│   │       ├── StudentResource.php
│   │       ├── SubjectResource.php
│   │       ├── TeacherResource.php
│   │       ├── TermResource.php
│   │       └── TimetableSlotResource.php
│   ├── Models/
│   │   ├── AcademicYear.php
│   │   ├── ActivityLog.php
│   │   ├── Assessment.php
│   │   ├── AttendanceRecord.php
│   │   ├── AttendanceSession.php
│   │   ├── AuditLog.php                Immutable — booted() blocks update/delete
│   │   ├── FeeStructure.php
│   │   ├── GradeEntry.php
│   │   ├── Invoice.php
│   │   ├── InvoiceItem.php
│   │   ├── Notification.php
│   │   ├── ParentGuardian.php          Model name avoids PHP "parent" keyword
│   │   ├── Payment.php
│   │   ├── Permission.php
│   │   ├── Role.php
│   │   ├── School.php
│   │   ├── SchoolClass.php             $table = 'school_classes'
│   │   ├── Student.php
│   │   ├── Subject.php
│   │   ├── Teacher.php
│   │   ├── Term.php
│   │   ├── TimetableSlot.php
│   │   └── User.php
│   ├── Observers/AuditObserver.php
│   ├── Policies/
│   │   ├── AcademicYearPolicy.php
│   │   ├── ParentPolicy.php
│   │   ├── SchoolClassPolicy.php
│   │   ├── StudentPolicy.php
│   │   ├── SubjectPolicy.php
│   │   ├── TeacherPolicy.php
│   │   └── TermPolicy.php
│   ├── Providers/
│   │   ├── AppServiceProvider.php      Registers Gate::policy() bindings
│   │   └── RepositoryServiceProvider.php
│   ├── Repositories/
│   │   ├── Contracts/                  Repository interfaces
│   │   │   ├── AcademicYearRepositoryInterface.php
│   │   │   ├── ClassRepositoryInterface.php
│   │   │   ├── ParentRepositoryInterface.php
│   │   │   ├── RepositoryInterface.php
│   │   │   ├── StudentRepositoryInterface.php
│   │   │   ├── SubjectRepositoryInterface.php
│   │   │   ├── TeacherRepositoryInterface.php
│   │   │   ├── TermRepositoryInterface.php
│   │   │   └── UserRepositoryInterface.php
│   │   └── Eloquent/                   Concrete repositories
│   │       └── Eloquent*Repository.php (one per contract)
│   ├── Services/
│   │   ├── Academic/AcademicService.php
│   │   ├── Attendance/AttendanceService.php
│   │   ├── Auth/AuthService.php
│   │   ├── BaseService.php             Provides transaction() helper
│   │   ├── Finance/FinanceService.php
│   │   ├── Grades/GradesService.php
│   │   ├── Notification/NotificationService.php
│   │   ├── ParentGuardian/ParentService.php
│   │   ├── ReportCard/ReportCardService.php
│   │   ├── Student/StudentService.php
│   │   ├── Teacher/TeacherService.php
│   │   └── Timetable/TimetableService.php
│   └── Traits/
│       ├── Auditable.php
│       └── HasUuid.php
├── database/
│   ├── factories/
│   ├── migrations/                     Phase-prefixed (2026_MM_DD_…)
│   │   ├── 2026_01_…create_users_…
│   │   ├── 2026_02_01_000001_create_students_table.php
│   │   ├── 2026_03_01_000001_create_teachers_table.php
│   │   ├── 2026_04_01_000001_create_parents_and_pivots.php
│   │   ├── 2026_05_01_000001_create_academic_structure_tables.php
│   │   ├── 2026_06_01_000001_create_attendance_tables.php
│   │   ├── 2026_07_01_000001_create_grades_tables.php
│   │   ├── 2026_08_01_000001_create_timetable_tables.php
│   │   ├── 2026_09_01_000001_create_finance_tables.php
│   │   └── 2026_12_01_000001_create_notifications_table.php
│   └── seeders/
│       ├── AdminUserSeeder.php
│       ├── DatabaseSeeder.php
│       ├── PermissionSeeder.php        ~80 permissions across all modules
│       ├── RoleSeeder.php
│       └── SchoolSeeder.php
├── resources/views/
│   └── reports/report-card.blade.php   A4 PDF template
├── routes/
│   ├── api.php                         Includes every v1 route file
│   └── api/v1/
│       ├── academic.php
│       ├── admin.php                   Settings + Users + Audit
│       ├── attendance.php
│       ├── auth.php
│       ├── dashboard.php
│       ├── finance.php
│       ├── grades.php
│       ├── notifications.php
│       ├── parents.php
│       ├── reports.php
│       ├── students.php
│       ├── teachers.php
│       └── timetable.php
└── tests/
    ├── Feature/
    │   ├── Academic/AcademicApiTest.php
    │   ├── Attendance/AttendanceApiTest.php
    │   ├── Auth/LoginTest.php
    │   ├── Finance/FinanceApiTest.php
    │   ├── ParentGuardian/ParentApiTest.php
    │   ├── Student/StudentApiTest.php
    │   ├── Student/StudentServiceTest.php
    │   └── Teacher/TeacherApiTest.php
    ├── TestCase.php
    └── Unit/
```

## Frontend (Next.js 15 App Router)

```
frontend/
├── public/
└── src/
    ├── app/
    │   ├── (public)/                   Login, marketing
    │   └── (authenticated)/            All routes behind auth
    │       ├── admin/
    │       │   ├── dashboard/
    │       │   ├── students/
    │       │   ├── teachers/
    │       │   ├── parents/
    │       │   ├── academic/
    │       │   │   ├── years/
    │       │   │   ├── terms/
    │       │   │   ├── subjects/
    │       │   │   └── classes/[id]/
    │       │   ├── attendance/[classId]/
    │       │   ├── grades/[classId]/[subjectId]/
    │       │   ├── timetable/[classId]/
    │       │   ├── finance/
    │       │   │   ├── fees/
    │       │   │   └── invoices/[id]/
    │       │   ├── report-cards/
    │       │   ├── notifications/
    │       │   ├── users/
    │       │   ├── audit-logs/
    │       │   └── settings/
    │       ├── secretary/              Re-exports admin pages where appropriate
    │       ├── teacher/
    │       └── parent/
    ├── components/
    │   ├── academic/
    │   ├── attendance/
    │   ├── finance/
    │   ├── grades/
    │   ├── layout/                     Sidebar, Topbar, Footer
    │   ├── parents/
    │   ├── students/
    │   ├── teachers/
    │   ├── timetable/
    │   └── ui/                         Button, Card, DataTable, Modal, …
    ├── config/navigation.ts
    ├── hooks/
    │   ├── academic/
    │   ├── admin/
    │   ├── attendance/
    │   ├── dashboard/
    │   ├── finance/
    │   ├── grades/
    │   ├── notifications/
    │   ├── parents/
    │   ├── students/
    │   ├── teachers/
    │   └── timetable/
    ├── lib/
    │   ├── api/                        client.ts + one file per module
    │   └── utils/                      cn, debounce, dates
    ├── store/                          Zustand stores (auth, ui)
    └── types/                          Shared TypeScript types
```
