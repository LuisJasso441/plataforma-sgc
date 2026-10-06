<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use ZipArchive;

/**
 * Genera el "Reporte de No Conformidad y Acción Correctiva" prellenado
 * editando el XML del formato original (conserva casillas, dibujos y hojas).
 * PhpSpreadsheet NO se usa aquí porque elimina los controles de formulario.
 */
class NcReportTemplate
{
    private const SHEET        = 'xl/worksheets/sheet1.xml';
    private const SHEET_RELS   = 'xl/worksheets/_rels/sheet1.xml.rels';
    private const VML          = 'xl/drawings/vmlDrawing1.vml';
    private const DRAWING      = 'xl/drawings/drawing1.xml';
    private const DRAWING_RELS = 'xl/drawings/_rels/drawing1.xml.rels';

    private const NS_MAIN = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    private const NS_REL  = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    /** Celdas del formato */
    public const CELLS = [
        'codigo'         => 'N1',
        'revision'       => 'N3',
        'fecha_revision' => 'N5',
        'logo_texto'     => 'B3',   // texto provisional si no hay logo
        'folio'          => 'C13',
        'proceso'        => 'C14',
        'lider'          => 'D15',
        'descripcion'    => 'B20',
    ];

    /** Casillas de "Origen de acción" (shapeId del control de formulario) */
    public const ORIGIN_SHAPES = [
        'servicio_proceso_producto' => 2146,
        'revision_direccion'        => 2131,
        'cliente'                   => 2147,
        'auditoria_interna'         => 2144,
        'auditoria_externa'         => 2145,
    ];

    /** Recuadro del logo (B1:C6) en píxeles */
    private const LOGO_BOX_W = 195;
    private const LOGO_BOX_H = 92;
    private const LOGO_PAD   = 4;
    private const EMU_PER_PX = 9525;

    private DOMDocument $sheet;
    private DOMXPath $xp;

    /**
     * @param  array{codigo?:string, revision?:string, fecha_revision?:string, folio?:string,
     *               proceso?:string, lider?:string, descripcion?:string, origen?:?string}  $data
     * @return string  ruta del archivo generado (temporal)
     */
    public static function fill(string $templatePath, array $data, ?string $logoPath = null): string
    {
        if (! is_file($templatePath)) {
            throw new RuntimeException("No se encontró el formato: {$templatePath}");
        }

        $output = tempnam(sys_get_temp_dir(), 'nc_reporte_') . '.xlsx';
        copy($templatePath, $output);

        $zip = new ZipArchive();
        if ($zip->open($output) !== true) {
            throw new RuntimeException('No se pudo abrir el formato.');
        }

        $self = new self($zip->getFromName(self::SHEET));

        foreach (['codigo', 'revision', 'fecha_revision', 'folio', 'proceso', 'lider', 'descripcion'] as $key) {
            if (isset($data[$key]) && $data[$key] !== '') {
                $self->setCell(self::CELLS[$key], (string) $data[$key]);
            }
        }

        if (! empty($data['descripcion'])) {
            $self->fitRowHeight(20, (string) $data['descripcion'], minHeight: 91.5, charsPerLine: 150);
        }

        $hasLogo = $logoPath && is_file($logoPath);
        if (! $hasLogo) {
            $self->setCell(self::CELLS['logo_texto'], 'EDITAR INFO');
        }

        $zip->addFromString(self::SHEET, $self->sheet->saveXML());

        if (! empty($data['origen']) && isset(self::ORIGIN_SHAPES[$data['origen']])) {
            self::checkBox($zip, $self, self::ORIGIN_SHAPES[$data['origen']]);
        }

        if ($hasLogo) {
            self::addLogo($zip, $logoPath);
        }

        $zip->close();

        return $output;
    }

    private function __construct(string $sheetXml)
    {
        $this->sheet = new DOMDocument();
        $this->sheet->preserveWhiteSpace = true;
        $this->sheet->loadXML($sheetXml);

        $this->xp = new DOMXPath($this->sheet);
        $this->xp->registerNamespace('m', self::NS_MAIN);
        $this->xp->registerNamespace('r', self::NS_REL);
    }

    /* ───────────── Celdas ───────────── */

    private function setCell(string $ref, string $text): void
    {
        [$col, $row] = self::splitRef($ref);

        $rowEl  = $this->rowElement($row);
        $cellEl = $this->cellElement($rowEl, $ref, $col);

        while ($cellEl->firstChild) {
            $cellEl->removeChild($cellEl->firstChild);
        }

        // Texto en línea: conserva el estilo (s="...") de la celda
        $cellEl->setAttribute('t', 'inlineStr');

        $is = $this->sheet->createElementNS(self::NS_MAIN, 'is');
        $t  = $this->sheet->createElementNS(self::NS_MAIN, 't');
        $t->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
        $t->appendChild($this->sheet->createTextNode($text));
        $is->appendChild($t);
        $cellEl->appendChild($is);
    }

    private function fitRowHeight(int $row, string $text, float $minHeight, int $charsPerLine): void
    {
        $lines = 0;
        foreach (preg_split('/\R/u', $text) as $paragraph) {
            $lines += max(1, (int) ceil(mb_strlen($paragraph) / $charsPerLine));
        }

        $rowEl = $this->rowElement($row);
        $rowEl->setAttribute('ht', (string) max($minHeight, $lines * 15 + 6));
        $rowEl->setAttribute('customHeight', '1');
    }

    private function rowElement(int $row): DOMElement
    {
        $found = $this->xp->query("//m:sheetData/m:row[@r='{$row}']")->item(0);
        if ($found instanceof DOMElement) {
            return $found;
        }

        $sheetData = $this->xp->query('//m:sheetData')->item(0);
        $new = $this->sheet->createElementNS(self::NS_MAIN, 'row');
        $new->setAttribute('r', (string) $row);

        foreach ($this->xp->query('m:row', $sheetData) as $existing) {
            if ((int) $existing->getAttribute('r') > $row) {
                return $sheetData->insertBefore($new, $existing);
            }
        }

        return $sheetData->appendChild($new);
    }

    private function cellElement(DOMElement $rowEl, string $ref, int $col): DOMElement
    {
        $found = $this->xp->query("m:c[@r='{$ref}']", $rowEl)->item(0);
        if ($found instanceof DOMElement) {
            return $found;
        }

        $new = $this->sheet->createElementNS(self::NS_MAIN, 'c');
        $new->setAttribute('r', $ref);

        foreach ($this->xp->query('m:c', $rowEl) as $existing) {
            [$existingCol] = self::splitRef($existing->getAttribute('r'));
            if ($existingCol > $col) {
                return $rowEl->insertBefore($new, $existing);
            }
        }

        return $rowEl->appendChild($new);
    }

    /** "C13" → [3, 13] */
    private static function splitRef(string $ref): array
    {
        preg_match('/^([A-Z]+)(\d+)$/', $ref, $m);

        $col = 0;
        foreach (str_split($m[1]) as $letter) {
            $col = $col * 26 + (ord($letter) - 64);
        }

        return [$col, (int) $m[2]];
    }

    /* ───────────── Casillas (controles de formulario) ───────────── */

    private static function checkBox(ZipArchive $zip, self $self, int $shapeId): void
    {
        // 1) ctrlProp (Excel 2010+): control → r:id → ctrlPropN.xml
        $control = $self->xp->query("//m:control[@shapeId='{$shapeId}']")->item(0);

        if ($control instanceof DOMElement) {
            $rid  = $control->getAttributeNS(self::NS_REL, 'id');
            $rels = $zip->getFromName(self::SHEET_RELS);

            if ($rid && preg_match('/Id="' . preg_quote($rid, '/') . '"[^>]*Target="\.\.\/([^"]+)"/', $rels, $m)) {
                $path = 'xl/' . $m[1];
                $xml  = $zip->getFromName($path);

                if ($xml !== false && ! str_contains($xml, 'checked=')) {
                    $xml = preg_replace('/<formControlPr\b/', '<formControlPr checked="Checked"', $xml, 1);
                    $zip->addFromString($path, $xml);
                }
            }
        }

        // 2) VML (respaldo para versiones anteriores de Excel)
        $vml = $zip->getFromName(self::VML);
        if ($vml !== false) {
            $vml = preg_replace_callback(
                '/(<v:shape id="_x0000_s' . $shapeId . '".*?)(<\/x:ClientData>)/s',
                fn ($m) => str_contains($m[1], '<x:Checked>') ? $m[0] : $m[1] . '<x:Checked>1</x:Checked>' . $m[2],
                $vml,
                1,
            );
            $zip->addFromString(self::VML, $vml);
        }
    }

    /* ───────────── Logo ───────────── */

    private static function addLogo(ZipArchive $zip, string $logoPath): void
    {
        $info = getimagesize($logoPath);
        if (! $info) {
            return;
        }

        [$imgW, $imgH] = $info;
        $ext = match ($info[2]) {
            IMAGETYPE_PNG  => 'png',
            IMAGETYPE_JPEG => 'jpeg',
            default        => null,
        };
        if (! $ext) {
            return;
        }

        // Ajustar al recuadro conservando proporción y centrar
        $boxW  = self::LOGO_BOX_W - 2 * self::LOGO_PAD;
        $boxH  = self::LOGO_BOX_H - 2 * self::LOGO_PAD;
        $scale = min($boxW / $imgW, $boxH / $imgH);
        $w     = (int) round($imgW * $scale);
        $h     = (int) round($imgH * $scale);
        $offX  = self::LOGO_PAD + (int) (($boxW - $w) / 2);
        $offY  = self::LOGO_PAD + (int) (($boxH - $h) / 2);

        $emu = fn (int $px) => $px * self::EMU_PER_PX;

        $zip->addFile($logoPath, "xl/media/sga_logo.{$ext}");

        // Relación dibujo → imagen
        $rels = $zip->getFromName(self::DRAWING_RELS)
            ?: '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
             . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"></Relationships>';
        $rels = str_replace(
            '</Relationships>',
            '<Relationship Id="rIdSgaLogo" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/sga_logo.' . $ext . '"/></Relationships>',
            $rels,
        );
        $zip->addFromString(self::DRAWING_RELS, $rels);

        // Tipo de contenido de la imagen
        $types = $zip->getFromName('[Content_Types].xml');
        if (! str_contains($types, 'Extension="' . $ext . '"')) {
            $types = str_replace(
                '<Default Extension="rels"',
                '<Default Extension="' . $ext . '" ContentType="image/' . $ext . '"/><Default Extension="rels"',
                $types,
            );
            $zip->addFromString('[Content_Types].xml', $types);
        }

        // Imagen anclada en B1
        $anchor = '<xdr:oneCellAnchor>'
            . '<xdr:from><xdr:col>1</xdr:col><xdr:colOff>' . $emu($offX) . '</xdr:colOff><xdr:row>0</xdr:row><xdr:rowOff>' . $emu($offY) . '</xdr:rowOff></xdr:from>'
            . '<xdr:ext cx="' . $emu($w) . '" cy="' . $emu($h) . '"/>'
            . '<xdr:pic><xdr:nvPicPr><xdr:cNvPr id="5000" name="Logo SGA"/><xdr:cNvPicPr><a:picLocks noChangeAspect="1"/></xdr:cNvPicPr></xdr:nvPicPr>'
            . '<xdr:blipFill><a:blip xmlns:r="' . self::NS_REL . '" r:embed="rIdSgaLogo"/><a:stretch><a:fillRect/></a:stretch></xdr:blipFill>'
            . '<xdr:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="' . $emu($w) . '" cy="' . $emu($h) . '"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></xdr:spPr>'
            . '</xdr:pic><xdr:clientData/></xdr:oneCellAnchor>';

        $drawing = $zip->getFromName(self::DRAWING);
        $zip->addFromString(self::DRAWING, str_replace('</xdr:wsDr>', $anchor . '</xdr:wsDr>', $drawing));
    }
}