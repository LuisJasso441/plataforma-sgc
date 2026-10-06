<?php

namespace App\Support;

use DateTimeInterface;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Lee el formato oficial "Reporte de No Conformidad y Acción Correctiva".
 *
 * Las secciones se ubican por sus ETIQUETAS (columna B), no por celdas fijas:
 * si en la reunión insertan filas (más acciones, más contención), la lectura
 * se ajusta sola. Las casillas se leen de los controles de formulario.
 */
class NcReportReader
{
    public const SHEET = 'FORMATO';

    /** Casillas del Paso 7 (shapeId del control de formulario) */
    private const SHAPE_SIMILARES  = ['si' => 2134, 'no' => 2137];
    private const SHAPE_CAMBIOS    = ['si' => 2129, 'no' => 2130, 'creacion' => 2148];
    private const SHAPE_DOCUMENTOS = [
        'Procedimiento'          => 2120,
        'Formato'                => 2123,
        'Anexos'                 => 2125,
        'Instructivo de Trabajo' => 2127,
        'Plan Control / POT'     => 2121,
        'Alerta'                 => 2124,
        'Ayuda Visual'           => 2126,
    ];

    private const MONTHS = [
        'enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4, 'mayo' => 5, 'junio' => 6,
        'julio' => 7, 'agosto' => 8, 'septiembre' => 9, 'setiembre' => 9,
        'octubre' => 10, 'noviembre' => 11, 'diciembre' => 12,
    ];

    private Worksheet $sheet;
    private int $lastRow;

    /** @var array<int,bool> shapeId => marcada */
    private array $checked;

    /**
     * Lee el reporte completo. Lanza RuntimeException si no es el formato oficial.
     *
     * @return array{
     *   fecha: ?Carbon,
     *   descripcion: string,
     *   equipo: list<array{nombre:string, area:string}>,
     *   contencion: list<array{actividad:string, responsable:string, fecha_inicio:?string, fecha_final:?string}>,
     *   causa_raiz: string,
     *   procesos_similares: array{aplica:?string, cuales:string},
     *   cambios_documentos: array{opcion:?string, documentos:list<string>, otro:string},
     *   acciones: list<array{numero:int, actividad:string, responsable:string, fecha_compromiso:?string}>,
     *   efectividad: array{evidencia:string, plazo:string},
     * }
     */
    public static function read(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);

        $book  = $reader->load($path);
        $sheet = $book->getSheetByName(self::SHEET) ?? $book->getSheet(0);

        try {
            return (new self($sheet, self::readCheckboxes($path)))->parse();
        } finally {
            $book->disconnectWorksheets();
        }
    }

    /** Solo la fecha del Paso 1 (null si no se pudo leer) */
    public static function triggerDate(string $path): ?Carbon
    {
        try {
            return self::read($path)['fecha'];
        } catch (Throwable) {
            return null;
        }
    }

    private function __construct(Worksheet $sheet, array $checked)
    {
        $this->sheet   = $sheet;
        $this->lastRow = $sheet->getHighestRow();
        $this->checked = $checked;
    }

    /* ───────────── Secciones ───────────── */

    private function parse(): array
    {
        $paso1 = $this->findRow('Paso 1');
        $paso2 = $this->findRow('Paso 2', $paso1);
        $paso3 = $this->findRow('Paso 3', $paso2);
        $paso4 = $this->findRow('Paso 4', $paso3);
        $paso5 = $this->findRow('Paso 5', $paso4);
        $paso7 = $this->findRow('Paso 7', $paso5);

        // Paso 1: Fecha (celda a la derecha de "Fecha:")
        $fechaRow = $this->findRow('Fecha', $paso1, $paso2);
        $fecha    = self::parseDate($this->raw("C{$fechaRow}"));

        // Paso 2: la descripción está 2 filas abajo del título
        $descripcion = $this->text('B' . ($paso2 + 2));

        // Paso 3: filas "Nombre:" (dos columnas de integrantes)
        $equipo = [];
        for ($r = $paso3 + 1; $r < $paso4; $r++) {
            if (! str_starts_with($this->norm($this->text("B{$r}")), 'nombre')) {
                continue;
            }
            foreach ([['C', 'F'], ['J', 'M']] as [$colNombre, $colArea]) {
                $nombre = $this->text("{$colNombre}{$r}");
                $area   = $this->text("{$colArea}{$r}");
                if ($nombre !== '' || $area !== '') {
                    $equipo[] = ['nombre' => $nombre, 'area' => $area];
                }
            }
        }

        // Paso 4: contención (debajo del encabezado hasta el Paso 5)
        $contencion = [];
        $header4 = $this->findRow('Actividad / Evidencia', $paso4, $paso5);
        for ($r = $header4 + 1; $r < $paso5; $r++) {
            $item = [
                'actividad'    => $this->text("B{$r}"),
                'responsable'  => $this->text("H{$r}"),
                'fecha_inicio' => self::parseDate($this->raw("K{$r}"))?->format('Y-m-d'),
                'fecha_final'  => self::parseDate($this->raw("M{$r}"))?->format('Y-m-d'),
            ];
            if ($item['actividad'] !== '' || $item['responsable'] !== '' || $item['fecha_inicio']) {
                $contencion[] = $item;
            }
        }

        // Paso 6: causa raíz (fila debajo de "Causa(s) raíz")
        $causaRow  = $this->findRow('Causa(s) raíz', $paso5, $paso7);
        $causaRaiz = $this->text('B' . ($causaRow + 1));

        // Paso 7: procesos similares y cambios en documentos
        $similaresRow = $this->findRow('¿Aplica a procesos similares', $paso7);
        $creacionRow  = $this->findRow('CREACIÓN', $paso7);

        $procesosSimilares = [
            'aplica' => $this->option(self::SHAPE_SIMILARES),
            'cuales' => $this->text("I{$similaresRow}"),
        ];

        $cambiosDocumentos = [
            'opcion'     => $this->option(self::SHAPE_CAMBIOS),
            'documentos' => array_keys(array_filter(
                self::SHAPE_DOCUMENTOS,
                fn (int $shape) => $this->checked[$shape] ?? false,
            )),
            'otro'       => $this->text("I{$creacionRow}"),
        ];

        // Paso 7: acciones definitivas (debajo de "Número" hasta "EFECTIVIDAD")
        $header7        = $this->findRow('Número', $paso7);
        $efectividadRow = $this->findRow('EFECTIVIDAD DE ACCIONES', $header7);

        $acciones = [];
        for ($r = $header7 + 1; $r < $efectividadRow; $r++) {
            $actividad   = $this->text("C{$r}");
            $responsable = $this->text("H{$r}");
            $compromiso  = self::parseDate($this->raw("K{$r}"));

            if ($actividad === '' && $responsable === '' && ! $compromiso) {
                continue;
            }

            $acciones[] = [
                'numero'           => count($acciones) + 1,
                'actividad'        => $actividad,
                'responsable'      => $responsable,
                'fecha_compromiso' => $compromiso?->format('Y-m-d'),
            ];
        }

        // Efectividad: fila debajo de "EFECTIVIDAD DE ACCIONES"
        $plazoRef = 'L' . ($efectividadRow + 1);
        $plazo    = self::parseDate($this->raw($plazoRef))?->format('d/m/Y') ?? $this->text($plazoRef);

        return [
            'fecha'              => $fecha,
            'descripcion'        => $descripcion,
            'equipo'             => $equipo,
            'contencion'         => $contencion,
            'causa_raiz'         => $causaRaiz,
            'procesos_similares' => $procesosSimilares,
            'cambios_documentos' => $cambiosDocumentos,
            'acciones'           => $acciones,
            'efectividad'        => [
                'evidencia' => $this->text('B' . ($efectividadRow + 1)),
                'plazo'     => $plazo,
            ],
        ];
    }

    /* ───────────── Utilidades de celdas ───────────── */

    /** Primera fila (columna B) cuyo texto empieza con $label, entre $from y $to */
    private function findRow(string $label, int $from = 1, ?int $to = null): int
    {
        $needle = $this->norm($label);
        $to ??= $this->lastRow;

        for ($r = max(1, $from); $r <= $to; $r++) {
            if (str_starts_with($this->norm($this->text("B{$r}")), $needle)) {
                return $r;
            }
        }

        throw new RuntimeException("El archivo no corresponde al formato oficial: no se encontró \"{$label}\".");
    }

    private function raw(string $ref): mixed
    {
        $cell = $this->sheet->getCell($ref);

        return $cell->isFormula() ? $cell->getOldCalculatedValue() : $cell->getValue();
    }

    private function text(string $ref): string
    {
        $value = $this->raw($ref);

        if ($value instanceof RichText) {
            $value = $value->getPlainText();
        }

        return trim((string) ($value ?? ''));
    }

    private function norm(string $text): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $text)));
    }

    /** Opción marcada de un grupo de casillas (la primera marcada) */
    private function option(array $shapes): ?string
    {
        foreach ($shapes as $key => $shape) {
            if ($this->checked[$shape] ?? false) {
                return $key;
            }
        }

        return null;
    }

    public static function parseDate(mixed $raw): ?Carbon
    {
        if ($raw instanceof DateTimeInterface) {
            return Carbon::instance($raw)->startOfDay();
        }

        // Fecha nativa de Excel (número de serie)
        if (is_numeric($raw) && (float) $raw > 0) {
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

    /* ───────────── Casillas (controles de formulario) ───────────── */

    /**
     * Estado de las casillas de la hoja FORMATO: [shapeId => bool].
     * Lee los ctrlProp (Excel 2010+) y, de respaldo, el VML.
     */
    private static function readCheckboxes(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return [];
        }

        try {
            $sheetPath = self::sheetPath($zip);
            if (! $sheetPath) {
                return [];
            }

            $sheetXml = $zip->getFromName($sheetPath) ?: '';
            $rels     = self::relationships($zip->getFromName(dirname($sheetPath) . '/_rels/' . basename($sheetPath) . '.rels') ?: '');
            $baseDir  = dirname($sheetPath);

            $checked = [];

            // 1) ctrlProp
            preg_match_all('/<control\b[^>]*shapeId="(\d+)"[^>]*r:id="([^"]+)"/', $sheetXml, $controls, PREG_SET_ORDER);
            foreach ($controls as [, $shapeId, $rid]) {
                if (isset($rels[$rid])) {
                    $xml = $zip->getFromName(self::resolve($baseDir, $rels[$rid])) ?: '';
                    $checked[(int) $shapeId] = (bool) preg_match('/\bchecked="Checked"/i', $xml);
                }
            }

            // 2) VML (respaldo)
            if (preg_match('/<legacyDrawing\b[^>]*r:id="([^"]+)"/', $sheetXml, $m) && isset($rels[$m[1]])) {
                $vml = $zip->getFromName(self::resolve($baseDir, $rels[$m[1]])) ?: '';
                preg_match_all('/<v:shape\b[^>]*id="_x0000_s(\d+)".*?<\/v:shape>/s', $vml, $shapes, PREG_SET_ORDER);
                foreach ($shapes as [$block, $shapeId]) {
                    if (preg_match('/<x:Checked>\s*1\s*<\/x:Checked>/', $block)) {
                        $checked[(int) $shapeId] = true;
                    }
                }
            }

            return $checked;
        } finally {
            $zip->close();
        }
    }

    /** Ruta del XML de la hoja FORMATO (o de la primera hoja) */
    private static function sheetPath(ZipArchive $zip): ?string
    {
        $workbook = $zip->getFromName('xl/workbook.xml') ?: '';
        $rels     = self::relationships($zip->getFromName('xl/_rels/workbook.xml.rels') ?: '');

        preg_match_all('/<sheet\b[^>]*name="([^"]+)"[^>]*r:id="([^"]+)"/', $workbook, $sheets, PREG_SET_ORDER);

        foreach ($sheets as [, $name, $rid]) {
            if (html_entity_decode($name) === self::SHEET && isset($rels[$rid])) {
                return self::resolve('xl', $rels[$rid]);
            }
        }

        return isset($sheets[0][2], $rels[$sheets[0][2]]) ? self::resolve('xl', $rels[$sheets[0][2]]) : null;
    }

    /** @return array<string,string> Id => Target */
    private static function relationships(string $xml): array
    {
        preg_match_all('/<Relationship\b[^>]*>/', $xml, $tags);

        $map = [];
        foreach ($tags[0] as $tag) {
            if (preg_match('/\bId="([^"]+)"/', $tag, $id) && preg_match('/\bTarget="([^"]+)"/', $tag, $target)) {
                $map[$id[1]] = $target[1];
            }
        }

        return $map;
    }

    /** Resuelve "../ctrlProps/x.xml" relativo a un directorio del zip */
    private static function resolve(string $baseDir, string $target): string
    {
        if (str_starts_with($target, '/')) {
            return ltrim($target, '/');
        }

        $out = [];
        foreach (explode('/', $baseDir . '/' . $target) as $part) {
            if ($part === '..') {
                array_pop($out);
            } elseif ($part !== '.' && $part !== '') {
                $out[] = $part;
            }
        }

        return implode('/', $out);
    }
}