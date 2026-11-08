<?php

namespace App\Dto;

use App\Validator\CpfCnpj;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Corpo do POST /api/registro.
 * Se "documento" (CPF/CNPJ) vier preenchido, o usuário já é cadastrado como artista.
 */
final readonly class RegistroDto
{
    public function __construct(
        #[Assert\NotBlank(message: 'Informe o nome.')]
        #[Assert\Length(max: 120)]
        public string $nome = '',

        #[Assert\NotBlank(message: 'Informe o e-mail.')]
        #[Assert\Email(message: 'E-mail inválido.')]
        public string $email = '',

        #[Assert\NotBlank(message: 'Informe a senha.')]
        #[Assert\Length(min: 8, max: 4096, minMessage: 'A senha precisa ter pelo menos {{ limit }} caracteres.')]
        public string $senha = '',

        #[Assert\NotBlank(message: 'Informe o telefone.')]
        public string $telefone = '',

        #[CpfCnpj]
        public ?string $documento = null,

        #[Assert\Length(max: 2000)]
        public ?string $bio = null,
    ) {
    }
}
