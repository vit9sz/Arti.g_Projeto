<?php

namespace App\Repository;

use App\Entity\PerfilArtista;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PerfilArtista>
 */
class PerfilArtistaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PerfilArtista::class);
    }

    /** @return Paginator<PerfilArtista> */
    public function buscar(?string $busca, int $pagina, int $porPagina): Paginator
    {
        $qb = $this->createQueryBuilder('a')
            ->join('a.usuario', 'u')
            ->addSelect('u')
            ->orderBy('u.nome', 'ASC');

        if (null !== $busca && '' !== trim($busca)) {
            $qb->andWhere('LOWER(u.nome) LIKE :busca')
                ->setParameter('busca', '%'.mb_strtolower(trim($busca)).'%');
        }

        $qb->setFirstResult(($pagina - 1) * $porPagina)->setMaxResults($porPagina);

        return new Paginator($qb);
    }
}
