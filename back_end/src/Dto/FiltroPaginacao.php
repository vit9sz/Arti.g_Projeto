<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/** Query string das listagens: ?busca=&categoria=&artista=&pagina=&porPagina= */
final readonly class FiltroPaginacao
{
    public function __construct(
        #[Assert\Length(max: 100)]
        public ?string $busca = null,

        #[Assert\Positive]
        public ?int $categoria = null,

        #[Assert\Positive]
        public ?int $artista = null,

        #[Assert\Positive]
        public int $pagina = 1,

        #[Assert\Range(min: 1, max: 60)]
        public int $porPagina = 20,
    ) {
    }
}
