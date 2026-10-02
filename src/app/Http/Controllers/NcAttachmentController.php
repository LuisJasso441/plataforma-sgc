<?php

namespace App\Http\Controllers;

use App\Models\NcAttachment;
use App\Models\NonConformity;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NcAttachmentController extends Controller
{
    public function __invoke(NcAttachment $attachment): StreamedResponse
    {
        $owner = $attachment->attachable;

        // Reporte → la NC directamente; evidencia → la NC de la acción
        $nc = $owner instanceof NonConformity ? $owner : $owner?->nonConformity;

        abort_unless($nc, 404);
        Gate::authorize('view', $nc);

        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404, 'Archivo no encontrado.');

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }
}