<?php

namespace App\Controller;

use App\Dto\ComentarioDto;
use App\Entity\Comentario;
use App\Entity\Obra;
use App\Entity\Usuario;
use App\Repository\ComentarioRepository;
use App\Security\Voter\ComentarioVoter;
use App\Service\CrudService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Feedbacks (CRUD de comentários). */
final class ComentarioController extends AbstractController
{
    private const GRUPOS = ['groups' => ['comentario:lista', 'usuario:publico']];

    public function __construct(private readonly CrudService $crud)
    {
    }

    #[Route('/api/obras/{id}/comentarios', name: 'api_comentarios_lista', requirements: ['id' => Requirement::DIGITS], methods: ['GET'])]
    public function listar(int $id, ComentarioRepository $comentarios): JsonResponse
    {
        $obra = $this->crud->buscarOu404(Obra::class, $id);

        return $this->json($comentarios->daObra($obra), context: self::GRUPOS);
    }

    /** POST /api/obras/{id}/comentarios  { "texto": "...", "respostaA"?: idDoComentario } */
    #[Route('/api/obras/{id}/comentarios', name: 'api_comentarios_criar', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    #[IsGranted('ROLE_USER', message: 'Faça login para comentar.')]
    public function criar(int $id, #[CurrentUser] Usuario $usuario, #[MapRequestPayload] ComentarioDto $dto): JsonResponse
    {
        $obra = $this->crud->buscarOu404(Obra::class, $id);

        $respostaA = null;
        if (null !== $dto->respostaA) {
            $respostaA = $this->crud->buscarOu404(Comentario::class, $dto->respostaA);
            if ($respostaA->getObra() !== $obra) {
                throw new UnprocessableEntityHttpException('O comentário respondido é de outra obra.');
            }
        }

        $comentario = (new Comentario($obra, $usuario, $respostaA))->setTexto($dto->texto);
        $this->crud->salvar($comentario);

        return $this->json($comentario, Response::HTTP_CREATED, [], self::GRUPOS);
    }

    /** PATCH /api/comentarios/{id}  { "texto": "..." } — só o autor. */
    #[Route('/api/comentarios/{id}', name: 'api_comentarios_editar', requirements: ['id' => Requirement::DIGITS], methods: ['PUT', 'PATCH'])]
    public function editar(int $id, Request $request): JsonResponse
    {
        $comentario = $this->crud->buscarOu404(Comentario::class, $id);
        $this->denyAccessUnlessGranted(ComentarioVoter::EDITAR, $comentario, 'Você só pode editar os seus comentários.');

        $this->crud->atualizar($comentario, $request->getPayload()->all(), ['comentario:escrita']);

        return $this->json($comentario, context: self::GRUPOS);
    }

    /** DELETE /api/comentarios/{id} — o autor ou o artista dono da obra. */
    #[Route('/api/comentarios/{id}', name: 'api_comentarios_excluir', requirements: ['id' => Requirement::DIGITS], methods: ['DELETE'])]
    public function excluir(int $id): Response
    {
        $comentario = $this->crud->buscarOu404(Comentario::class, $id);
        $this->denyAccessUnlessGranted(ComentarioVoter::EXCLUIR, $comentario, 'Você não pode excluir este comentário.');

        $this->crud->excluir($comentario);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
