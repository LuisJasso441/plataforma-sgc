<?php

namespace App\Support;

use DateTimeInterface;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

/**
 * Lee datos del formato oficial "Reporte de No Conformidad y Acción Correctiva".
 * Si el formato cambia de diseño, solo se ajustan las constantes.
 */
class NcReportReader
{
    public const SHEET     = 'FORMATO';
    public const DATE_CELL = 'C12'; // Paso 1 › Datos generales › Fecha

    private const MONTHS = [
        'enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4, 'mayo' => 5, 'junio' => 6,
        'julio' => 7, 'agosto' => 8, 'septiembre' => 9, 'setiembre' => 9,
        'octubre' => 10, 'noviembre' => 11, 'diciembre' => 12,
    ];

    /**
     * Fecha de la reunión (campo "Fecha" del Paso 1). null si no se pudo leer.
     */
    public static function triggerDate(string $path): ?Carbon
    {
        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);

            $book  = $reader->load($path);
            $sheet = $book->getSheetByName(self::SHEET) ?? $book->getSheet(0);
            $cell  = $sheet->getCell(self::DATE_CELL);

            // Si es fórmula (ej. =HOY()), se usa el valor guardado en el archivo
            $raw = $cell->isFormula() ? $cell->getOldCalculatedValue() : $cell->getValue();

            $book->disconnectWorksheets();

            return self::parse($raw);
        } catch (Throwable) {
            return null;
        }
    }

    private static function parse(mixed $raw): ?Carbon
    {
        if ($raw instanceof DateTimeInterface) {
            return Carbon::instance($raw)->startOfDay();
        }

        // Fecha nativa de Excel (número de serie)
        if (is_numeric($raw)) {
            return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $raw))->startOfDay();
        }

        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        $text = mb_strtolower(trim($raw));

        // Texto largo: "miércoles, 30 de septiembre de 2026"
        if (preg_match('/(\d{1,2})\s+de\s+([a-záéíóú]+)\s+(?:de|del)\s+(\d{4})/u', $text, $m)
            && isset(self::MONTHS[$m[2]])) {
            return Carbon::create((int) $m[3], self::MONTHS[$m[2]], (int) $m[1])->startOfDay();
        }

        // Texto corto: dd/mm/aaaa, dd-mm-aaaa, aaaa-mm-dd
        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d'] as $format) {
            try {
                return Carbon::createFromFormat('!' . $format, $text);
            } catch (Throwable) {
                // siguiente formato
            }
        }

        return null;
    }
}