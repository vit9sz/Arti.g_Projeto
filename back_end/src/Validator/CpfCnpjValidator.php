<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class CpfCnpjValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof CpfCnpj) {
            throw new UnexpectedTypeException($constraint, CpfCnpj::class);
        }

        if (null === $value || '' === $value) {
            return; // NotBlank cuida do vazio
        }

        $numeros = preg_replace('/\D/', '', (string) $value) ?? '';

        $valido = match (strlen($numeros)) {
            11 => self::cpfValido($numeros),
            14 => self::cnpjValido($numeros),
            default => false,
        };

        if (!$valido) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }

    public static function cpfValido(string $cpf): bool
    {
        if (preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false; // 000.000.000-00, 111.111.111-11...
        }

        for ($t = 9; $t < 11; ++$t) {
            $soma = 0;
            for ($i = 0; $i < $t; ++$i) {
                $soma += (int) $cpf[$i] * (($t + 1) - $i);
            }
            $digito = ((10 * $soma) % 11) % 10;
            if ((int) $cpf[$t] !== $digito) {
                return false;
            }
        }

        return true;
    }

    public static function cnpjValido(string $cnpj): bool
    {
        if (preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }

        $pesos = [
            [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2],
            [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2],
        ];

        foreach ($pesos as $indice => $pesosDigito) {
            $soma = 0;
            foreach ($pesosDigito as $i => $peso) {
                $soma += (int) $cnpj[$i] * $peso;
            }
            $resto = $soma % 11;
            $digito = $resto < 2 ? 0 : 11 - $resto;
            if ((int) $cnpj[12 + $indice] !== $digito) {
                return false;
            }
        }

        return true;
    }
}
