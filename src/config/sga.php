<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Reporte de No Conformidad y Acción Correctiva (formato oficial)
    |--------------------------------------------------------------------------
    | Valores FIJOS de la cintilla del formato. Se editan aquí, en el código.
    | El logo es un archivo PNG o JPG en la ruta indicada; si no existe,
    | el recuadro del logo muestra "EDITAR INFO".
    */

    'reporte_nc' => [
        // El nombre de este archivo es también el nombre de descarga: "<nombre>_<folio>.xlsx"
        'plantilla'      => resource_path('templates/no-conformidad/FR-SG-06_Reporte de no conformidad y acción correctiva.xlsx'),
        'logo'           => resource_path('templates/no-conformidad/logo.png'),

        'codigo'         => 'FR-SG-06',
        'revision'       => '01',
        'fecha_revision' => '19/03/2022',
    ],

];