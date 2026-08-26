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
        'slug', 'school_code', 'access_code', 'school_name', 'slogan', 'logo', 'secondary_logo', 'document_header_image', 'document_header_image_settings',
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
}
