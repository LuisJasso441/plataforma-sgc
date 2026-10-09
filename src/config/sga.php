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

    /*
    |--------------------------------------------------------------------------
    | Logo de la empresa en la PLATAFORMA (LOGO PENDIENTE)
    |--------------------------------------------------------------------------
    | Ruta relativa a public/. Solo se usa en la interfaz web (login).
    | Los formatos de Excel usan su propio logo (reporte_nc.logo).
    */

    'logo_empresa' => 'images/logo-empresa.png',

    'reporte_nc' => [
        // El nombre de este archivo es también el nombre de descarga: "<nombre>_<folio>.xlsx"
        'plantilla'      => resource_path('templates/no-conformidad/FR-SG-06_Reporte de no conformidad y acción correctiva.xlsx'),
        // Logo de los formatos de Excel (reporte y bitácora)
        'logo'           => resource_path('templates/no-conformidad/logo.png'),

        'codigo'         => 'FR-SG-06',
        'revision'       => '01',
        'fecha_revision' => '19/03/2022',
    ],

    /*
    |--------------------------------------------------------------------------
    | Bitácora de No Conformidades y Acciones Correctivas (FR-SG-05)
    |--------------------------------------------------------------------------
    | Valores FIJOS de la cintilla. El logo es el mismo del reporte.
    | El nombre del archivo de la plantilla es también el nombre de descarga.
    */

    'bitacora_nc' => [
        'plantilla'      => resource_path('templates/no-conformidad/FR-SG-05_Bitácora de no conformidades y acciones correctivas.xlsx'),

        'codigo'         => 'FR-SG-05',
        'revision'       => '00',
        'fecha_revision' => '22/07/2021',
    ],

];