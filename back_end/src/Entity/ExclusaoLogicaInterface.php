<?php

namespace App\Entity;

/**
 * Entidades que não são apagadas do banco: só recebem a data de exclusão.
 *
 * Regra de negócio: excluir uma obra não apaga comentários e curtidas
 * (sem cascade). Por isso a obra fica no banco marcada como excluída e
 * some das listagens.
 */
interface ExclusaoLogicaInterface
{
    public function marcarComoExcluido(): void;

    public function isExcluido(): bool;
}
