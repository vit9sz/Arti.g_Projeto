<?php

namespace App\Security\Voter;

use App\Entity\Obra;
use App\Entity\Usuario;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Só o artista dono pode editar ou excluir a obra.
 *
 * @extends Voter<string, Obra>
 */
final class ObraVoter extends Voter
{
    public const EDITAR = 'OBRA_EDITAR';
    public const EXCLUIR = 'OBRA_EXCLUIR';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::EDITAR, self::EXCLUIR], true) && $subject instanceof Obra;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $usuario = $token->getUser();
        if (!$usuario instanceof Usuario) {
            return false;
        }

        return $subject->getArtista()->getUsuario() === $usuario;
    }
}
