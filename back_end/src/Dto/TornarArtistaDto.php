<?php

namespace App\Dto;

use App\Validator\CpfCnpj;
use Symfony\Component\Validator\Constraints as Assert;

/** Corpo do POST /api/me/artista (visitante completando o perfil para virar artista). */
final readonly class TornarArtistaDto
{
    public function __construct(
        #[Assert\NotBlank(message: 'Informe o CPF ou CNPJ.')]
        #[CpfCnpj]
        public string $documento = '',

        #[Assert\Length(max: 2000)]
        public ?string $bio = null,
    ) {
    }
}
