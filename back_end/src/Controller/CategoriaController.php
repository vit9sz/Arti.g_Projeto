<?php

namespace App\Controller;

use App\Repository\CategoriaRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class CategoriaController extends AbstractController
{
    #[Route('/api/categorias', name: 'api_categorias', methods: ['GET'])]
    public function listar(CategoriaRepository $categorias): JsonResponse
    {
        return $this->json($categorias->findBy([], ['nome' => 'ASC']), context: ['groups' => ['categoria:lista']]);
    }
}
