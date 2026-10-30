<?php

namespace App\Models;

use App\Enums\CertificateAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property CertificateAction $action
 */
class CertificateActionLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['certificate_enrollment_id', 'certificate_award_id', 'user_id', 'action', 'cycle', 'reason', 'context'];

    protected function casts(): array
    {
        return ['action' => CertificateAction::class, 'cycle' => 'integer', 'context' => 'array', 'created_at' => 'datetime'];
    }

    /** @return BelongsTo<CertificateEnrollment, $this> */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(CertificateEnrollment::class, 'certificate_enrollment_id');
    }

    /** @return BelongsTo<CertificateAward, $this> */
    public function award(): BelongsTo
    {
        return $this->belongsTo(CertificateAward::class, 'certificate_award_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
