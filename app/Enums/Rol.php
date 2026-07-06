<?php

namespace App\Enums;

enum Rol: string
{
    case Administrador = 'Administrador';
    case Gestor = 'Gestor';
    case Consulta = 'Consulta';
}
