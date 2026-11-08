<?php

namespace App\Controller;

use App\Dto\RegistroDto;
use App\Entity\PerfilArtista;
use App\Entity\Usuario;
use App\Service\CrudService;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
final class AuthController extends AbstractController
{
    public function __construct(
        private readonly CrudService $crud,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly JWTTokenManagerInterface $jwt,
    ) {
    }

    /**
     * POST /api/login  { "email": "...", "senha": "..." }  →  { "token": "...", "usuario": {...} }
     *
     * Este método nunca executa: o firewall "login" (json_login em security.yaml)
     * intercepta a requisição antes e o LexikJWT gera o token.
     */
    #[Route('/login', name: 'api_login', methods: ['POST'])]
    public function login(): never
    {
        throw new \LogicException('Interceptado pelo firewall json_login.');
    }

    /**
     * POST /api/registro
     * { "nome", "email", "senha", "telefone", "documento"?: "CPF ou CNPJ", "bio"? }
     * Sem "documento" → visitante. Com "documento" → artista.
     */
    #[Route('/registro', name: 'api_registro', methods: ['POST'])]
    public function registrar(#[MapRequestPayload] RegistroDto $dto): JsonResponse
    {
        $usuario = (new Usuario())
            ->setNome($dto->nome)
            ->setEmail($dto->email)
            ->setTelefone($dto->telefone);
        $usuario->setSenha($this->hasher->hashPassword($usuario, $dto->senha));

        $entidades = [$usuario];
        if (null !== $dto->documento && '' !== trim($dto->documento)) {
            $entidades[] = (new PerfilArtista($usuario, $dto->documento))->setBio($dto->bio);
        }

        $this->crud->salvar(...$entidades);

        return $this->json([
            'token' => $this->jwt->create($usuario),
            'usuario' => $usuario,
        ], Response::HTTP_CREATED, [], ['groups' => ['usuario:publico', 'usuario:privado']]);
    }
}
