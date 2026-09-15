<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Enums\Gender;
use App\Models\AcademicYear;
use App\Models\CarteScolaireImport;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

/** CSV/XLSX import for an administrator-exported Carte Scolaire list.
 * No external credentials, OTP, or automated access are used or stored. */
final class CarteScolaireImportService
{
    /** @return array<string, mixed> */
    public function preview(UploadedFile $file, AcademicYear $year, User $user, string $cycle): array
    {
        $rows = $this->readRows($file);
        $classes = SchoolClass::query()->where('academic_year_id', $year->id)->get()
            ->groupBy(fn (SchoolClass $class) => $this->key($class->name));
        $preview = [];
        $summary = ['total' => 0, 'ready' => 0, 'unmatched_class' => 0, 'invalid' => 0, 'existing' => 0];

        foreach ($rows as $index => $row) {
            $matricule = strtoupper(trim((string) ($row['matricule'] ?? $row['nationalmatricule'] ?? '')));
            $firstName = trim((string) ($row['firstname'] ?? $row['prenom'] ?? ''));
            $lastName = trim((string) ($row['lastname'] ?? $row['nom'] ?? ''));
            $classLabel = trim((string) ($row['class'] ?? $row['classe'] ?? ''));
            $gender = strtolower(trim((string) ($row['gender'] ?? $row['sexe'] ?? '')));
            $birthDate = trim((string) ($row['dateofbirth'] ?? $row['datenaissance'] ?? ''));
            $status = 'ready'; $reason = null; $classId = null;

            if ($matricule === '' || $firstName === '' || $lastName === '' || $classLabel === '' || $birthDate === '' || strtotime($birthDate) === false || ! in_array($gender, ['male', 'female', 'm', 'f'], true)) {
                $status = 'invalid'; $reason = 'Required identity, class, date of birth, or gender is missing.';
            } elseif (($matches = $classes->get($this->key($classLabel), collect()))->count() !== 1) {
                $status = 'unmatched_class'; $reason = 'No single configured class exactly matches the source class.';
            } elseif (Student::query()->where('admission_number', $matricule)->exists()) {
                $status = 'existing'; $reason = 'A student with this national matricule already exists in this school.';
            } else {
                $classId = $matches->first()->id;
            }
            $summary['total']++;
            $summary[$status]++;
            $preview[] = compact('index', 'matricule', 'firstName', 'lastName', 'classLabel', 'classId', 'gender', 'birthDate', 'status', 'reason');
        }

        $import = CarteScolaireImport::create([
            'school_id' => $user->school_id, 'academic_year_id' => $year->id, 'created_by' => $user->id,
            'source_filename' => $file->getClientOriginalName(), 'source_hash' => hash_file('sha256', $file->getRealPath()),
            'status' => 'draft', 'preview_rows' => $preview, 'summary' => $summary + ['cycle' => $cycle],
        ]);
        return ['import' => $import, 'preview' => $preview, 'summary' => $summary];
    }

    public function approveAndImport(CarteScolaireImport $import, User $user): CarteScolaireImport
    {
        if ($import->school_id !== $user->school_id) {
            abort(403, 'This import belongs to a different school.');
        }
        if ($import->status !== 'draft') throw ValidationException::withMessages(['import' => ['This import is no longer awaiting approval.']]);
        return DB::transaction(function () use ($import, $user): CarteScolaireImport {
            $created = 0;
            foreach ($import->preview_rows as $row) {
                if (($row['status'] ?? null) !== 'ready') continue;
                if (Student::query()->where('admission_number', $row['matricule'])->exists()) continue;
                $student = Student::create([
                    'school_id' => $user->school_id, 'admission_number' => $row['matricule'],
                    'first_name' => $row['firstName'], 'last_name' => $row['lastName'],
                    'gender' => in_array(strtolower($row['gender']), ['male', 'm'], true) ? Gender::Male->value : Gender::Female->value,
                    'date_of_birth' => date('Y-m-d', strtotime($row['birthDate'])), 'enrollment_date' => now()->toDateString(),
                    'academic_year_id' => $import->academic_year_id, 'class_id' => $row['classId'],
                    'cycle' => $import->summary['cycle'] ?? 'secondary_general', 'status' => 'active', 'created_by' => $user->id,
                ]);
                StudentEnrollment::create([
                    'school_id' => $user->school_id,
                    'student_id' => $student->id,
                    'academic_year_id' => $import->academic_year_id,
                    'class_id' => $row['classId'],
                    'enrolled_at' => now()->toDateString(),
                    'status' => 'active',
                    'created_by' => $user->id,
                ]);
                $created++;
            }
            $import->update(['status' => 'imported', 'approved_by' => $user->id, 'approved_at' => now(), 'imported_at' => now(), 'summary' => $import->summary + ['imported' => $created]]);
            return $import->refresh();
        });
    }

    /** @return array<int, array<string, string>> */
    private function readRows(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if ($extension === 'xlsx') {
            try {
                $sheet = IOFactory::load($file->getRealPath())->getActiveSheet();
            } catch (Throwable $exception) {
                report($exception);
                throw ValidationException::withMessages([
                    'file' => ['The selected spreadsheet is invalid or unsafe and could not be read.'],
                ]);
            }
            $matrix = $sheet->toArray(null, true, true, false);
            $headers = array_shift($matrix);
            if (! $headers) throw ValidationException::withMessages(['file' => ['The spreadsheet has no header row.']]);
            $headers = $this->normaliseHeaders($headers);
            $rows = [];
            foreach ($matrix as $values) {
                if (count($rows) >= 5000) throw ValidationException::withMessages(['file' => ['An import may contain at most 5,000 rows.']]);
                if (count(array_filter($values, fn ($v) => trim((string) $v) !== '')) === 0) continue;
                $rows[] = array_combine($headers, array_map('strval', array_slice(array_pad($values, count($headers), ''), 0, count($headers))));
            }
            return $rows;
        }
        if ($extension !== 'csv') throw ValidationException::withMessages(['file' => ['Upload an official CSV or XLSX export.']]);
        $handle = fopen($file->getRealPath(), 'rb');
        $headers = fgetcsv($handle);
        if (! $headers) throw ValidationException::withMessages(['file' => ['The CSV file has no header row.']]);
        $headers = $this->normaliseHeaders($headers);
        $rows = [];
        while (($values = fgetcsv($handle)) !== false) {
            if (count($rows) >= 5000) throw ValidationException::withMessages(['file' => ['An import may contain at most 5,000 rows.']]);
            if (count(array_filter($values, fn ($v) => trim((string) $v) !== '')) === 0) continue;
            $rows[] = array_combine($headers, array_slice(array_pad($values, count($headers), ''), 0, count($headers)));
        }
        fclose($handle);
        return $rows;
    }

    private function key(string $value): string
    {
        return strtolower(preg_replace('/[^a-z0-9]+/i', '', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', trim($value)) ?: trim($value)));
    }

    /** @param array<int, mixed> $headers @return array<int, string> */
    private function normaliseHeaders(array $headers): array
    {
        $headers = array_map(fn ($header) => $this->key((string) $header), $headers);
        if (in_array('', $headers, true) || count($headers) !== count(array_unique($headers))) {
            throw ValidationException::withMessages(['file' => ['Header names must be present and unique.']]);
        }
        return $headers;
    }
}
