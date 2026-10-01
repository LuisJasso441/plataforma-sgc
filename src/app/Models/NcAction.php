<?php

namespace App\Models;

use App\Enums\NcActionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class NcAction extends Model
{
    protected $fillable = [
        'non_conformity_id', 'number', 'activity', 'responsible',
        'commitment_date', 'actual_end_date', 'status',
        'review_comment', 'reviewed_by', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'commitment_date' => 'date',
            'actual_end_date' => 'date',
            'status'          => NcActionStatus::class,
            'reviewed_at'     => 'datetime',
        ];
    }

    public function nonConformity(): BelongsTo
    {
        return $this->belongsTo(NonConformity::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function evidences(): MorphMany
    {
        return $this->morphMany(NcAttachment::class, 'attachable')
            ->where('type', NcAttachment::TYPE_EVIDENCIA)
            ->latest();
    }

    public function isOverdue(): bool
    {
        return $this->status !== NcActionStatus::Validada
            && $this->commitment_date->lt(today());
    }
}