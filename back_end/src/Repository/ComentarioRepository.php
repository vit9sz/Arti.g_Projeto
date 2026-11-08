<?php

namespace App\Repository;

use App\Entity\Comentario;
use App\Entity\Obra;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Comentario>
 */
class ComentarioRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Comentario::class);
    }

    /** @return list<Comentario> comentários ativos da obra, do mais antigo para o mais novo */
    public function daObra(Obra $obra): array
    {
        return $this->createQueryBuilder('c')
            ->join('c.autor', 'u')
            ->addSelect('u')
            ->andWhere('c.obra = :obra')
            ->andWhere('c.excluidoEm IS NULL')
            ->setParameter('obra', $obra)
            ->orderBy('c.criadoEm', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
