<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\UploadedFile;
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

    /**
     * Guarda un archivo subido en disco privado y lo registra ligado a $owner
     * (una NonConformity para reportes, una NcAction para evidencias).
     */
    public static function storeUpload(
        UploadedFile $file,
        Model $owner,
        string $type,
        string $directory,
        string $disk = 'local',
    ): self {
        $attachment = new self([
            'type'          => $type,
            'original_name' => $file->getClientOriginalName(),
            'path'          => $file->store($directory, $disk),
            'disk'          => $disk,
            'mime_type'     => $file->getMimeType(),
            'size'          => $file->getSize(),
            'uploaded_by'   => auth()->id(),
        ]);

        $attachment->attachable()->associate($owner);
        $attachment->save();

        return $attachment;
    }
}