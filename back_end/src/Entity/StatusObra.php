<?php

namespace App\Entity;

enum StatusObra: string
{
    case Disponivel = 'disponivel';
    case Vendida = 'vendida';
}
