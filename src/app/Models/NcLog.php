<?php

namespace App\Models;

use App\Enums\NcStage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NcLog extends Model
{
    protected $fillable = [
        'user_id', 'event', 'from_stage', 'to_stage', 'comment', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'from_stage' => NcStage::class,
            'to_stage'   => NcStage::class,
            'meta'       => 'array',
        ];
    }

    public function nonConformity(): BelongsTo
    {
        return $this->belongsTo(NonConformity::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}