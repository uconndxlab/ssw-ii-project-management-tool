<?php

namespace App\Models;

use App\Enums\RecentRecordType;
use Carbon\Carbon;
use Database\Factories\RecentRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property RecentRecordType $record_type
 * @property int $record_id
 * @property Carbon $last_opened_at
 */
class RecentRecord extends Model
{
    /** @use HasFactory<RecentRecordFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['user_id', 'record_type', 'record_id', 'last_opened_at'];

    protected function casts(): array
    {
        return [
            'record_type' => RecentRecordType::class,
            'last_opened_at' => 'datetime',
        ];
    }
}
