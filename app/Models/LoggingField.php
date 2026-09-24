<?php

namespace App\Models;

use App\Enums\ProgramScopeMode;
use App\Models\Concerns\HasProgramScope;
use App\Models\Concerns\VisibleToUser;
use App\Models\Contracts\HasPrograms;
use App\Models\Pivots\AgreementLoggingFieldPivot;
use Database\Factories\LoggingFieldFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/**
 * Index/view: admins only, and a listed program is in your privilege.
 * Edit: you admin a listed program. Delete: every listed program is in your admin scope. No programs: system admin only.
 *
 * @property ProgramScopeMode $program_scope_mode
 * @property array<int, string|array<string, mixed>>|null $options_json
 * @property AgreementLoggingFieldPivot|null $pivot
 */
class LoggingField extends Model implements HasPrograms
{
    /** @use HasFactory<LoggingFieldFactory> */
    use HasFactory, HasProgramScope, VisibleToUser;

    public const FIELD_TYPE_CHECKBOX = 'checkbox';

    public const FIELD_TYPE_MULTISELECT = 'multiselect';

    public const FIELD_TYPE_CHECKBOX_GROUP = 'checkbox_group';

    public const FIELD_TYPE_SELECT = 'select';

    protected $fillable = [
        'name',
        'slug',
        'field_type',
        'help_text',
        'options_json',
        'is_active',
        'sort_order',
        'is_full_width',
        'available_in_agreements',
        'available_in_contact_families',
        'available_in_activities',
        'program_scope_mode',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'options_json' => 'array',
            'is_full_width' => 'boolean',
            'available_in_agreements' => 'boolean',
            'available_in_contact_families' => 'boolean',
            'available_in_activities' => 'boolean',
            'program_scope_mode' => ProgramScopeMode::class,
        ];
    }

    /**
     * Boot method to auto-generate slug
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($loggingField) {
            if (empty($loggingField->slug)) {
                $loggingField->slug = Str::slug($loggingField->name);
            }
        });
    }

    /**
     * Scope to only active logging fields
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to order by sort_order then name
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order', 'asc')->orderBy('name', 'asc');
    }

    /**
     * Agreements using this logging field
     *
     * @return BelongsToMany<Agreement, $this>
     */
    public function agreements(): BelongsToMany
    {
        return $this->belongsToMany(Agreement::class, 'agreement_logging_field_assignments', 'logging_field_id', 'agreement_id')
            ->withPivot('is_required')
            ->withTimestamps();
    }

    /**
     * Contact families using this logging field
     *
     * @return BelongsToMany<ContactFamily, $this>
     */
    public function contactFamilies(): BelongsToMany
    {
        return $this->belongsToMany(ContactFamily::class, 'contact_family_logging_field_assignments', 'logging_field_id', 'contact_family_id')
            ->withPivot('is_required')
            ->withTimestamps();
    }

    /** @return BelongsToMany<Program, $this> */
    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(Program::class, 'logging_field_program')->withTimestamps();
    }

    /**
     * @return array<string, string>
     */
    public static function availabilityOptions(): array
    {
        return [
            'available_in_agreements' => 'Agreements',
            'available_in_contact_families' => 'Activity Families',
            'available_in_activities' => 'Activity Types',
        ];
    }

    /**
     * Field type options
     *
     * @return array<string, string>
     */
    public static function fieldTypes(): array
    {
        return [
            'number' => 'Number',
            'decimal' => 'Decimal',
            'text' => 'Text',
            'textarea' => 'Textarea',
            self::FIELD_TYPE_CHECKBOX => 'Checkbox',
            self::FIELD_TYPE_MULTISELECT => 'Multiselect',
            self::FIELD_TYPE_SELECT => 'Select',
            'document' => 'Document Upload',
        ];
    }

    public function fieldTypeLabel(): string
    {
        if ($this->isMultiselect()) {
            return self::fieldTypes()[self::FIELD_TYPE_MULTISELECT];
        }

        return self::fieldTypes()[$this->field_type] ?? Str::of($this->field_type)->replace('_', ' ')->title()->toString();
    }

    public function usesOptions(): bool
    {
        return in_array($this->field_type, [self::FIELD_TYPE_SELECT, self::FIELD_TYPE_MULTISELECT, self::FIELD_TYPE_CHECKBOX_GROUP], true);
    }

    public function isMultiselect(): bool
    {
        return in_array($this->field_type, [self::FIELD_TYPE_MULTISELECT, self::FIELD_TYPE_CHECKBOX_GROUP], true);
    }

    /**
     * @return array<int, array{id: string, label: string}>
     */
    public function normalizedOptions(): array
    {
        /** @var array<int, mixed>|null $options */
        $options = $this->options_json;

        return collect($options ?? [])
            ->map(function ($option, $index) {
                if (is_string($option)) {
                    $label = trim($option);

                    if ($label === '') {
                        return null;
                    }

                    return [
                        'id' => $label,
                        'label' => $label,
                    ];
                }

                if (! is_array($option)) {
                    return null;
                }

                $label = trim((string) ($option['label'] ?? $option['value'] ?? ''));

                if ($label === '') {
                    return null;
                }

                return [
                    'id' => (string) ($option['id'] ?? Str::uuid()),
                    'label' => $label,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function optionValues(): array
    {
        return collect($this->normalizedOptions())
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function optionLabelMap(): array
    {
        return collect($this->normalizedOptions())
            ->mapWithKeys(fn ($option) => [(string) $option['id'] => $option['label']])
            ->all();
    }
}
