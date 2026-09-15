<?php

declare(strict_types=1);

namespace App\Services\Student;

use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\FeeStructure;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentImport;
use App\Models\StudentImportRow;
use App\Services\BaseService;
use App\Services\Finance\FinanceService;
use App\Services\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final class OfficialStudentImportService extends BaseService
{
    public const FIELDS = [
        'official_matricule' => ['label' => 'Official matricule', 'required' => true],
        'full_name' => ['label' => 'Student full name', 'required' => false],
        'first_name' => ['label' => 'First name', 'required' => false],
        'last_name' => ['label' => 'Last name / surname', 'required' => false],
        'class_name' => ['label' => 'Class', 'required' => true],
        'gender' => ['label' => 'Gender', 'required' => false],
        'date_of_birth' => ['label' => 'Date of birth', 'required' => false],
        'place_of_birth' => ['label' => 'Place of birth', 'required' => false],
        'nationality' => ['label' => 'Nationality', 'required' => false],
        'phone' => ['label' => 'Student phone', 'required' => false],
        'email' => ['label' => 'Student email', 'required' => false],
        'address' => ['label' => 'Address', 'required' => false],
        'city' => ['label' => 'City', 'required' => false],
        'previous_school' => ['label' => 'Previous school', 'required' => false],
    ];

    private const ALIASES = [
        'official_matricule' => ['matricule_national', 'matricule_no', 'matricule', 'official_matricule', 'national_registration_number', 'registration_number'],
        'first_name' => ['first_name', 'first_names', 'prenom', 'prenoms', 'given_name', 'given_names'],
        'last_name' => ['last_name', 'nom', 'surname', 'family_name'],
        'full_name' => ['noms_et_prenoms', 'nom_et_prenom', 'name', 'names', 'full_name', 'student_name'],
        'class_name' => ['classe', 'class', 'niveau', 'class_name', 'class_name_level'],
        'gender' => ['sexe', 'sex', 'gender'],
        'date_of_birth' => ['date_de_naissance', 'date_naissance', 'date_of_birth', 'birth_date'],
        'place_of_birth' => ['lieu_de_naissance', 'lieu_naissance', 'place_of_birth'],
        'nationality' => ['nationalite', 'nationality'],
        'phone' => ['telephone', 'phone', 'student_phone'],
        'email' => ['email', 'student_email'],
        'address' => ['adresse', 'address'],
        'city' => ['ville', 'city'],
        'previous_school' => ['etablissement_precedent', 'previous_school'],
    ];

    public function __construct(
        private readonly TabularStudentFileReader $reader,
        private readonly TenantContext $tenant,
        private readonly FinanceService $finance,
    ) {}

    /** @return array<string, mixed> */
    public function analyze(UploadedFile $file, AcademicYear $year): array
    {
        $rows = $this->readRows($file);
        $columns = array_keys($rows[0]);

        return [
            'detected_columns' => $columns,
            'suggested_mapping' => $this->suggestMapping($columns),
            'supported_fields' => collect(self::FIELDS)->map(fn (array $config, string $key): array => [
                'key' => $key, 'label' => $config['label'], 'required' => $config['required'],
            ])->values()->all(),
            'sample_rows' => array_slice($rows, 0, 5),
            'row_count' => count($rows),
            'active_academic_year' => ['id' => $year->id, 'title' => $year->title],
        ];
    }

    /** @param array<string, string> $columnMapping */
    public function preview(UploadedFile $file, AcademicYear $year, array $columnMapping = []): StudentImport
    {
        $rows = $this->readRows($file);
        $columns = array_keys($rows[0]);
        $mapping = $columnMapping ?: $this->suggestMapping($columns);
        $this->validateMapping($mapping, $columns);

        $normalizedRows = [];
        foreach ($rows as $index => $raw) {
            [$normalized, $errors] = $this->normalize($raw, $mapping);
            $normalizedRows[] = compact('index', 'raw', 'normalized', 'errors');
        }

        $matricules = collect($normalizedRows)->pluck('normalized.official_matricule')->filter()->unique()->values();
        $existing = Student::query()->whereIn('official_matricule', $matricules)->get()->keyBy('official_matricule');
        $classSuggestions = $this->suggestClasses($year, collect($normalizedRows)->pluck('normalized.class_name')->filter()->unique()->all());

        return $this->transaction(function () use ($file, $year, $columns, $mapping, $normalizedRows, $existing, $classSuggestions): StudentImport {
            $import = StudentImport::query()->create([
                'school_id' => $this->tenant->schoolId(), 'academic_year_id' => $year->id,
                'source' => 'spreadsheet', 'original_filename' => basename($file->getClientOriginalName()),
                'status' => 'previewed', 'detected_columns' => $columns, 'column_mapping' => $mapping,
                'class_mapping' => $classSuggestions, 'duplicate_action' => 'skip', 'imported_by' => Auth::id(),
            ]);

            $seen = [];
            $counts = ['total' => count($normalizedRows), 'ready' => 0, 'attention' => 0, 'valid' => 0, 'existing' => 0, 'duplicate' => 0, 'duplicate_file' => 0, 'error' => 0];
            foreach ($normalizedRows as $item) {
                $data = $item['normalized'];
                $errors = $item['errors'];
                $warnings = [];
                $matricule = $data['official_matricule'];
                $existingStudent = $matricule !== '' ? $existing->get($matricule) : null;
                if ($matricule !== '' && isset($seen[$matricule])) {
                    $errors['official_matricule'] = "Duplicate matricule '{$matricule}' inside this spreadsheet.";
                    $counts['duplicate_file']++;
                }
                if ($matricule !== '') $seen[$matricule] = true;
                if (($data['class_name'] ?? '') !== '' && empty($classSuggestions[$data['class_name']])) {
                    $warnings['class_name'] = "Class '{$data['class_name']}' does not exist by that exact name. Map it to an existing class before import.";
                }

                $status = $errors !== [] ? 'error' : ($existingStudent ? 'duplicate' : 'valid');
                $action = $existingStudent ? 'skip' : 'create';
                if ($status === 'error') $counts['error']++;
                elseif ($existingStudent) { $counts['existing']++; $counts['duplicate']++; $counts['attention']++; }
                else { $counts['valid']++; $counts['ready']++; if ($warnings !== []) $counts['attention']++; }

                StudentImportRow::query()->create([
                    'student_import_id' => $import->id, 'row_number' => $item['index'] + 2,
                    'fingerprint' => hash('sha256', implode('|', [$this->tenant->schoolId(), $matricule, $data['last_name'], $data['first_name'], $data['date_of_birth'] ?? ''])),
                    'raw_data' => $item['raw'], 'normalized_data' => $data, 'status' => $status,
                    'action' => $action, 'errors' => $errors ?: null, 'warnings' => $warnings ?: null,
                    'student_id' => $existingStudent?->id,
                ]);
            }
            $import->update(['summary' => $counts]);
            return $import->fresh(['academicYear', 'rows']) ?? $import;
        });
    }

    /** @param array<string, string> $classMapping */
    public function confirm(StudentImport $import, array $classMapping, string $duplicateAction): StudentImport
    {
        return $this->transaction(function () use ($import, $classMapping, $duplicateAction): StudentImport {
            DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ["student-spreadsheet-import:{$import->school_id}"]);
            $import = StudentImport::query()->whereKey($import->id)->lockForUpdate()->firstOrFail();
            if ($import->status === 'completed') return $import->load(['academicYear', 'rows']);

            $year = AcademicYear::query()->active()->whereKey($import->academic_year_id)->first();
            if (! $year) throw ValidationException::withMessages(['academic_year' => ['The academic year used by this preview is no longer active. Upload the file again.']]);

            $externalClasses = $import->rows()->pluck('normalized_data')->map(fn (?array $data): string => (string) ($data['class_name'] ?? ''))->filter()->unique()->values();
            $missingMappings = $externalClasses->filter(fn (string $name): bool => empty($classMapping[$name]));
            if ($missingMappings->isNotEmpty()) throw ValidationException::withMessages(['class_mapping' => ['Map every spreadsheet class before importing: '.$missingMappings->join(', ')]]);

            $classes = SchoolClass::query()->where('academic_year_id', $year->id)->whereIn('id', array_values($classMapping))->get()->keyBy('id');
            if ($classes->count() !== count(array_unique(array_values($classMapping)))) throw ValidationException::withMessages(['class_mapping' => ['One or more mapped classes do not belong to the active school year.']]);

            $import->update(['status' => 'processing', 'class_mapping' => $classMapping, 'duplicate_action' => $duplicateAction]);
            $summary = ['total' => $import->rows()->count(), 'processed' => 0, 'imported' => 0, 'new_students' => 0, 'existing_students_updated' => 0, 'skipped' => 0, 'errors' => 0, 'students_enrolled' => 0, 'fee_structures_assigned' => 0, 'students_without_fee_configuration' => 0, 'payments_created' => 0];

            foreach ($import->rows()->orderBy('row_number')->get() as $row) {
                if ($row->status === 'error') { $summary['errors']++; continue; }
                $data = $row->normalized_data;
                $classId = $classMapping[(string) $data['class_name']] ?? null;
                /** @var ?SchoolClass $class */
                $class = $classId ? $classes->get($classId) : null;
                if (! $class) { $row->update(['status' => 'error', 'errors' => ['class_name' => 'The mapped class is invalid.']]); $summary['errors']++; continue; }

                $student = Student::query()->where('official_matricule', $data['official_matricule'])->lockForUpdate()->first();
                if ($student && $duplicateAction === 'skip') {
                    $row->update(['status' => 'duplicate', 'action' => 'skip', 'student_id' => $student->id]);
                    $summary['skipped']++; $summary['processed']++; continue;
                }

                $attributes = $this->studentAttributes($data, $class, $year);
                if ($student) {
                    $student->update(array_filter($attributes, fn ($value, string $key): bool => $value !== null || in_array($key, ['class_id', 'academic_year_id'], true), ARRAY_FILTER_USE_BOTH));
                    $summary['existing_students_updated']++; $action = 'update';
                } else {
                    $student = Student::query()->create($attributes + [
                        'school_id' => $import->school_id, 'official_matricule' => $data['official_matricule'],
                        'admission_number' => $data['official_matricule'], 'status' => StudentStatus::Active->value, 'created_by' => Auth::id(),
                    ]);
                    $summary['new_students']++; $action = 'create';
                }

                StudentEnrollment::query()->updateOrCreate(
                    ['student_id' => $student->id, 'academic_year_id' => $year->id],
                    ['school_id' => $import->school_id, 'class_id' => $class->id, 'enrolled_at' => $year->start_date, 'status' => 'active', 'created_by' => Auth::id()],
                );
                $summary['students_enrolled']++;

                $hasFees = FeeStructure::query()->where('academic_year_id', $year->id)->where('is_required', true)
                    ->where(fn ($query) => $query->whereNull('class_id')->orWhere('class_id', $class->id))->exists();
                $warnings = $row->warnings ?? [];
                $invoiceId = null;
                if ($hasFees) {
                    $invoice = $this->finance->generateInvoiceForStudent($student, $year);
                    $invoiceId = $invoice->id; $summary['fee_structures_assigned']++;
                } else {
                    $warnings['fee_structure'] = "Student imported successfully, but no fee structure is configured for {$class->name}.";
                    $summary['students_without_fee_configuration']++;
                }

                $row->update(['status' => 'imported', 'action' => $action, 'student_id' => $student->id, 'invoice_id' => $invoiceId, 'fee_assigned' => $hasFees, 'warnings' => $warnings ?: null]);
                $summary['imported']++;
                $summary['processed']++;
            }

            $import->update(['status' => 'completed', 'summary' => $summary, 'completed_at' => now()]);
            return $import->fresh(['academicYear', 'rows']) ?? $import;
        });
    }

    /** @return array<int, array<string, string>> */
    private function readRows(UploadedFile $file): array
    {
        try {
            $rows = $this->reader->read($file);
        } catch (Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages([
                'file' => ['The selected spreadsheet is invalid or unsafe and could not be read.'],
            ]);
        }
        if ($rows === []) throw ValidationException::withMessages(['file' => ['The selected file contains no student rows.']]);
        if (count($rows) > 10000) throw ValidationException::withMessages(['file' => ['A single import cannot exceed 10,000 rows.']]);
        return $rows;
    }

    /** @param array<int, string> $columns @return array<string, string> */
    private function suggestMapping(array $columns): array
    {
        $normalizedColumns = collect($columns)->mapWithKeys(fn (string $column): array => [$this->key($column) => $column]);
        $mapping = [];
        foreach (self::ALIASES as $field => $aliases) foreach ($aliases as $alias) if ($normalizedColumns->has($alias)) { $mapping[$field] = $normalizedColumns->get($alias); break; }
        return $mapping;
    }

    /** @param array<string, string> $mapping @param array<int, string> $columns */
    private function validateMapping(array $mapping, array $columns): void
    {
        foreach (['official_matricule', 'class_name'] as $required) if (empty($mapping[$required])) throw ValidationException::withMessages(['column_mapping' => ["Map a spreadsheet column to {$required}."]]);
        if (empty($mapping['full_name']) && (empty($mapping['first_name']) || empty($mapping['last_name']))) throw ValidationException::withMessages(['column_mapping' => ['Map either Student full name, or both First name and Last name.']]);
        foreach ($mapping as $field => $column) if (! isset(self::FIELDS[$field]) || ! in_array($column, $columns, true)) throw ValidationException::withMessages(['column_mapping' => ["Invalid mapping for {$field}."]]);
        if (count(array_filter($mapping)) !== count(array_unique(array_filter($mapping)))) throw ValidationException::withMessages(['column_mapping' => ['A spreadsheet column cannot be mapped to multiple fields.']]);
    }

    /** @param array<int, string> $externalClasses @return array<string, string> */
    private function suggestClasses(AcademicYear $year, array $externalClasses): array
    {
        $classes = SchoolClass::query()->where('academic_year_id', $year->id)->active()->get();
        $byName = $classes->mapWithKeys(fn (SchoolClass $class): array => [$this->key($class->name) => $class->id]);
        $result = [];
        foreach ($externalClasses as $name) $result[$name] = (string) ($byName->get($this->key($name)) ?? '');
        return $result;
    }

    /** @param array<string, string> $raw @param array<string, string> $mapping @return array{array<string, mixed>, array<string, string>} */
    private function normalize(array $raw, array $mapping): array
    {
        $data = [];
        foreach (array_keys(self::FIELDS) as $field) $data[$field] = isset($mapping[$field]) ? trim((string) ($raw[$mapping[$field]] ?? '')) : '';
        if (($data['first_name'] === '' || $data['last_name'] === '') && $data['full_name'] !== '') {
            $parts = preg_split('/\s+/', trim($data['full_name'])) ?: [];
            if (count($parts) >= 2) { $data['last_name'] = $data['last_name'] ?: (string) array_shift($parts); $data['first_name'] = $data['first_name'] ?: implode(' ', $parts); }
        }
        unset($data['full_name']);
        $suppliedGender = $data['gender'];
        $suppliedDateOfBirth = $data['date_of_birth'];
        $data['official_matricule'] = mb_strtoupper(preg_replace('/\s+/', '', $data['official_matricule']) ?? '');
        $data['first_name'] = Str::title(mb_strtolower($data['first_name']));
        $data['last_name'] = mb_strtoupper($data['last_name']);
        $data['gender'] = $this->gender($data['gender']);
        $data['date_of_birth'] = $this->date($data['date_of_birth']);
        $data['email'] = $data['email'] !== '' ? mb_strtolower($data['email']) : null;
        foreach (['place_of_birth', 'nationality', 'phone', 'address', 'city', 'previous_school'] as $optional) $data[$optional] = $data[$optional] !== '' ? $data[$optional] : null;

        $errors = [];
        foreach (['official_matricule', 'first_name', 'last_name', 'class_name'] as $required) if (($data[$required] ?? '') === '') $errors[$required] = "Missing {$required}.";
        if ($suppliedGender !== '' && $data['gender'] === null) $errors['gender'] = "Invalid gender '{$suppliedGender}'.";
        if ($suppliedDateOfBirth !== '' && $data['date_of_birth'] === null) $errors['date_of_birth'] = "Invalid date of birth '{$suppliedDateOfBirth}'.";
        if ($data['email'] !== null && ! filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Invalid student email.';
        return [$data, $errors];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function studentAttributes(array $data, SchoolClass $class, AcademicYear $year): array
    {
        return [
            'first_name' => $data['first_name'], 'last_name' => $data['last_name'], 'gender' => $data['gender'],
            'date_of_birth' => $data['date_of_birth'], 'place_of_birth' => $data['place_of_birth'],
            'nationality' => $data['nationality'] ?: 'Cameroonian', 'phone' => $data['phone'], 'email' => $data['email'],
            'address' => $data['address'], 'city' => $data['city'], 'country' => 'Cameroon', 'previous_school' => $data['previous_school'],
            'class_id' => $class->id, 'academic_year_id' => $year->id, 'enrollment_date' => $year->start_date,
            'cycle' => $class->education_system, 'speciality' => $class->speciality,
        ];
    }

    private function gender(string $value): ?string
    {
        if ($value === '') return null;
        return match (mb_strtolower(trim($value))) {
            'm', 'male', 'masculin', 'garcon', 'garçon' => Gender::Male->value,
            'f', 'female', 'feminin', 'féminin', 'fille' => Gender::Female->value,
            default => null,
        };
    }

    private function date(string $value): ?string
    {
        if ($value === '') return null;
        try {
            if (is_numeric($value) && (float) $value > 1000) return CarbonImmutable::create(1899, 12, 30)->addDays((int) $value)->toDateString();
            return CarbonImmutable::parse($value)->toDateString();
        } catch (Throwable) { return null; }
    }

    private function key(string $value): string
    {
        return Str::of(Str::ascii($value))->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString();
    }
}
