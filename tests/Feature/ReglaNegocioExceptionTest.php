<?php

namespace Tests\Feature;

use App\Exceptions\ReglaNegocioException;
use Tests\TestCase;

/** La respuesta 422 de una regla de negocio: mensaje, lista y, si los hay, errores por campo. */
class ReglaNegocioExceptionTest extends TestCase
{
    private function respuesta(ReglaNegocioException $e): array
    {
        $respuesta = $e->render();
        $this->assertSame(422, $respuesta->getStatusCode());

        return $respuesta->getData(true);
    }

    public function test_sin_campos_responde_como_antes(): void
    {
        $this->assertSame(['mensaje' => 'El expediente está radicado.'], $this->respuesta(new ReglaNegocioException('El expediente está radicado.')));
        $this->assertSame(
            ['mensaje' => 'No es válido.', 'errores' => ['Falta el nombre.']],
            $this->respuesta(new ReglaNegocioException('No es válido.', ['Falta el nombre.'])),
        );
    }

    public function test_los_campos_viajan_como_errors_junto_al_mensaje_y_la_lista(): void
    {
        $e = new ReglaNegocioException('El requisito no es válido.', ['Elige la etapa.', 'Falta la descripción.'], ['etapa_id' => ['Elige la etapa.']]);

        $this->assertSame([
            'mensaje' => 'El requisito no es válido.',
            'errores' => ['Elige la etapa.', 'Falta la descripción.'],
            'errors' => ['etapa_id' => ['Elige la etapa.']],
        ], $this->respuesta($e));
        $this->assertSame(['etapa_id' => ['Elige la etapa.']], $e->campos());
        $this->assertSame(['Elige la etapa.', 'Falta la descripción.'], $e->errores());
    }

    public function test_un_campo_sin_mensajes_no_viaja(): void
    {
        $this->assertSame(
            ['mensaje' => 'No es válido.', 'errores' => ['Falta el nombre.']],
            $this->respuesta(new ReglaNegocioException('No es válido.', ['Falta el nombre.'], ['etapa_id' => []])),
        );
    }
}
