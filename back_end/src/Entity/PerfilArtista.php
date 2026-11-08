<?php

namespace App\Entity;

use App\Repository\PerfilArtistaRepository;
use App\Validator\CpfCnpj;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Dados que só um artista tem. Relação 1:1 com Usuario.
 */
#[ORM\Entity(repositoryClass: PerfilArtistaRepository::class)]
#[ORM\Table(name: 'perfil_artista')]
#[UniqueEntity(fields: ['documento'], message: 'Este CPF/CNPJ já está cadastrado.')]
class PerfilArtista
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['artista:publico', 'usuario:privado', 'obra:lista', 'obra:detalhe'])]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'perfilArtista', targetEntity: Usuario::class)]
    #[ORM\JoinColumn(nullable: false, unique: true)]
    private Usuario $usuario;

    /** CPF (11 dígitos) ou CNPJ (14 dígitos), só números. Nunca é serializado. */
    #[ORM\Column(length: 14, unique: true)]
    #[Assert\NotBlank(message: 'Informe o CPF ou CNPJ.')]
    #[CpfCnpj]
    private string $documento = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 2000)]
    #[Groups(['artista:publico', 'usuario:privado', 'artista:editar'])]
    private ?string $bio = null;

    /** @var Collection<int, Obra> 1:N */
    #[ORM\OneToMany(targetEntity: Obra::class, mappedBy: 'artista', fetch: 'EXTRA_LAZY')]
    private Collection $obras;

    /** @var Collection<int, Usuario> N:N (lado inverso da tabela "seguidor") */
    #[ORM\ManyToMany(targetEntity: Usuario::class, mappedBy: 'artistasSeguidos', fetch: 'EXTRA_LAZY')]
    private Collection $seguidores;

    public function __construct(Usuario $usuario, string $documento)
    {
        $this->usuario = $usuario;
        $this->setDocumento($documento);
        $usuario->setPerfilArtista($this);
        $this->obras = new ArrayCollection();
        $this->seguidores = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsuario(): Usuario
    {
        return $this->usuario;
    }

    #[Groups(['artista:publico', 'obra:lista', 'obra:detalhe'])]
    public function getNome(): string
    {
        return $this->usuario->getNome();
    }

    #[Groups(['artista:publico', 'obra:lista', 'obra:detalhe'])]
    public function getFotoPerfil(): ?string
    {
        return $this->usuario->getFotoPerfil();
    }

    public function getDocumento(): string
    {
        return $this->documento;
    }

    public function setDocumento(string $documento): static
    {
        $this->documento = preg_replace('/\D/', '', $documento) ?? '';

        return $this;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(?string $bio): static
    {
        $this->bio = $bio;

        return $this;
    }

    /** @return Collection<int, Obra> */
    public function getObras(): Collection
    {
        return $this->obras;
    }

    /** @return Collection<int, Usuario> */
    public function getSeguidores(): Collection
    {
        return $this->seguidores;
    }

    #[Groups(['artista:publico'])]
    #[SerializedName('totalSeguidores')]
    public function getTotalSeguidores(): int
    {
        return $this->seguidores->count();
    }
}
