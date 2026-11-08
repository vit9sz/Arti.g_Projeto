<?php

namespace App\Service;

use App\Entity\ExclusaoLogicaInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * CRUD genérico: funciona para qualquer entidade.
 *
 * Os cuidados para ele não virar uma falha de segurança:
 *  - QUEM pode mexer é decidido nos controllers com Voters (#[IsGranted]).
 *  - QUAIS campos podem ser alterados é decidido pelo grupo de escrita
 *    (#[Groups(['obra:escrita'])] etc.). Campo fora do grupo é ignorado,
 *    então ninguém consegue mandar {"artista": 2} ou {"roles": [...]} no JSON.
 */
final class CrudService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly DenormalizerInterface $denormalizer,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $classe
     *
     * @return T
     */
    public function buscarOu404(string $classe, int $id): object
    {
        $entidade = $this->em->find($classe, $id);

        if (null === $entidade || ($entidade instanceof ExclusaoLogicaInterface && $entidade->isExcluido())) {
            throw new NotFoundHttpException('Registro não encontrado.');
        }

        return $entidade;
    }

    /**
     * Copia os campos permitidos de $dados para a entidade (nova ou existente).
     *
     * @template T of object
     *
     * @param T            $entidade
     * @param array<mixed> $dados
     * @param list<string> $gruposEscrita
     *
     * @return T
     */
    public function preencher(object $entidade, array $dados, array $gruposEscrita): object
    {
        return $this->denormalizer->denormalize($dados, $entidade::class, null, [
            AbstractNormalizer::OBJECT_TO_POPULATE => $entidade,
            AbstractNormalizer::GROUPS => $gruposEscrita,
        ]);
    }

    /**
     * Valida e grava uma ou mais entidades na mesma transação.
     * Se algo estiver inválido, lança ValidationFailedException (vira HTTP 422).
     */
    public function salvar(object $entidade, object ...$outras): object
    {
        foreach ([$entidade, ...$outras] as $item) {
            $violacoes = $this->validator->validate($item);
            if (count($violacoes) > 0) {
                throw new ValidationFailedException($item, $violacoes);
            }
        }

        foreach ([$entidade, ...$outras] as $item) {
            $this->em->persist($item);
        }
        $this->em->flush();

        return $entidade;
    }

    /**
     * @param array<mixed> $dados
     * @param list<string> $gruposEscrita
     */
    public function atualizar(object $entidade, array $dados, array $gruposEscrita): object
    {
        return $this->salvar($this->preencher($entidade, $dados, $gruposEscrita));
    }

    /** Exclusão lógica quando a entidade suporta; senão remove de verdade. */
    public function excluir(object $entidade): void
    {
        if ($entidade instanceof ExclusaoLogicaInterface) {
            $entidade->marcarComoExcluido();
        } else {
            $this->em->remove($entidade);
        }

        $this->em->flush();
    }
}
