<?php

namespace App\Entity;

use App\Repository\CategoriaRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CategoriaRepository::class)]
#[ORM\Table(name: 'categoria')]
#[UniqueEntity(fields: ['nome'], message: 'Já existe uma categoria com esse nome.')]
class Categoria
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['categoria:lista', 'obra:lista', 'obra:detalhe'])]
    private ?int $id = null;

    #[ORM\Column(length: 80, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 80)]
    #[Groups(['categoria:lista', 'obra:lista', 'obra:detalhe'])]
    private string $nome;

    public function __construct(string $nome)
    {
        $this->nome = trim($nome);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNome(): string
    {
        return $this->nome;
    }
}
