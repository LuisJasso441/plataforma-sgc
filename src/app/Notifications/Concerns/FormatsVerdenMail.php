<?php

namespace App\Notifications\Concerns;

use Illuminate\Support\HtmlString;

/**
 * Piezas comunes de los correos de VerdenSGA (texto previo al botón y firma, centrados).
 */
trait FormatsVerdenMail
{
    /** Párrafo centrado (p. ej. "Para consultar..., haz clic en el siguiente botón:") */
    protected function centered(string $text): HtmlString
    {
        return new HtmlString('<p style="text-align: center;">' . e($text) . '</p>');
    }

    /** Firma centrada: VerdenSGA / Sistema de Gestión Ambiental */
    protected function signature(): HtmlString
    {
        return new HtmlString(
            '<p style="text-align: center;"><strong>' . e(config('app.name')) . '</strong><br>Sistema de Gestión Ambiental</p>'
        );
    }
}