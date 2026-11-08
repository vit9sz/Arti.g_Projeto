<?php

namespace App\EventListener;

use App\Entity\Usuario;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Além do token, devolve os dados do usuário no login.
 * Assim o React já sabe o nome e se é artista sem fazer outra requisição.
 *
 *   { "token": "eyJ...", "usuario": { "id": 1, "nome": "...", "ehArtista": true, ... } }
 */
#[AsEventListener(event: Events::AUTHENTICATION_SUCCESS)]
final class LoginSucessoListener
{
    public function __construct(private readonly NormalizerInterface $normalizer)
    {
    }

    public function __invoke(AuthenticationSuccessEvent $event): void
    {
        $usuario = $event->getUser();
        if (!$usuario instanceof Usuario) {
            return;
        }

        $dados = $event->getData();
        $dados['usuario'] = $this->normalizer->normalize($usuario, null, ['groups' => ['usuario:publico', 'usuario:privado']]);
        $event->setData($dados);
    }
}
