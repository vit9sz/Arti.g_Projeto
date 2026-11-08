<?php

namespace App\Repository;

use App\Entity\Obra;
use App\Entity\PerfilArtista;
use App\Entity\StatusObra;
use App\Entity\Usuario;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @extends ServiceEntityRepository<Obra>
 */
class ObraRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        #[Autowire(param: 'app.dias_obra_vendida_no_feed')]
        private readonly int $diasVendidaNoFeed,
    ) {
        parent::__construct($registry, Obra::class);
    }

    /**
     * Feed público. Regra: obra vendida continua aparecendo (como esgotada)
     * por alguns dias depois da venda e depois sai do feed.
     *
     * @return Paginator<Obra>
     */
    public function feed(
        ?string $busca,
        ?int $categoriaId,
        ?int $artistaId,
        int $pagina,
        int $porPagina,
    ): Paginator {
        $limiteVendida = new \DateTimeImmutable(sprintf('-%d days', $this->diasVendidaNoFeed));

        $qb = $this->createQueryBuilder('o')
            ->join('o.artista', 'a')
            ->join('a.usuario', 'u')
            ->addSelect('a', 'u')
            ->andWhere('o.excluidaEm IS NULL')
            ->andWhere('(o.status = :disponivel OR o.vendidaEm >= :limiteVendida)')
            ->setParameter('disponivel', StatusObra::Disponivel)
            ->setParameter('limiteVendida', $limiteVendida)
            ->orderBy('o.criadoEm', 'DESC');

        if (null !== $busca && '' !== trim($busca)) {
            $qb->andWhere('(LOWER(o.titulo) LIKE :busca OR LOWER(o.descricao) LIKE :busca OR LOWER(u.nome) LIKE :busca)')
                ->setParameter('busca', '%'.mb_strtolower(trim($busca)).'%');
        }

        if (null !== $categoriaId) {
            $qb->andWhere(':categoria MEMBER OF o.categorias')
                ->setParameter('categoria', $categoriaId);
        }

        if (null !== $artistaId) {
            $qb->andWhere('a.id = :artista')->setParameter('artista', $artistaId);
        }

        $qb->setFirstResult(($pagina - 1) * $porPagina)->setMaxResults($porPagina);

        return new Paginator($qb);
    }

    /** @return list<Obra> todas as obras não excluídas do artista (para o perfil dele) */
    public function doArtista(PerfilArtista $artista): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.artista = :artista')
            ->andWhere('o.excluidaEm IS NULL')
            ->setParameter('artista', $artista)
            ->orderBy('o.criadoEm', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Dentre as obras informadas, quais o usuário curtiu (uma query só).
     *
     * @param list<int> $idsObras
     *
     * @return list<int>
     */
    public function idsCurtidosPor(Usuario $usuario, array $idsObras): array
    {
        if ([] === $idsObras) {
            return [];
        }

        $linhas = $this->createQueryBuilder('o')
            ->select('o.id')
            ->join('o.curtidas', 'u')
            ->andWhere('u = :usuario')
            ->andWhere('o.id IN (:ids)')
            ->setParameter('usuario', $usuario)
            ->setParameter('ids', $idsObras)
            ->getQuery()
            ->getSingleColumnResult();

        return array_map('intval', $linhas);
    }

    /** @return array{totalObras: int, obrasVendidas: int, totalCurtidas: int} */
    public function estatisticasDoArtista(PerfilArtista $artista): array
    {
        $obras = $this->createQueryBuilder('o')
            ->select('COUNT(o.id) AS total')
            ->addSelect('SUM(CASE WHEN o.status = :vendida THEN 1 ELSE 0 END) AS vendidas')
            ->andWhere('o.artista = :artista')
            ->andWhere('o.excluidaEm IS NULL')
            ->setParameter('artista', $artista)
            ->setParameter('vendida', StatusObra::Vendida)
            ->getQuery()
            ->getSingleResult();

        $curtidas = $this->createQueryBuilder('o')
            ->select('COUNT(u.id)')
            ->join('o.curtidas', 'u')
            ->andWhere('o.artista = :artista')
            ->andWhere('o.excluidaEm IS NULL')
            ->setParameter('artista', $artista)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'totalObras' => (int) $obras['total'],
            'obrasVendidas' => (int) $obras['vendidas'],
            'totalCurtidas' => (int) $curtidas,
        ];
    }
}
