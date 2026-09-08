<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\URL;

/**
 * The school (institution).
 *
 * Single-school architecture in Phase 1; table designed for future
 * multi-school expansion per SRS §1.
 *
 * @property string $id
 * @property string $access_code
 * @property string $school_name
 * @property ?string $slogan
 * @property ?string $logo
 * @property ?string $phone
 * @property string $email
 * @property ?string $address
 * @property ?string $website
 * @property ?string $city
 * @property ?string $country
 * @property ?string $current_academic_year_id
 * @property ?array $report_card_remarks
 */
class School extends Model
{
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    protected $fillable = [
        'slug', 'school_code', 'access_code', 'school_name', 'slogan', 'logo', 'secondary_logo', 'document_header_image', 'document_header_image_settings', 'student_id_card_settings', 'student_id_card_stamp',
        'primary_color', 'secondary_color', 'document_header', 'document_footer',
        'principal_name', 'principal_title', 'education_systems', 'is_active', 'phone', 'email',
        'address', 'website', 'city', 'country',
        'current_academic_year_id', 'default_locale', 'report_card_remarks', 'honor_roll_rules',
    ];

    protected static function booted(): void
    {
        static::creating(function (School $school): void {
            if (filled($school->access_code)) {
                return;
            }

            do {
                $code = 'SCH-'.strtoupper(bin2hex(random_bytes(4)));
            } while (static::withoutGlobalScopes()->where('access_code', $code)->exists());

            $school->access_code = $code;
        });
    }

    protected function casts(): array
    {
        return [
            'report_card_remarks' => 'array',
            'honor_roll_rules' => 'array',
            'education_systems' => 'array',
            'document_header_image_settings' => 'array',
            'student_id_card_settings' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function currentAcademicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'current_academic_year_id');
    }

    public function academicYears(): HasMany
    {
        return $this->hasMany(AcademicYear::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function promotionPolicies(): HasMany
    {
        return $this->hasMany(PromotionPolicy::class);
    }

    public function logoUrl(): Attribute
    {
        return Attribute::get(fn () => $this->logo
            ? URL::temporarySignedRoute('public.media', now()->addHours(12), [
                'directory' => 'schools',
                'filename' => basename($this->logo),
            ], false)
            : null);
    }

    public function secondaryLogoUrl(): Attribute
    {
        return Attribute::get(fn () => $this->secondary_logo
            ? URL::temporarySignedRoute('public.media', now()->addHours(12), [
                'directory' => 'schools',
                'filename' => basename($this->secondary_logo),
            ], false)
            : null);
    }

    public function documentHeaderImageUrl(): Attribute
    {
        return Attribute::get(fn () => $this->document_header_image
            ? URL::temporarySignedRoute('public.media', now()->addHours(12), [
                'directory' => 'schools',
                'filename' => basename($this->document_header_image),
            ], false)
            : null);
    }

    public function studentIdCardStampUrl(): Attribute
    {
        return Attribute::get(fn () => $this->student_id_card_stamp
            ? URL::temporarySignedRoute('public.media', now()->addHours(12), [
                'directory' => 'schools',
                'filename' => basename($this->student_id_card_stamp),
            ], false)
            : null);
    }

    /** @return array{mode:string,width:int,max_height:int,alignment:string} */
    public function documentHeaderImageSettings(): array
    {
        $settings = is_array($this->document_header_image_settings)
            ? $this->document_header_image_settings
            : [];

        return [
            'mode' => in_array($settings['mode'] ?? null, ['fit', 'full_width'], true)
                ? $settings['mode']
                : 'fit',
            'width' => min(100, max(30, (int) ($settings['width'] ?? 100))),
            'max_height' => min(200, max(40, (int) ($settings['max_height'] ?? 105))),
            'alignment' => in_array($settings['alignment'] ?? null, ['left', 'center', 'right'], true)
                ? $settings['alignment']
                : 'center',
        ];
    }

    /** @return array<string, mixed> */
    public function studentIdCardSettings(): array
    {
        $settings = is_array($this->student_id_card_settings) ? $this->student_id_card_settings : [];

        return [
            'background_color' => $settings['background_color'] ?? '#FFFFFF',
            'border_color' => $settings['border_color'] ?? ($this->primary_color ?: '#1D4ED8'),
            'accent_color' => $settings['accent_color'] ?? ($this->primary_color ?: '#1D4ED8'),
            'text_color' => $settings['text_color'] ?? '#111827',
            'border_width' => min(4, max(1, (int) ($settings['border_width'] ?? 3))),
            'corner_style' => in_array($settings['corner_style'] ?? null, ['square', 'soft', 'rounded'], true) ? $settings['corner_style'] : 'soft',
            'spacing' => in_array($settings['spacing'] ?? null, ['compact', 'standard'], true) ? $settings['spacing'] : 'standard',
            'header_height' => min(20, max(13, (int) ($settings['header_height'] ?? 16))),
            'header_image_width' => min(100, max(50, (int) ($settings['header_image_width'] ?? 100))),
            'year_gap' => min(4, max(0, (int) ($settings['year_gap'] ?? 1))),
            'font_scale' => min(115, max(85, (int) ($settings['font_scale'] ?? 100))),
            'photo_size' => in_array($settings['photo_size'] ?? null, ['small', 'standard', 'large'], true) ? $settings['photo_size'] : 'standard',
            'show_title' => (bool) ($settings['show_title'] ?? true),
            'title_fr' => trim((string) ($settings['title_fr'] ?? 'Carte d’identité scolaire')),
            'title_en' => trim((string) ($settings['title_en'] ?? 'Student Identity Card')),
            'show_motto' => (bool) ($settings['show_motto'] ?? true),
            'show_cameroon_flag' => (bool) ($settings['show_cameroon_flag'] ?? true),
            'flag_size' => min(16, max(6, (int) ($settings['flag_size'] ?? 10))),
            'show_stamp' => (bool) ($settings['show_stamp'] ?? true),
            'stamp_label' => trim((string) ($settings['stamp_label'] ?? 'School stamp')),
            'show_signature' => (bool) ($settings['show_signature'] ?? true),
            'signature_label' => trim((string) ($settings['signature_label'] ?? 'Authorized signature')),
        ];
    }
}
