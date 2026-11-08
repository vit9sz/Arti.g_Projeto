<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/** Corpo do POST /api/obras/{id}/comentarios */
final readonly class ComentarioDto
{
    public function __construct(
        #[Assert\NotBlank(message: 'O comentário não pode ficar vazio.')]
        #[Assert\Length(max: 5000, maxMessage: 'O comentário pode ter no máximo {{ limit }} caracteres.')]
        public string $texto = '',

        /** id do comentário que está sendo respondido (opcional) */
        #[Assert\Positive]
        public ?int $respostaA = null,
    ) {
    }
}
