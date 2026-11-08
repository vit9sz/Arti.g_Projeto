<?php

namespace App\Controller;

use App\Dto\FiltroPaginacao;
use App\Entity\PerfilArtista;
use App\Entity\Usuario;
use App\Repository\ObraRepository;
use App\Repository\PerfilArtistaRepository;
use App\Service\CrudService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

#[Route('/api/artistas')]
final class ArtistaController extends AbstractController
{
    private const GRUPOS = ['groups' => ['artista:publico']];

    public function __construct(private readonly CrudService $crud)
    {
    }

    /** GET /api/artistas?busca=&pagina=1&porPagina=20 */
    #[Route('', name: 'api_artistas_lista', methods: ['GET'])]
    public function listar(
        PerfilArtistaRepository $artistas,
        #[MapQueryString] FiltroPaginacao $filtro = new FiltroPaginacao(),
    ): JsonResponse {
        $paginador = $artistas->buscar($filtro->busca, $filtro->pagina, $filtro->porPagina);

        return $this->json([
            'itens' => iterator_to_array($paginador, false),
            'total' => count($paginador),
            'pagina' => $filtro->pagina,
            'porPagina' => $filtro->porPagina,
        ], context: self::GRUPOS);
    }

    /** GET /api/artistas/{id} — perfil público (+ "seguidoPorMim" se estiver logado). */
    #[Route('/{id}', name: 'api_artistas_detalhe', requirements: ['id' => Requirement::DIGITS], methods: ['GET'])]
    public function detalhe(int $id, NormalizerInterface $normalizer): JsonResponse
    {
        $artista = $this->crud->buscarOu404(PerfilArtista::class, $id);
        $usuario = $this->getUser();

        $dados = $normalizer->normalize($artista, 'json', self::GRUPOS);
        $dados['seguidoPorMim'] = $usuario instanceof Usuario && $usuario->segue($artista);

        return $this->json($dados);
    }

    /** GET /api/artistas/{id}/obras — todas as obras do artista (inclusive vendidas). */
    #[Route('/{id}/obras', name: 'api_artistas_obras', requirements: ['id' => Requirement::DIGITS], methods: ['GET'])]
    public function obras(int $id, ObraRepository $obras): JsonResponse
    {
        $artista = $this->crud->buscarOu404(PerfilArtista::class, $id);

        return $this->json($obras->doArtista($artista), context: ['groups' => ['obra:lista']]);
    }

    /** POST /api/artistas/{id}/seguidores — seguir. Ninguém segue a si mesmo. */
    #[Route('/{id}/seguidores', name: 'api_artistas_seguir', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    #[IsGranted('ROLE_USER', message: 'Faça login para seguir artistas.')]
    public function seguir(int $id, #[CurrentUser] Usuario $usuario, EntityManagerInterface $em): JsonResponse
    {
        $artista = $this->crud->buscarOu404(PerfilArtista::class, $id);
        if ($artista->getUsuario() === $usuario) {
            throw new ConflictHttpException('Você não pode seguir a si mesmo.');
        }

        $usuario->seguir($artista);
        $em->flush();

        return $this->json(['seguidoPorMim' => true, 'totalSeguidores' => $artista->getTotalSeguidores()]);
    }

    /** DELETE /api/artistas/{id}/seguidores — deixar de seguir. */
    #[Route('/{id}/seguidores', name: 'api_artistas_deixar_de_seguir', requirements: ['id' => Requirement::DIGITS], methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function deixarDeSeguir(int $id, #[CurrentUser] Usuario $usuario, EntityManagerInterface $em): JsonResponse
    {
        $artista = $this->crud->buscarOu404(PerfilArtista::class, $id);
        $usuario->deixarDeSeguir($artista);
        $em->flush();

        return $this->json(['seguidoPorMim' => false, 'totalSeguidores' => $artista->getTotalSeguidores()]);
    }
}
