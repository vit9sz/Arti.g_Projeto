<?php

namespace App\Entity;

use App\Repository\UsuarioRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Qualquer pessoa cadastrada (visitante/comprador).
 *
 * Um usuário vira artista quando ganha um PerfilArtista (relação 1:1).
 * Usamos composição em vez de herança porque a regra de negócio diz que
 * "um visitante pode virar artista depois" — com herança do Doctrine o
 * objeto não consegue trocar de classe depois de criado.
 */
#[ORM\Entity(repositoryClass: UsuarioRepository::class)]
#[ORM\Table(name: 'usuario')]
#[UniqueEntity(fields: ['email'], message: 'Este e-mail já está cadastrado.')]
class Usuario implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['usuario:publico', 'usuario:privado'])]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank(message: 'Informe o nome.')]
    #[Assert\Length(max: 120)]
    #[Groups(['usuario:publico', 'usuario:privado', 'usuario:editar'])]
    private string $nome = '';

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank(message: 'Informe o e-mail.')]
    #[Assert\Email(message: 'E-mail inválido.')]
    #[Groups(['usuario:privado', 'usuario:editar'])]
    private string $email = '';

    /** Hash da senha. Nunca entra em nenhum grupo de serialização. */
    #[ORM\Column]
    private string $senha = '';

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: 'Informe o telefone.')]
    #[Assert\Regex(pattern: '/^\+?[\d\s().-]{10,20}$/', message: 'Telefone inválido.')]
    #[Groups(['usuario:privado', 'usuario:editar'])]
    private string $telefone = '';

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['usuario:publico', 'usuario:privado'])]
    private ?string $fotoPerfil = null;

    /** @var list<string> papéis extras (ex.: ROLE_ADMIN). ROLE_USER/ROLE_ARTISTA são calculados. */
    #[ORM\Column(type: Types::JSON)]
    private array $roles = [];

    #[ORM\Column]
    #[Groups(['usuario:privado'])]
    private \DateTimeImmutable $criadoEm;

    #[ORM\OneToOne(mappedBy: 'usuario', targetEntity: PerfilArtista::class)]
    #[Groups(['usuario:privado'])]
    private ?PerfilArtista $perfilArtista = null;

    /**
     * N:N — artistas que este usuário segue (tabela "seguidor").
     *
     * @var Collection<int, PerfilArtista>
     */
    #[ORM\ManyToMany(targetEntity: PerfilArtista::class, inversedBy: 'seguidores', fetch: 'EXTRA_LAZY')]
    #[ORM\JoinTable(name: 'seguidor')]
    #[ORM\JoinColumn(name: 'usuario_id', referencedColumnName: 'id')]
    #[ORM\InverseJoinColumn(name: 'perfil_artista_id', referencedColumnName: 'id')]
    private Collection $artistasSeguidos;

    /**
     * N:N — obras que este usuário curtiu (tabela "curtida").
     *
     * @var Collection<int, Obra>
     */
    #[ORM\ManyToMany(targetEntity: Obra::class, mappedBy: 'curtidas', fetch: 'EXTRA_LAZY')]
    private Collection $obrasCurtidas;

    public function __construct()
    {
        $this->criadoEm = new \DateTimeImmutable();
        $this->artistasSeguidos = new ArrayCollection();
        $this->obrasCurtidas = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNome(): string
    {
        return $this->nome;
    }

    public function setNome(string $nome): static
    {
        $this->nome = trim($nome);

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = mb_strtolower(trim($email));

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getPassword(): string
    {
        return $this->senha;
    }

    public function setSenha(string $senhaHash): static
    {
        $this->senha = $senhaHash;

        return $this;
    }

    public function getTelefone(): string
    {
        return $this->telefone;
    }

    public function setTelefone(string $telefone): static
    {
        $this->telefone = trim($telefone);

        return $this;
    }

    public function getFotoPerfil(): ?string
    {
        return $this->fotoPerfil;
    }

    public function setFotoPerfil(?string $fotoPerfil): static
    {
        $this->fotoPerfil = $fotoPerfil;

        return $this;
    }

    #[Groups(['usuario:privado'])]
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';
        if (null !== $this->perfilArtista) {
            $roles[] = 'ROLE_ARTISTA';
        }

        return array_values(array_unique($roles));
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    #[Groups(['usuario:privado'])]
    #[SerializedName('ehArtista')]
    public function isArtista(): bool
    {
        return null !== $this->perfilArtista;
    }

    public function getCriadoEm(): \DateTimeImmutable
    {
        return $this->criadoEm;
    }

    public function getPerfilArtista(): ?PerfilArtista
    {
        return $this->perfilArtista;
    }

    public function setPerfilArtista(PerfilArtista $perfilArtista): static
    {
        $this->perfilArtista = $perfilArtista;

        return $this;
    }

    /** @return Collection<int, PerfilArtista> */
    public function getArtistasSeguidos(): Collection
    {
        return $this->artistasSeguidos;
    }

    public function segue(PerfilArtista $artista): bool
    {
        return $this->artistasSeguidos->contains($artista);
    }

    public function seguir(PerfilArtista $artista): static
    {
        if (!$this->artistasSeguidos->contains($artista)) {
            $this->artistasSeguidos->add($artista);
            $artista->getSeguidores()->add($this);
        }

        return $this;
    }

    public function deixarDeSeguir(PerfilArtista $artista): static
    {
        $this->artistasSeguidos->removeElement($artista);
        $artista->getSeguidores()->removeElement($this);

        return $this;
    }

    /** @return Collection<int, Obra> */
    public function getObrasCurtidas(): Collection
    {
        return $this->obrasCurtidas;
    }

    public function eraseCredentials(): void
    {
        // Não guardamos senha em texto puro na entidade, nada a limpar.
    }
}
