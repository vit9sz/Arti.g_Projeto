<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Padroniza os erros da API em JSON para o React tratar sempre do mesmo jeito:
 *
 *   { "erro": "mensagem", "campos": { "email": ["E-mail inválido."] } }
 *
 * "campos" só aparece em erros de validação (HTTP 422).
 */
#[AsEventListener(event: KernelEvents::EXCEPTION)]
final class ApiExceptionListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api')) {
            return;
        }

        $erro = $event->getThrowable();

        // #[MapRequestPayload] embrulha a ValidationFailedException numa HttpException 422
        $validacao = $erro instanceof ValidationFailedException ? $erro : $erro->getPrevious();
        if ($validacao instanceof ValidationFailedException) {
            $event->setResponse(new JsonResponse([
                'erro' => 'Existem campos inválidos.',
                'campos' => $this->agruparPorCampo($validacao->getViolations()),
            ], 422));

            return;
        }

        if ($erro instanceof HttpExceptionInterface) {
            $event->setResponse(new JsonResponse(
                ['erro' => $erro->getMessage() ?: 'Erro na requisição.'],
                $erro->getStatusCode(),
                $erro->getHeaders(),
            ));
        }

        // Outros erros (500) seguem o fluxo normal: em dev o Symfony mostra a página de debug.
    }

    /** @return array<string, list<string>> */
    private function agruparPorCampo(ConstraintViolationListInterface $violacoes): array
    {
        $campos = [];
        foreach ($violacoes as $violacao) {
            $campo = $violacao->getPropertyPath() ?: 'geral';
            $campos[$campo][] = (string) $violacao->getMessage();
        }

        return $campos;
    }
}
