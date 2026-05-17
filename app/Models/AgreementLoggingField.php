<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class AgreementLoggingField extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'field_type',
        'help_text',
        'options_json',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'options_json' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($field) {
            if (empty($field->slug)) {
                $field->slug = Str::slug($field->name);
            }
        });
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function agreements(): BelongsToMany
    {
        return $this->belongsToMany(Agreement::class, 'agreement_logging_field_assignments')
            ->withPivot('is_required')
            ->withTimestamps();
    }

    public static function fieldTypes(): array
    {
        return [
            'number' => 'Number',
            'decimal' => 'Decimal',
            'text' => 'Text',
            'textarea' => 'Textarea',
            'checkbox' => 'Checkbox',
            'select' => 'Select',
        ];
    }
}
