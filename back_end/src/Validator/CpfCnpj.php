<?php

namespace App\Validator;

use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint;

/**
 * Valida CPF (11 dígitos) ou CNPJ (14 dígitos), conferindo os dígitos verificadores.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
class CpfCnpj extends Constraint
{
    public string $message = 'CPF ou CNPJ inválido.';

    #[HasNamedArguments]
    public function __construct(?string $message = null, ?array $groups = null, mixed $payload = null)
    {
        parent::__construct(null, $groups, $payload);
        $this->message = $message ?? $this->message;
    }
}
