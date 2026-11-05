<?php

namespace App\Models;

use App\Enums\ReportFormat;
use App\Enums\ReportType;
use Database\Factories\ReportRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportRequest extends Model
{
    /** @use HasFactory<ReportRequestFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'agreement_id', 'user_id', 'user_name', 'user_email', 'type', 'format',
    ];

    protected function casts(): array
    {
        return [
            'type' => ReportType::class,
            'format' => ReportFormat::class,
        ];
    }

    /** @return BelongsTo<Agreement, $this> */
    public function agreement(): BelongsTo
    {
        return $this->belongsTo(Agreement::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
