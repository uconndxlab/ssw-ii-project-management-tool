<?php

namespace App\Models;

use App\Services\PrivateFileService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgreementAttachment extends Model
{
    protected $fillable = [
        'agreement_id',
        'filename',
        'file_path',
        'mime_type',
        'file_size',
    ];

    public function agreement(): BelongsTo
    {
        return $this->belongsTo(Agreement::class);
    }

    /**
     * Get the URL to download this attachment.
     */
    public function getDownloadUrlAttribute(): string
    {
        return route('agreements.attachments.download', [
            'agreement' => $this->agreement_id,
            'attachment' => $this->id,
        ]);
    }

    /**
     * Get human-readable file size.
     */
    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->file_size ?? 0;
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2).' '.$units[$i];
    }

    /**
     * Delete the physical file when the model is deleted.
     */
    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($attachment) {
            app(PrivateFileService::class)->deleteIfExists($attachment->file_path);
        });
    }
}
