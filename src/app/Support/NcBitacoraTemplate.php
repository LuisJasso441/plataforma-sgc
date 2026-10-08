<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use ZipArchive;

/**
 * Genera la "Bitácora de No Conformidades y Acciones Correctivas" (FR-SG-05)
 * editando el XML del formato original: conserva estilos, formato condicional
 * del estatus, listas desplegables, comentarios y configuración de impresión.
 *
 * Una hoja por departamento (cada departamento lleva su propio consecutivo de folios).
 */
class NcBitacoraTemplate
{
    private const NS_MAIN = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    private const NS_REL  = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    private const NS_PKG  = 'http://schemas.openxmlformats.org/package/2006/relationships';

    private const TEMPLATE_SHEET = 'Bitácora de AC';
    private const HIDDEN_SHEET   = 'Bitácora de AC INT';

    /** Filas de datos del formato: 8 a 20 (la 20 lleva el borde inferior grueso) */
    private const FIRST_ROW = 8;
    private const LAST_ROW  = 20;

    /** Columnas de datos B..O */
    public const COLUMNS = ['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O'];

    /** Ancho aproximado (caracteres por línea) para calcular la altura de fila */
    private const WRAP = ['B' => 12, 'D' => 18, 'E' => 15, 'F' => 15, 'G' => 80, 'H' => 12, 'L' => 22, 'M' => 22, 'N' => 42, 'O' => 30];

    /** Recuadro del logo (B2:D4) en píxeles */
    private const LOGO_BOX_W = 350;
    private const LOGO_BOX_H = 78;
    private const LOGO_PAD   = 4;
    private const EMU_PER_PX = 9525;

    /**
     * @param  array<string, list<array<string, string|\DateTimeInterface|null>>>  $groups
     *         nombre de departamento => filas; cada fila indexada por columna B..O
     * @param  array{codigo?:string, revision?:string, fecha_revision?:string}  $cintilla
     * @return string  ruta del archivo generado (temporal)
     */
    public static function fill(string $templatePath, array $groups, array $cintilla = [], ?string $logoPath = null): string
    {
        if (! is_file($templatePath)) {
            throw new RuntimeException("No se encontró el formato: {$templatePath}");
        }

        if ($groups === []) {
            $groups = [self::TEMPLATE_SHEET => []];
        }

        $output = tempnam(sys_get_temp_dir(), 'nc_bitacora_') . '.xlsx';
        copy($templatePath, $output);

        $zip = new ZipArchive();
        if ($zip->open($output) !== true) {
            throw new RuntimeException('No se pudo abrir el formato.');
        }

        try {
            (new self($zip))->build($groups, $cintilla, $logoPath && is_file($logoPath) ? $logoPath : null);
        } finally {
            $zip->close();
        }

        return $output;
    }

    private ZipArchive $zip;

    private function __construct(ZipArchive $zip)
    {
        $this->zip = $zip;
    }

    /* ═══════════════ Libro ═══════════════ */

    private function build(array $groups, array $cintilla, ?string $logoPath): void
    {
        $workbook = $this->dom('xl/workbook.xml');
        $wbXp     = $this->xpath($workbook);
        $wbRels   = $this->dom('xl/_rels/workbook.xml.rels');
        $relsXp   = new DOMXPath($wbRels);
        $relsXp->registerNamespace('p', self::NS_PKG);
        $types    = $this->zip->getFromName('[Content_Types].xml');

        $sheetsEl = $wbXp->query('//m:sheets')->item(0);
        $template = $this->sheetEntry($wbXp, self::TEMPLATE_SHEET);
        $hidden   = $this->sheetEntry($wbXp, self::HIDDEN_SHEET);

        if (! $template) {
            throw new RuntimeException('El formato no contiene la hoja "' . self::TEMPLATE_SHEET . '".');
        }

        $templateRid  = $template->getAttributeNS(self::NS_REL, 'id');
        $templatePath = $this->relTarget($relsXp, $templateRid);
        $templateXml  = $this->zip->getFromName($templatePath);

        // 1) Quitar la hoja oculta de ejemplo (INT) y sus partes
        if ($hidden) {
            $this->removeSheet($hidden, $relsXp, $types);
        }

        // 2) Una hoja por departamento (la primera reutiliza la hoja del formato)
        $titles = [];
        $index  = 0;
        $nextId = 100;

        foreach ($groups as $name => $rows) {
            $title    = $this->uniqueTitle((string) $name, $titles);
            $titles[] = $title;
            $isFirst  = $index === 0;

            $sheet = $this->buildSheet($templateXml, array_values($rows), $cintilla, keepParts: $isFirst, hasLogo: (bool) $logoPath, selected: $isFirst);

            if ($isFirst) {
                $path = $templatePath;
                $template->setAttribute('name', $title);
            } else {
                $n     = $nextId++;
                $path  = "xl/worksheets/sheet{$n}.xml";
                $rid   = "rIdSga{$n}";
                $entry = $workbook->createElementNS(self::NS_MAIN, 'sheet');
                $entry->setAttribute('name', $title);
                $entry->setAttribute('sheetId', (string) $n);
                $entry->setAttributeNS(self::NS_REL, 'r:id', $rid);

                // Insertar después de la última hoja de departamento
                $anchor = $template;
                for ($k = 1; $k < $index; $k++) {
                    $anchor = $anchor->nextSibling;
                }
                $sheetsEl->insertBefore($entry, $anchor->nextSibling);

                $rel = $wbRels->createElementNS(self::NS_PKG, 'Relationship');
                $rel->setAttribute('Id', $rid);
                $rel->setAttribute('Type', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet');
                $rel->setAttribute('Target', "worksheets/sheet{$n}.xml");
                $wbRels->documentElement->appendChild($rel);

                $types = str_replace('</Types>', '<Override PartName="/' . $path . '" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>', $types);
            }

            // Logo: la primera hoja lo agrega a su dibujo; las demás reciben un dibujo propio
            if ($logoPath) {
                $types = $this->attachLogo($path, $sheet, $logoPath, $isFirst, $index, $types);
            }

            $this->zip->addFromString($path, $sheet['xml']);
            $index++;
        }

        // 3) Nombres definidos (filtro, área de impresión y títulos) por hoja
        $this->rebuildDefinedNames($workbook, $wbXp, $titles, $groups);

        $this->zip->addFromString('xl/workbook.xml', $workbook->saveXML());
        $this->zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels->saveXML());
        $this->zip->addFromString('[Content_Types].xml', $types);

        // docProps/app.xml enumera las hojas originales: se quita la lista para evitar discrepancias
        $app = $this->zip->getFromName('docProps/app.xml');
        if ($app !== false) {
            $app = preg_replace('/<HeadingPairs>.*?<\/HeadingPairs>|<TitlesOfParts>.*?<\/TitlesOfParts>/s', '', $app);
            $this->zip->addFromString('docProps/app.xml', $app);
        }
    }

    private function sheetEntry(DOMXPath $xp, string $name): ?DOMElement
    {
        foreach ($xp->query('//m:sheets/m:sheet') as $sheet) {
            if ($sheet->getAttribute('name') === $name) {
                return $sheet;
            }
        }

        return null;
    }

    private function relTarget(DOMXPath $relsXp, string $rid): string
    {
        $rel = $relsXp->query("//p:Relationship[@Id='{$rid}']")->item(0);

        return 'xl/' . ltrim($rel->getAttribute('Target'), '/');
    }

    /** Quita una hoja del libro con su relación, sus partes y sus tipos de contenido */
    private function removeSheet(DOMElement $entry, DOMXPath $relsXp, string &$types): void
    {
        $rid  = $entry->getAttributeNS(self::NS_REL, 'id');
        $rel  = $relsXp->query("//p:Relationship[@Id='{$rid}']")->item(0);
        $path = 'xl/' . ltrim($rel->getAttribute('Target'), '/');

        $relsPath = dirname($path) . '/_rels/' . basename($path) . '.rels';
        $parts    = [$path, $relsPath];

        if (($sheetRels = $this->zip->getFromName($relsPath)) !== false) {
            preg_match_all('/Target="([^"]+)"/', $sheetRels, $m);
            foreach ($m[1] as $target) {
                $part    = $this->resolve(dirname($path), $target);
                $parts[] = $part;
                $parts[] = dirname($part) . '/_rels/' . basename($part) . '.rels';
            }
        }

        foreach ($parts as $part) {
            $this->zip->deleteName($part);
            $types = preg_replace('#<Override PartName="/' . preg_quote($part, '#') . '"[^>]*/>#', '', $types);
        }

        $rel->parentNode->removeChild($rel);
        $entry->parentNode->removeChild($entry);
    }

    private function uniqueTitle(string $name, array $used): string
    {
        $title = trim(preg_replace('/[\[\]:*?\/\\\\]/u', ' ', $name)) ?: 'Bitácora';
        $title = mb_substr($title, 0, 31);
        $base  = $title;

        for ($i = 2; in_array(mb_strtolower($title), array_map('mb_strtolower', $used), true); $i++) {
            $title = mb_substr($base, 0, 31 - strlen(" ({$i})")) . " ({$i})";
        }

        return $title;
    }

    private function rebuildDefinedNames(DOMDocument $workbook, DOMXPath $xp, array $titles, array $groups): void
    {
        $defined = $xp->query('//m:definedNames')->item(0);
        if (! $defined) {
            $defined = $workbook->createElementNS(self::NS_MAIN, 'definedNames');
            $xp->query('//m:sheets')->item(0)->after($defined);
        }

        // Se quitan los nombres locales del formato (filtro / área de impresión)
        foreach (iterator_to_array($xp->query('m:definedName[@localSheetId]', $defined)) as $node) {
            $defined->removeChild($node);
        }

        $counts = array_values(array_map('count', $groups));

        foreach ($titles as $i => $title) {
            $last   = self::LAST_ROW + max(0, $counts[$i] - (self::LAST_ROW - self::FIRST_ROW + 1));
            $quoted = "'" . str_replace("'", "''", $title) . "'";

            foreach ([
                '_xlnm._FilterDatabase' => ["{$quoted}!\$A\$7:\$AB\${$last}", true],
                '_xlnm.Print_Area'      => ["{$quoted}!\$A\$1:\$P\$" . ($last + 1), false],
                '_xlnm.Print_Titles'    => ["{$quoted}!\$6:\$7", false],
            ] as $name => [$ref, $hidden]) {
                $node = $workbook->createElementNS(self::NS_MAIN, 'definedName');
                $node->setAttribute('name', $name);
                $node->setAttribute('localSheetId', (string) $i);
                if ($hidden) {
                    $node->setAttribute('hidden', '1');
                }
                $node->appendChild($workbook->createTextNode($ref));
                $defined->appendChild($node);
            }
        }
    }

    /* ═══════════════ Hoja ═══════════════ */

    /**
     * @return array{xml:string, doc:DOMDocument, xp:DOMXPath}
     */
    private function buildSheet(string $templateXml, array $rows, array $cintilla, bool $keepParts, bool $hasLogo, bool $selected): array
    {
        $doc = new DOMDocument();
        $doc->preserveWhiteSpace = true;
        $doc->loadXML($templateXml);
        $xp = $this->xpath($doc);

        $capacity = self::LAST_ROW - self::FIRST_ROW + 1;
        $total    = max(count($rows), $capacity);
        $delta    = $total - $capacity;
        $lastRow  = self::LAST_ROW + $delta;

        // Pestaña seleccionada solo en la primera hoja
        foreach ($xp->query('//m:sheetView') as $view) {
            $selected ? $view->setAttribute('tabSelected', '1') : $view->removeAttribute('tabSelected');
        }

        // 1) Filas de datos: plantilla de fila intermedia (8) y de última fila (20)
        $sheetData = $xp->query('//m:sheetData')->item(0);
        $midTpl    = $xp->query("m:row[@r='" . self::FIRST_ROW . "']", $sheetData)->item(0);
        $lastTpl   = $xp->query("m:row[@r='" . self::LAST_ROW . "']", $sheetData)->item(0);
        $after     = $lastTpl->nextSibling;

        foreach (iterator_to_array($xp->query('m:row', $sheetData)) as $row) {
            $r = (int) $row->getAttribute('r');
            if ($r >= self::FIRST_ROW && $r <= self::LAST_ROW) {
                $sheetData->removeChild($row);
            } elseif ($r > self::LAST_ROW && $delta > 0) {
                $this->renumberRow($row, $r + $delta);
            }
        }

        for ($i = 0; $i < $total; $i++) {
            $r   = self::FIRST_ROW + $i;
            $tpl = $r === $lastRow ? $lastTpl : $midTpl;
            $row = $tpl->cloneNode(true);
            $this->renumberRow($row, $r);

            $data = $rows[$i] ?? null;
            if ($data) {
                foreach (self::COLUMNS as $col) {
                    $this->writeCell($xp, $row, "{$col}{$r}", $data[$col] ?? null);
                }
                $row->setAttribute('ht', (string) $this->rowHeight($data));
            } else {
                $row->setAttribute('ht', '30'); // filas vacías del formato
            }
            $row->setAttribute('customHeight', '1');

            $sheetData->insertBefore($row, $after);
        }

        // Columnas H a K (Quién emite y fechas de seguimiento) vienen ocultas en el formato: se muestran
        foreach ($xp->query('//m:cols/m:col') as $col) {
            if ((int) $col->getAttribute('min') >= 8 && (int) $col->getAttribute('max') <= 11) {
                $col->removeAttribute('hidden');
            }
        }

        // Impresión: en lugar del salto manual tras el encabezado, se repiten las filas 6 y 7 en cada hoja
        foreach (iterator_to_array($xp->query('//m:rowBreaks')) as $node) {
            $node->parentNode->removeChild($node);
        }

        // 2) Cintilla
        foreach (['codigo' => 2, 'revision' => 3, 'fecha_revision' => 4] as $key => $r) {
            if (isset($cintilla[$key]) && $cintilla[$key] !== '') {
                $rowEl = $xp->query("m:row[@r='{$r}']", $sheetData)->item(0);
                $this->writeCell($xp, $rowEl, "O{$r}", (string) $cintilla[$key]);
            }
        }

        if (! $hasLogo) {
            $rowEl = $xp->query("m:row[@r='3']", $sheetData)->item(0);
            $this->writeCell($xp, $rowEl, 'C3', 'EDITAR INFO');
        }

        // 3) Rangos (formato condicional, listas desplegables) que abarcan 8:20
        if ($delta > 0) {
            foreach ($xp->query('//m:conditionalFormatting | //m:dataValidation') as $node) {
                $node->setAttribute('sqref', $this->shiftSqref($node->getAttribute('sqref'), $delta));
            }
            $xp->registerNamespace('xm', 'http://schemas.microsoft.com/office/excel/2006/main');
            foreach ($xp->query('//xm:sqref') as $node) {
                $node->nodeValue = $this->shiftSqref($node->nodeValue, $delta);
            }
            foreach ($xp->query('//m:autoFilter') as $node) {
                $node->setAttribute('ref', $this->shiftSqref($node->getAttribute('ref'), $delta));
            }
        }

        // 4) Hojas clonadas: sin partes propias del formato (dibujos, comentarios, impresora)
        if (! $keepParts) {
            foreach (iterator_to_array($xp->query('//m:drawing | //m:legacyDrawing | //m:legacyDrawingHF')) as $node) {
                $node->parentNode->removeChild($node);
            }
            foreach ($xp->query('//m:pageSetup') as $node) {
                $node->removeAttributeNS(self::NS_REL, 'id');
            }
        }

        return ['xml' => $doc->saveXML(), 'doc' => $doc, 'xp' => $xp];
    }

    private function renumberRow(DOMElement $row, int $r): void
    {
        $row->setAttribute('r', (string) $r);
        foreach ($row->childNodes as $cell) {
            if ($cell instanceof DOMElement && $cell->hasAttribute('r')) {
                $cell->setAttribute('r', preg_replace('/\d+$/', (string) $r, $cell->getAttribute('r')));
            }
        }
    }

    /** Desplaza las filas >= 20 de una lista de rangos ("L8:N20 M10 D21:E22") */
    private function shiftSqref(string $sqref, int $delta): string
    {
        return preg_replace_callback('/([A-Z]+)(\d+)/', function ($m) use ($delta) {
            $r = (int) $m[2];

            return $m[1] . ($r >= self::LAST_ROW ? $r + $delta : $r);
        }, $sqref);
    }

    private function writeCell(DOMXPath $xp, DOMElement $row, string $ref, mixed $value): void
    {
        $doc  = $row->ownerDocument;
        $cell = $xp->query("m:c[@r='{$ref}']", $row)->item(0);

        if (! $cell instanceof DOMElement) {
            $cell = $doc->createElementNS(self::NS_MAIN, 'c');
            $cell->setAttribute('r', $ref);
            $col = self::colIndex($ref);
            $before = null;
            foreach ($xp->query('m:c', $row) as $existing) {
                if (self::colIndex($existing->getAttribute('r')) > $col) {
                    $before = $existing;
                    break;
                }
            }
            $row->insertBefore($cell, $before);
        }

        while ($cell->firstChild) {
            $cell->removeChild($cell->firstChild);
        }
        $cell->removeAttribute('t');

        if ($value === null || $value === '') {
            return;
        }

        if ($value instanceof \DateTimeInterface) {
            // Número de serie de Excel (base 1899-12-30): conserva el formato de fecha de la celda
            $serial = (int) (new \DateTimeImmutable('1899-12-30'))->diff(new \DateTimeImmutable($value->format('Y-m-d')))->format('%a');
            $cell->appendChild($doc->createElementNS(self::NS_MAIN, 'v', (string) $serial));

            return;
        }

        $cell->setAttribute('t', 'inlineStr');
        $is = $doc->createElementNS(self::NS_MAIN, 'is');
        $t  = $doc->createElementNS(self::NS_MAIN, 't');
        $t->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
        $t->appendChild($doc->createTextNode((string) $value));
        $is->appendChild($t);
        $cell->appendChild($is);
    }

    private static function colIndex(string $ref): int
    {
        preg_match('/^([A-Z]+)/', $ref, $m);
        $n = 0;
        foreach (str_split($m[1]) as $ch) {
            $n = $n * 26 + ord($ch) - 64;
        }

        return $n;
    }

    private function rowHeight(array $data): float
    {
        $lines = 1;
        foreach (self::WRAP as $col => $chars) {
            $text = $data[$col] ?? '';
            if (! is_string($text) || $text === '') {
                continue;
            }
            $n = 0;
            foreach (preg_split('/\R/u', $text) as $paragraph) {
                $n += max(1, (int) ceil(mb_strlen($paragraph) / $chars));
            }
            $lines = max($lines, $n);
        }

        return max(45, $lines * 13 + 10);
    }

    /* ═══════════════ Logo ═══════════════ */

    private function attachLogo(string $sheetPath, array &$sheet, string $logoPath, bool $isFirst, int $index, string $types): string
    {
        $info = getimagesize($logoPath);
        $ext  = match ($info[2] ?? null) {
            IMAGETYPE_PNG  => 'png',
            IMAGETYPE_JPEG => 'jpeg',
            default        => null,
        };
        if (! $ext) {
            return $types;
        }

        $media = "xl/media/sga_logo.{$ext}";
        if ($this->zip->locateName($media) === false) {
            $this->zip->addFile($logoPath, $media);
        }
        if (! str_contains($types, 'Extension="' . $ext . '"')) {
            $types = str_replace('<Default Extension="rels"', '<Default Extension="' . $ext . '" ContentType="image/' . $ext . '"/><Default Extension="rels"', $types);
        }

        [$imgW, $imgH] = $info;
        $boxW  = self::LOGO_BOX_W - 2 * self::LOGO_PAD;
        $boxH  = self::LOGO_BOX_H - 2 * self::LOGO_PAD;
        $scale = min($boxW / $imgW, $boxH / $imgH);
        $w     = (int) round($imgW * $scale);
        $h     = (int) round($imgH * $scale);
        $offX  = self::LOGO_PAD + (int) (($boxW - $w) / 2);
        $offY  = self::LOGO_PAD + (int) (($boxH - $h) / 2);
        $emu   = fn (int $px) => $px * self::EMU_PER_PX;

        $anchor = '<xdr:oneCellAnchor>'
            . '<xdr:from><xdr:col>1</xdr:col><xdr:colOff>' . $emu($offX) . '</xdr:colOff><xdr:row>1</xdr:row><xdr:rowOff>' . $emu($offY) . '</xdr:rowOff></xdr:from>'
            . '<xdr:ext cx="' . $emu($w) . '" cy="' . $emu($h) . '"/>'
            . '<xdr:pic><xdr:nvPicPr><xdr:cNvPr id="5000" name="Logo SGA"/><xdr:cNvPicPr><a:picLocks noChangeAspect="1"/></xdr:cNvPicPr></xdr:nvPicPr>'
            . '<xdr:blipFill><a:blip xmlns:r="' . self::NS_REL . '" r:embed="rIdSgaLogo"/><a:stretch><a:fillRect/></a:stretch></xdr:blipFill>'
            . '<xdr:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="' . $emu($w) . '" cy="' . $emu($h) . '"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></xdr:spPr>'
            . '</xdr:pic><xdr:clientData/></xdr:oneCellAnchor>';

        $imageRel  = '<Relationship Id="rIdSgaLogo" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/sga_logo.' . $ext . '"/>';
        $emptyRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="' . self::NS_PKG . '"></Relationships>';

        if ($isFirst) {
            // Dibujo existente de la hoja del formato
            $sheetRels = $this->zip->getFromName(dirname($sheetPath) . '/_rels/' . basename($sheetPath) . '.rels') ?: '';
            if (! preg_match('/Type="[^"]*\/drawing" Target="([^"]+)"/', $sheetRels, $m)) {
                return $types;
            }
            $drawingPath = $this->resolve(dirname($sheetPath), $m[1]);
            $drawingRels = dirname($drawingPath) . '/_rels/' . basename($drawingPath) . '.rels';

            $this->zip->addFromString($drawingPath, str_replace('</xdr:wsDr>', $anchor . '</xdr:wsDr>', $this->zip->getFromName($drawingPath)));
            $this->zip->addFromString($drawingRels, str_replace('</Relationships>', $imageRel . '</Relationships>', $this->zip->getFromName($drawingRels) ?: $emptyRels));

            return $types;
        }

        // Hoja clonada: dibujo nuevo solo con el logo
        $drawingPath = "xl/drawings/drawingSga{$index}.xml";
        $this->zip->addFromString($drawingPath,
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">'
            . $anchor . '</xdr:wsDr>');
        $this->zip->addFromString("xl/drawings/_rels/drawingSga{$index}.xml.rels", str_replace('</Relationships>', $imageRel . '</Relationships>', $emptyRels));

        $this->zip->addFromString(dirname($sheetPath) . '/_rels/' . basename($sheetPath) . '.rels',
            str_replace('</Relationships>', '<Relationship Id="rIdSgaDrawing" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawingSga' . $index . '.xml"/></Relationships>', $emptyRels));

        // <drawing> va antes de <legacyDrawing>/<extLst> según el esquema
        $doc = $sheet['doc'];
        $xp  = $sheet['xp'];
        $drawing = $doc->createElementNS(self::NS_MAIN, 'drawing');
        $drawing->setAttributeNS(self::NS_REL, 'r:id', 'rIdSgaDrawing');
        $before = $xp->query('//m:legacyDrawing | //m:legacyDrawingHF | //m:picture | //m:oleObjects | //m:controls | //m:webPublishItems | //m:tableParts | //m:extLst')->item(0);
        $doc->documentElement->insertBefore($drawing, $before);
        $sheet['xml'] = $doc->saveXML();

        return str_replace('</Types>', '<Override PartName="/' . $drawingPath . '" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/></Types>', $types);
    }

    /* ═══════════════ Utilidades ═══════════════ */

    private function dom(string $path): DOMDocument
    {
        $doc = new DOMDocument();
        $doc->preserveWhiteSpace = true;
        $doc->loadXML($this->zip->getFromName($path));

        return $doc;
    }

    private function xpath(DOMDocument $doc): DOMXPath
    {
        $xp = new DOMXPath($doc);
        $xp->registerNamespace('m', self::NS_MAIN);
        $xp->registerNamespace('r', self::NS_REL);

        return $xp;
    }

    private function resolve(string $baseDir, string $target): string
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