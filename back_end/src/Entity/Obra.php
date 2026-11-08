<?php

namespace App\Entity;

use App\Repository\ObraRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ObraRepository::class)]
#[ORM\Table(name: 'obra')]
#[ORM\Index(columns: ['status'])]
#[ORM\HasLifecycleCallbacks]
class Obra implements ExclusaoLogicaInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['obra:lista', 'obra:detalhe'])]
    private ?int $id = null;

    /** N:1 — o artista dono da obra. */
    #[ORM\ManyToOne(targetEntity: PerfilArtista::class, inversedBy: 'obras')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['obra:lista', 'obra:detalhe'])]
    private PerfilArtista $artista;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank(message: 'Informe o título.')]
    #[Assert\Length(max: 150)]
    #[Groups(['obra:lista', 'obra:detalhe', 'obra:escrita'])]
    private string $titulo = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 5000)]
    #[Groups(['obra:detalhe', 'obra:escrita'])]
    private ?string $descricao = null;

    /** Caminho público (/uploads/obras/x.jpg) ou URL completa. */
    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Envie a imagem da obra.')]
    #[Groups(['obra:lista', 'obra:detalhe'])]
    private string $imagem = '';

    /** Decimal como string para não perder centavos. Sem limite: o artista decide. */
    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    #[Assert\NotBlank(message: 'Informe o valor.')]
    #[Assert\PositiveOrZero(message: 'O valor não pode ser negativo.')]
    #[Groups(['obra:lista', 'obra:detalhe', 'obra:escrita'])]
    private string $valor = '0.00';

    #[ORM\Column(length: 20, enumType: StatusObra::class)]
    #[Groups(['obra:lista', 'obra:detalhe', 'obra:status'])]
    private StatusObra $status = StatusObra::Disponivel;

    /** @var Collection<int, Categoria> N:N (tabela "obra_categoria") */
    #[ORM\ManyToMany(targetEntity: Categoria::class)]
    #[ORM\JoinTable(name: 'obra_categoria')]
    #[Groups(['obra:lista', 'obra:detalhe'])]
    private Collection $categorias;

    /** @var Collection<int, Usuario> N:N (tabela "curtida") */
    #[ORM\ManyToMany(targetEntity: Usuario::class, inversedBy: 'obrasCurtidas', fetch: 'EXTRA_LAZY')]
    #[ORM\JoinTable(name: 'curtida')]
    #[ORM\JoinColumn(name: 'obra_id', referencedColumnName: 'id')]
    #[ORM\InverseJoinColumn(name: 'usuario_id', referencedColumnName: 'id')]
    private Collection $curtidas;

    /** @var Collection<int, Comentario> 1:N */
    #[ORM\OneToMany(targetEntity: Comentario::class, mappedBy: 'obra', fetch: 'EXTRA_LAZY')]
    private Collection $comentarios;

    #[ORM\Column]
    #[Groups(['obra:lista', 'obra:detalhe'])]
    private \DateTimeImmutable $criadoEm;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $atualizadoEm = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['obra:detalhe'])]
    private ?\DateTimeImmutable $vendidaEm = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $excluidaEm = null;

    public function __construct(PerfilArtista $artista)
    {
        $this->artista = $artista;
        $this->criadoEm = new \DateTimeImmutable();
        $this->categorias = new ArrayCollection();
        $this->curtidas = new ArrayCollection();
        $this->comentarios = new ArrayCollection();
    }

    #[ORM\PreUpdate]
    public function aoAtualizar(): void
    {
        $this->atualizadoEm = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getArtista(): PerfilArtista
    {
        return $this->artista;
    }

    public function getTitulo(): string
    {
        return $this->titulo;
    }

    public function setTitulo(string $titulo): static
    {
        $this->titulo = trim($titulo);

        return $this;
    }

    public function getDescricao(): ?string
    {
        return $this->descricao;
    }

    public function setDescricao(?string $descricao): static
    {
        $this->descricao = $descricao;

        return $this;
    }

    public function getImagem(): string
    {
        return $this->imagem;
    }

    public function setImagem(string $imagem): static
    {
        $this->imagem = $imagem;

        return $this;
    }

    public function getValor(): string
    {
        return $this->valor;
    }

    /** Aceita número ou string ("150", 150.5, "1500.00"), vindo de JSON ou multipart. */
    public function setValor(string|int|float $valor): static
    {
        $this->valor = number_format((float) str_replace(',', '.', (string) $valor), 2, '.', '');

        return $this;
    }

    public function getStatus(): StatusObra
    {
        return $this->status;
    }

    public function setStatus(StatusObra $status): static
    {
        if ($status === $this->status) {
            return $this;
        }
        $this->status = $status;
        $this->vendidaEm = StatusObra::Vendida === $status ? new \DateTimeImmutable() : null;

        return $this;
    }

    public function isVendida(): bool
    {
        return StatusObra::Vendida === $this->status;
    }

    /** @return Collection<int, Categoria> */
    public function getCategorias(): Collection
    {
        return $this->categorias;
    }

    /** @param iterable<Categoria> $categorias */
    public function definirCategorias(iterable $categorias): static
    {
        $this->categorias->clear();
        foreach ($categorias as $categoria) {
            $this->categorias->add($categoria);
        }

        return $this;
    }

    public function isCurtidaPor(Usuario $usuario): bool
    {
        return $this->curtidas->contains($usuario);
    }

    public function curtir(Usuario $usuario): void
    {
        if (!$this->curtidas->contains($usuario)) {
            $this->curtidas->add($usuario);
            $usuario->getObrasCurtidas()->add($this);
        }
    }

    public function descurtir(Usuario $usuario): void
    {
        $this->curtidas->removeElement($usuario);
        $usuario->getObrasCurtidas()->removeElement($this);
    }

    #[Groups(['obra:lista', 'obra:detalhe'])]
    #[SerializedName('totalCurtidas')]
    public function getTotalCurtidas(): int
    {
        return $this->curtidas->count();
    }

    /** @return Collection<int, Comentario> */
    public function getComentarios(): Collection
    {
        return $this->comentarios;
    }

    public function getCriadoEm(): \DateTimeImmutable
    {
        return $this->criadoEm;
    }

    public function getVendidaEm(): ?\DateTimeImmutable
    {
        return $this->vendidaEm;
    }

    public function marcarComoExcluido(): void
    {
        $this->excluidaEm = new \DateTimeImmutable();
    }

    public function isExcluido(): bool
    {
        return null !== $this->excluidaEm;
    }
}
