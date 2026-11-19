<?php

/*
 * Avisos de contratación (AvisosContratacion). Los días son calendario, en la zona `zona` (la
 * aplicación guarda y responde en UTC); "hoy" es now() en esa zona.
 */
return [

    // Zona horaria de los días de contratación: "hoy", los días sin actualización, la fecha real
    // sugerida y el tope de la fecha real.
    'zona' => env('CONTRATACION_ZONA', 'America/Bogota'),

    // Una etapa o un cierre está próximo si faltan estos días o menos.
    'dias_proxima' => (int) env('CONTRATACION_DIAS_PROXIMA', 3),

    // Un proceso está sin actualización si pasaron estos días o más desde su última actividad.
    'dias_sin_actualizacion' => (int) env('CONTRATACION_DIAS_SIN_ACTUALIZACION', 15),

];
