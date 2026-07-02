<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class LoggingField extends Model
{
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
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to order by sort_order then name
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order', 'asc')->orderBy('name', 'asc');
    }

    /**
     * Agreements using this logging field
     */
    public function agreements(): BelongsToMany
    {
        return $this->belongsToMany(Agreement::class, 'agreement_logging_field_assignments', 'logging_field_id', 'agreement_id')
            ->withPivot('is_required')
            ->withTimestamps();
    }

    /**
     * Contact families using this logging field
     */
    public function contactFamilies(): BelongsToMany
    {
        return $this->belongsToMany(ContactFamily::class, 'contact_family_logging_field_assignments', 'logging_field_id', 'contact_family_id')
            ->withPivot('is_required')
            ->withTimestamps();
    }

    public static function availabilityOptions(): array
    {
        return [
            'available_in_agreements' => 'Agreements',
            'available_in_contact_families' => 'Contact Families',
            'available_in_activities' => 'Activities',
        ];
    }

    /**
     * Field type options
     */
    public static function fieldTypes(): array
    {
        return [
            'number' => 'Number',
            'decimal' => 'Decimal',
            'text' => 'Text',
            'textarea' => 'Textarea',
            'checkbox' => 'Checkbox',
            'select' => 'Select',
            'document' => 'Document Upload',
        ];
    }
}
