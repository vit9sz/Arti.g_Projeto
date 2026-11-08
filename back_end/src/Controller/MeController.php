<?php

namespace App\Controller;

use App\Dto\TornarArtistaDto;
use App\Entity\PerfilArtista;
use App\Entity\Usuario;
use App\Repository\ObraRepository;
use App\Service\CrudService;
use App\Service\UploadImagem;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Tudo sobre o usuário logado. */
#[Route('/api/me')]
#[IsGranted('ROLE_USER')]
final class MeController extends AbstractController
{
    private const GRUPOS = ['groups' => ['usuario:publico', 'usuario:privado']];

    public function __construct(private readonly CrudService $crud)
    {
    }

    #[Route('', name: 'api_me', methods: ['GET'])]
    public function me(#[CurrentUser] Usuario $usuario): JsonResponse
    {
        return $this->json($usuario, context: self::GRUPOS);
    }

    /** PATCH /api/me  { "nome"?, "email"?, "telefone"?, "bio"? (só artista) } */
    #[Route('', name: 'api_me_editar', methods: ['PUT', 'PATCH'])]
    public function editar(#[CurrentUser] Usuario $usuario, Request $request): JsonResponse
    {
        $dados = $request->getPayload()->all();

        $this->crud->preencher($usuario, $dados, ['usuario:editar']);
        if (null !== $perfil = $usuario->getPerfilArtista()) {
            $this->crud->preencher($perfil, $dados, ['artista:editar']);
            $this->crud->salvar($usuario, $perfil);
        } else {
            $this->crud->salvar($usuario);
        }

        return $this->json($usuario, context: self::GRUPOS);
    }

    /** POST /api/me/foto  (multipart/form-data, campo "foto") */
    #[Route('/foto', name: 'api_me_foto', methods: ['POST'])]
    public function foto(#[CurrentUser] Usuario $usuario, Request $request, UploadImagem $upload): JsonResponse
    {
        $novoCaminho = $upload->salvar($request->files->get('foto'), 'perfis');
        $upload->remover($usuario->getFotoPerfil());
        $usuario->setFotoPerfil($novoCaminho);
        $this->crud->salvar($usuario);

        return $this->json($usuario, context: self::GRUPOS);
    }

    /**
     * POST /api/me/artista  { "documento": "CPF ou CNPJ", "bio"? }
     * Visitante completa o perfil e vira artista. Devolve um token novo com ROLE_ARTISTA.
     */
    #[Route('/artista', name: 'api_me_tornar_artista', methods: ['POST'])]
    public function tornarArtista(
        #[CurrentUser] Usuario $usuario,
        #[MapRequestPayload] TornarArtistaDto $dto,
        JWTTokenManagerInterface $jwt,
    ): JsonResponse {
        if ($usuario->isArtista()) {
            throw new ConflictHttpException('Você já é artista.');
        }

        $perfil = (new PerfilArtista($usuario, $dto->documento))->setBio($dto->bio);
        $this->crud->salvar($perfil);

        return $this->json([
            'token' => $jwt->create($usuario),
            'usuario' => $usuario,
        ], Response::HTTP_CREATED, [], self::GRUPOS);
    }

    /** GET /api/me/estatisticas — seguidores, curtidas e vendas do artista logado. */
    #[Route('/estatisticas', name: 'api_me_estatisticas', methods: ['GET'])]
    #[IsGranted('ROLE_ARTISTA', message: 'Apenas artistas têm estatísticas.')]
    public function estatisticas(#[CurrentUser] Usuario $usuario, ObraRepository $obras): JsonResponse
    {
        $perfil = $usuario->getPerfilArtista();

        return $this->json([
            'totalSeguidores' => $perfil->getTotalSeguidores(),
            ...$obras->estatisticasDoArtista($perfil),
        ]);
    }

    /** GET /api/me/obras — todas as obras do artista logado (inclusive vendidas há muito tempo). */
    #[Route('/obras', name: 'api_me_obras', methods: ['GET'])]
    #[IsGranted('ROLE_ARTISTA', message: 'Apenas artistas têm obras.')]
    public function minhasObras(#[CurrentUser] Usuario $usuario, ObraRepository $obras): JsonResponse
    {
        return $this->json(
            $obras->doArtista($usuario->getPerfilArtista()),
            context: ['groups' => ['obra:lista', 'obra:detalhe']],
        );
    }

    /** GET /api/me/seguindo — artistas que o usuário segue. */
    #[Route('/seguindo', name: 'api_me_seguindo', methods: ['GET'])]
    public function seguindo(#[CurrentUser] Usuario $usuario): JsonResponse
    {
        return $this->json(
            $usuario->getArtistasSeguidos()->getValues(),
            context: ['groups' => ['artista:publico']],
        );
    }
}
