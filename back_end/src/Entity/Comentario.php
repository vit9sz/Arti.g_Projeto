<?php

namespace App\Entity;

use App\Repository\ComentarioRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Feedback de um usuário numa obra. O artista "comenta de volta"
 * criando um comentário com respostaA apontando para o original.
 */
#[ORM\Entity(repositoryClass: ComentarioRepository::class)]
#[ORM\Table(name: 'comentario')]
class Comentario implements ExclusaoLogicaInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['comentario:lista'])]
    private ?int $id = null;

    /** N:1 */
    #[ORM\ManyToOne(targetEntity: Obra::class, inversedBy: 'comentarios')]
    #[ORM\JoinColumn(nullable: false)]
    private Obra $obra;

    /** N:1 */
    #[ORM\ManyToOne(targetEntity: Usuario::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['comentario:lista'])]
    private Usuario $autor;

    /** N:1 com ela mesma — resposta a outro comentário (opcional). */
    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Comentario $respostaA = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'O comentário não pode ficar vazio.')]
    #[Assert\Length(max: 5000, maxMessage: 'O comentário pode ter no máximo {{ limit }} caracteres.')]
    #[Groups(['comentario:lista', 'comentario:escrita'])]
    private string $texto = '';

    #[ORM\Column]
    #[Groups(['comentario:lista'])]
    private \DateTimeImmutable $criadoEm;

    #[ORM\Column(nullable: true)]
    #[Groups(['comentario:lista'])]
    private ?\DateTimeImmutable $editadoEm = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $excluidoEm = null;

    public function __construct(Obra $obra, Usuario $autor, ?Comentario $respostaA = null)
    {
        $this->obra = $obra;
        $this->autor = $autor;
        $this->respostaA = $respostaA;
        $this->criadoEm = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getObra(): Obra
    {
        return $this->obra;
    }

    public function getAutor(): Usuario
    {
        return $this->autor;
    }

    public function getRespostaA(): ?Comentario
    {
        return $this->respostaA;
    }

    #[Groups(['comentario:lista'])]
    #[SerializedName('respostaAId')]
    public function getRespostaAId(): ?int
    {
        return $this->respostaA?->getId();
    }

    #[Groups(['comentario:lista'])]
    #[SerializedName('autorEhArtistaDaObra')]
    public function isAutorArtistaDaObra(): bool
    {
        return $this->obra->getArtista()->getUsuario() === $this->autor;
    }

    public function getTexto(): string
    {
        return $this->texto;
    }

    public function setTexto(string $texto): static
    {
        if (null !== $this->id && trim($texto) !== $this->texto) {
            $this->editadoEm = new \DateTimeImmutable();
        }
        $this->texto = trim($texto);

        return $this;
    }

    public function getCriadoEm(): \DateTimeImmutable
    {
        return $this->criadoEm;
    }

    public function marcarComoExcluido(): void
    {
        $this->excluidoEm = new \DateTimeImmutable();
    }

    public function isExcluido(): bool
    {
        return null !== $this->excluidoEm;
    }
}
