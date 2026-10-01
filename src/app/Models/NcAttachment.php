<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Number;

class NcAttachment extends Model
{
    public const TYPE_SOLICITUD = 'solicitud';
    public const TYPE_REPORTE   = 'reporte';
    public const TYPE_EVIDENCIA = 'evidencia';

    protected $fillable = [
        'type', 'original_name', 'path', 'disk',
        'mime_type', 'size', 'uploaded_by',
    ];

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function humanSize(): string
    {
        return Number::fileSize($this->size);
    }
}