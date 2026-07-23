<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityAgreementFundingSource extends Model
{
    public const ROLE_PAYOR = 'payor';
    public const ROLE_PAYEE = 'payee';
    public const SOURCE_USER = 'user';
    public const SOURCE_ORGANIZATION = 'organization';

    protected $fillable = [
        'activity_id',
        'agreement_id',
        'role',
        'source_type',
        'source_id',
    ];

    protected function casts(): array
    {
        return [
            'activity_id' => 'integer',
            'agreement_id' => 'integer',
            'source_id' => 'integer',
        ];
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function agreement(): BelongsTo
    {
        return $this->belongsTo(Agreement::class);
    }

    public function token(): string
    {
        return self::tokenFor($this->source_type, (int) $this->source_id);
    }

    public static function tokenFor(string $sourceType, int $sourceId): string
    {
        return $sourceType.':'.$sourceId;
    }

    public function resolveSourceModel(): User|Organization|null
    {
        return match ($this->source_type) {
            self::SOURCE_USER => User::query()->find($this->source_id),
            self::SOURCE_ORGANIZATION => Organization::query()->find($this->source_id),
            default => null,
        };
    }
}
