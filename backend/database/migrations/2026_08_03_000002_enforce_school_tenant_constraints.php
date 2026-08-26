<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['email']);
            $table->uuid('school_id')->nullable(false)->change();
            $table->unique(['school_id', 'email'], 'users_school_email_unique');
        });

        Schema::table('students', function (Blueprint $table): void {
            $table->dropUnique(['admission_number']);
            $table->dropUnique(['email']);
            $table->uuid('school_id')->nullable(false)->change();
            $table->unique(['school_id', 'admission_number'], 'students_school_admission_unique');
            $table->unique(['school_id', 'email'], 'students_school_email_unique');
        });

        Schema::table('teachers', function (Blueprint $table): void {
            $table->dropUnique(['employee_number']);
            $table->dropUnique(['email']);
            $table->uuid('school_id')->nullable(false)->change();
            $table->unique(['school_id', 'employee_number'], 'teachers_school_employee_unique');
            $table->unique(['school_id', 'email'], 'teachers_school_email_unique');
        });

        Schema::table('parents', function (Blueprint $table): void {
            $table->dropUnique(['email']);
            $table->uuid('school_id')->nullable(false)->change();
            $table->unique(['school_id', 'email'], 'parents_school_email_unique');
        });

        Schema::table('subjects', function (Blueprint $table): void {
            $table->dropUnique(['name']);
            $table->dropUnique(['code']);
            $table->uuid('school_id')->nullable(false)->change();
            $table->unique(['school_id', 'name'], 'subjects_school_name_unique');
            $table->unique(['school_id', 'code'], 'subjects_school_code_unique');
        });
    }

    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table): void {
            $table->dropUnique('subjects_school_name_unique');
            $table->dropUnique('subjects_school_code_unique');
            $table->unique('name');
            $table->unique('code');
        });
        Schema::table('parents', function (Blueprint $table): void {
            $table->dropUnique('parents_school_email_unique');
            $table->unique('email');
        });
        Schema::table('teachers', function (Blueprint $table): void {
            $table->dropUnique('teachers_school_employee_unique');
            $table->dropUnique('teachers_school_email_unique');
            $table->unique('employee_number');
            $table->unique('email');
        });
        Schema::table('students', function (Blueprint $table): void {
            $table->dropUnique('students_school_admission_unique');
            $table->dropUnique('students_school_email_unique');
            $table->unique('admission_number');
            $table->unique('email');
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique('users_school_email_unique');
            $table->unique('email');
        });
    }
};
