<?php

namespace App\Security\Voter;

use App\Entity\Comentario;
use App\Entity\Usuario;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * - Editar: só o autor do comentário.
 * - Excluir: o autor OU o artista dono da obra comentada.
 *
 * @extends Voter<string, Comentario>
 */
final class ComentarioVoter extends Voter
{
    public const EDITAR = 'COMENTARIO_EDITAR';
    public const EXCLUIR = 'COMENTARIO_EXCLUIR';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::EDITAR, self::EXCLUIR], true) && $subject instanceof Comentario;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $usuario = $token->getUser();
        if (!$usuario instanceof Usuario) {
            return false;
        }

        $ehAutor = $subject->getAutor() === $usuario;

        return match ($attribute) {
            self::EDITAR => $ehAutor,
            self::EXCLUIR => $ehAutor || $subject->getObra()->getArtista()->getUsuario() === $usuario,
            default => false,
        };
    }
}
