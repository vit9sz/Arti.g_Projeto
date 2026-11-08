<?php

namespace App\Controller;

use App\Dto\FiltroPaginacao;
use App\Entity\Obra;
use App\Entity\Usuario;
use App\Repository\CategoriaRepository;
use App\Repository\ObraRepository;
use App\Security\Voter\ObraVoter;
use App\Service\CrudService;
use App\Service\UploadImagem;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

#[Route('/api/obras')]
final class ObraController extends AbstractController
{
    private const GRUPOS_LISTA = ['groups' => ['obra:lista']];
    private const GRUPOS_DETALHE = ['groups' => ['obra:lista', 'obra:detalhe']];

    public function __construct(
        private readonly CrudService $crud,
        private readonly ObraRepository $obras,
        private readonly NormalizerInterface $normalizer,
    ) {
    }

    /** GET /api/obras?busca=&categoria=&artista=&pagina=1&porPagina=20 — o feed. */
    #[Route('', name: 'api_obras_feed', methods: ['GET'])]
    public function feed(#[MapQueryString] FiltroPaginacao $filtro = new FiltroPaginacao()): JsonResponse
    {
        $paginador = $this->obras->feed($filtro->busca, $filtro->categoria, $filtro->artista, $filtro->pagina, $filtro->porPagina);
        $lista = iterator_to_array($paginador, false);

        return $this->json([
            'itens' => $this->normalizarComCurtida($lista, self::GRUPOS_LISTA),
            'total' => count($paginador),
            'pagina' => $filtro->pagina,
            'porPagina' => $filtro->porPagina,
        ]);
    }

    #[Route('/{id}', name: 'api_obras_detalhe', requirements: ['id' => Requirement::DIGITS], methods: ['GET'])]
    public function detalhe(int $id): JsonResponse
    {
        $obra = $this->crud->buscarOu404(Obra::class, $id);

        return $this->json($this->normalizarComCurtida([$obra], self::GRUPOS_DETALHE)[0]);
    }

    /**
     * POST /api/obras  (multipart/form-data)
     * Campos: titulo, descricao?, valor, categorias[] (ids), imagem (arquivo)
     */
    #[Route('', name: 'api_obras_criar', methods: ['POST'])]
    #[IsGranted('ROLE_ARTISTA', message: 'Apenas artistas podem publicar obras.')]
    public function criar(
        #[CurrentUser] Usuario $usuario,
        Request $request,
        UploadImagem $upload,
        CategoriaRepository $categorias,
    ): JsonResponse {
        $obra = new Obra($usuario->getPerfilArtista());
        $this->crud->preencher($obra, $request->request->all(), ['obra:escrita']);
        $obra->definirCategorias($categorias->findBy(['id' => $this->idsCategorias($request)]));

        $obra->setImagem($upload->salvar($request->files->get('imagem'), 'obras'));
        try {
            $this->crud->salvar($obra);
        } catch (\Throwable $erro) {
            $upload->remover($obra->getImagem()); // não deixa arquivo órfão se a validação falhar
            throw $erro;
        }

        return $this->json($obra, Response::HTTP_CREATED, [], self::GRUPOS_DETALHE);
    }

    /**
     * PATCH /api/obras/{id}  (JSON)
     * { "titulo"?, "descricao"?, "valor"?, "status"?: "disponivel"|"vendida", "categorias"?: [1, 2] }
     */
    #[Route('/{id}', name: 'api_obras_editar', requirements: ['id' => Requirement::DIGITS], methods: ['PUT', 'PATCH'])]
    public function editar(int $id, Request $request, CategoriaRepository $categorias): JsonResponse
    {
        $obra = $this->crud->buscarOu404(Obra::class, $id);
        $this->denyAccessUnlessGranted(ObraVoter::EDITAR, $obra, 'Você só pode editar as suas obras.');

        $this->crud->preencher($obra, $request->getPayload()->all(), ['obra:escrita', 'obra:status']);
        if (null !== $ids = $this->idsCategorias($request)) {
            $obra->definirCategorias($categorias->findBy(['id' => $ids]));
        }
        $this->crud->salvar($obra);

        return $this->json($obra, context: self::GRUPOS_DETALHE);
    }

    /** POST /api/obras/{id}/imagem  (multipart, campo "imagem") — troca a foto da obra. */
    #[Route('/{id}/imagem', name: 'api_obras_imagem', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function trocarImagem(int $id, Request $request, UploadImagem $upload): JsonResponse
    {
        $obra = $this->crud->buscarOu404(Obra::class, $id);
        $this->denyAccessUnlessGranted(ObraVoter::EDITAR, $obra, 'Você só pode editar as suas obras.');

        $antiga = $obra->getImagem();
        $obra->setImagem($upload->salvar($request->files->get('imagem'), 'obras'));
        $this->crud->salvar($obra);
        $upload->remover($antiga);

        return $this->json($obra, context: self::GRUPOS_DETALHE);
    }

    /** DELETE /api/obras/{id} — exclusão lógica (comentários e curtidas ficam no banco). */
    #[Route('/{id}', name: 'api_obras_excluir', requirements: ['id' => Requirement::DIGITS], methods: ['DELETE'])]
    public function excluir(int $id): Response
    {
        $obra = $this->crud->buscarOu404(Obra::class, $id);
        $this->denyAccessUnlessGranted(ObraVoter::EXCLUIR, $obra, 'Você só pode excluir as suas obras.');

        $this->crud->excluir($obra);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /** POST /api/obras/{id}/curtida — o próprio artista pode curtir a sua obra. */
    #[Route('/{id}/curtida', name: 'api_obras_curtir', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function curtir(int $id, #[CurrentUser] Usuario $usuario, EntityManagerInterface $em): JsonResponse
    {
        $obra = $this->crud->buscarOu404(Obra::class, $id);
        if ($obra->isVendida()) {
            throw new ConflictHttpException('Obras vendidas não podem receber curtidas.');
        }

        $obra->curtir($usuario);
        $em->flush();

        return $this->json(['curtidaPorMim' => true, 'totalCurtidas' => $obra->getTotalCurtidas()]);
    }

    #[Route('/{id}/curtida', name: 'api_obras_descurtir', requirements: ['id' => Requirement::DIGITS], methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function descurtir(int $id, #[CurrentUser] Usuario $usuario, EntityManagerInterface $em): JsonResponse
    {
        $obra = $this->crud->buscarOu404(Obra::class, $id);
        $obra->descurtir($usuario);
        $em->flush();

        return $this->json(['curtidaPorMim' => false, 'totalCurtidas' => $obra->getTotalCurtidas()]);
    }

    /**
     * GET /api/obras/{id}/comprar → { "url": "https://wa.me/55...?text=..." }
     * A compra é combinada no WhatsApp do artista. Só usuários cadastrados.
     */
    #[Route('/{id}/comprar', name: 'api_obras_comprar', requirements: ['id' => Requirement::DIGITS], methods: ['GET'])]
    #[IsGranted('ROLE_USER', message: 'Faça login para comprar.')]
    public function comprar(int $id, #[CurrentUser] Usuario $usuario): JsonResponse
    {
        $obra = $this->crud->buscarOu404(Obra::class, $id);
        if ($obra->isVendida()) {
            throw new ConflictHttpException('Esta obra já foi vendida.');
        }
        $artista = $obra->getArtista()->getUsuario();
        if ($artista === $usuario) {
            throw new ConflictHttpException('Você não pode comprar a sua própria obra.');
        }

        $telefone = preg_replace('/\D/', '', $artista->getTelefone()) ?? '';
        if (strlen($telefone) <= 11) {
            $telefone = '55'.$telefone; // DDI do Brasil
        }

        $mensagem = sprintf(
            'Olá, %s! Vi a obra "%s" (R$ %s) no Arti.g e tenho interesse em comprar.',
            $artista->getNome(),
            $obra->getTitulo(),
            number_format((float) $obra->getValor(), 2, ',', '.'),
        );

        return $this->json(['url' => 'https://wa.me/'.$telefone.'?text='.rawurlencode($mensagem)]);
    }

    /**
     * Normaliza as obras e acrescenta "curtidaPorMim" (true/false) para o usuário logado.
     *
     * @param list<Obra> $lista
     *
     * @return list<array<string, mixed>>
     */
    private function normalizarComCurtida(array $lista, array $contexto): array
    {
        $usuario = $this->getUser();
        $curtidas = $usuario instanceof Usuario
            ? $this->obras->idsCurtidosPor($usuario, array_map(fn (Obra $o) => $o->getId(), $lista))
            : [];

        return array_map(function (Obra $obra) use ($contexto, $curtidas) {
            $dados = $this->normalizer->normalize($obra, 'json', $contexto);
            $dados['curtidaPorMim'] = in_array($obra->getId(), $curtidas, true);

            return $dados;
        }, $lista);
    }

    /** Aceita categorias como [1,2] (JSON), categorias[]=1 (multipart) ou "1,2". Null = não enviado. */
    private function idsCategorias(Request $request): ?array
    {
        $payload = $request->getPayload();
        if (!$payload->has('categorias')) {
            return null;
        }

        $valor = $payload->all()['categorias'];
        $ids = is_array($valor) ? $valor : explode(',', (string) $valor);

        return array_values(array_filter(array_map('intval', $ids)));
    }
}
