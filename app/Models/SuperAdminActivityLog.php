<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SuperAdminActivityLog extends Model
{
    use HasFactory;

    /** @var array<int, string> */
    protected $fillable = [
        'platform_admin_id',
        'platform_admin_name',
        'action',
        'subject_type',
        'subject_id',
        'description',
    ];

    /** @return BelongsTo<PlatformAdmin, $this> */
    public function platformAdmin(): BelongsTo
    {
        return $this->belongsTo(PlatformAdmin::class);
    }
}
