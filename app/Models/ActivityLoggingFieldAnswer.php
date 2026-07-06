<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLoggingFieldAnswer extends Model
{
    protected $fillable = [
        'activity_id',
        'logging_field_id',
        'context_type',
        'context_id',
        'value_text',
        'value_number',
        'value_boolean',
        'file_path',
    ];

    protected function casts(): array
    {
        return [
            'activity_id' => 'integer',
            'logging_field_id' => 'integer',
            'context_id' => 'integer',
            'value_number' => 'float',
            'value_boolean' => 'boolean',
        ];
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function loggingField(): BelongsTo
    {
        return $this->belongsTo(LoggingField::class);
    }

    public function getValueAttribute(): mixed
    {
        if ($this->file_path !== null) {
            return $this->file_path;
        }

        if ($this->value_boolean !== null) {
            return $this->value_boolean;
        }

        if ($this->value_number !== null) {
            return $this->value_number;
        }

        return $this->value_text;
    }
}
