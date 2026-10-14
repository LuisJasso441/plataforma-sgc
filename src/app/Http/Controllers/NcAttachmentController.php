<?php

namespace App\Http\Controllers;

use App\Models\NcAttachment;
use App\Models\NonConformity;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NcAttachmentController extends Controller
{
    /** Tipos que se pueden mostrar en el navegador (sin SVG ni HTML por seguridad) */
    private const INLINE = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
        'pdf' => 'application/pdf',
    ];

    /** Descarga del archivo */
    public function __invoke(NcAttachment $attachment): StreamedResponse
    {
        $this->authorizeAccess($attachment);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }

    /** Vista en el navegador (miniaturas, visor de imagen y PDF) */
    public function preview(NcAttachment $attachment): Response
    {
        $this->authorizeAccess($attachment);

        $ext = strtolower(pathinfo($attachment->original_name, PATHINFO_EXTENSION));
        $mime = self::INLINE[$ext] ?? null;

        // Excel, Word, etc. no se pueden ver en el navegador: se descargan
        if (! $mime) {
            return redirect()->route('no-conformidad.attachments.download', $attachment);
        }

        return Storage::disk($attachment->disk)->response($attachment->path, $attachment->original_name, [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=86400',
        ], 'inline');
    }

    /** Reporte → la NC directamente; evidencia → la NC de la acción */
    private function authorizeAccess(NcAttachment $attachment): void
    {
        $owner = $attachment->attachable;
        $nc = $owner instanceof NonConformity ? $owner : $owner?->nonConformity;

        abort_unless($nc, 404);
        Gate::authorize('view', $nc);

        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404, 'Archivo no encontrado.');
    }
}