<?php

namespace App\DataFixtures;

use App\Entity\Categoria;
use App\Entity\Comentario;
use App\Entity\Obra;
use App\Entity\PerfilArtista;
use App\Entity\StatusObra;
use App\Entity\Usuario;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Dados de exemplo para desenvolver o front.
 * Rode: php bin/console doctrine:fixtures:load
 *
 * Logins (senha de todos: senha123):
 *   visitante@artig.com  (visitante)
 *   ana@artig.com        (artista)
 *   bruno@artig.com      (artista)
 */
class AppFixtures extends Fixture
{
    public function __construct(private readonly UserPasswordHasherInterface $hasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $categorias = [];
        foreach (['Pintura', 'Fotografia', 'Ilustração', 'Escultura', 'Arte Digital', 'Gravura'] as $nome) {
            $categorias[$nome] = new Categoria($nome);
            $manager->persist($categorias[$nome]);
        }

        $visitante = $this->usuario('Carla Visitante', 'visitante@artig.com', '(11) 98888-0001');
        $ana = $this->usuario('Ana Souza', 'ana@artig.com', '(11) 98888-0002');
        $bruno = $this->usuario('Bruno Lima', 'bruno@artig.com', '(19) 98888-0003');
        foreach ([$visitante, $ana, $bruno] as $u) {
            $manager->persist($u);
        }

        // CPF e CNPJ de teste (válidos pelos dígitos verificadores, não pertencem a ninguém)
        $perfilAna = (new PerfilArtista($ana, '529.982.247-25'))->setBio('Pintora de aquarelas e paisagens urbanas.');
        $perfilBruno = (new PerfilArtista($bruno, '11.222.333/0001-81'))->setBio('Fotógrafo e artista digital.');
        $manager->persist($perfilAna);
        $manager->persist($perfilBruno);

        $dados = [
            [$perfilAna, 'Chuva na Paulista', 'Aquarela sobre papel 300g.', 850, ['Pintura'], 900],
            [$perfilAna, 'Ipê Amarelo', 'Aquarela, 30x40 cm.', 620, ['Pintura', 'Ilustração'], 700],
            [$perfilAna, 'Beco do Batman', 'Guache e nanquim.', 1200, ['Pintura'], 1000],
            [$perfilAna, 'Gato no Telhado', 'Ilustração em nanquim.', 300, ['Ilustração'], 600],
            [$perfilBruno, 'Neblina na Serra', 'Fotografia impressa em fine art.', 450, ['Fotografia'], 800],
            [$perfilBruno, 'Cidade Neon', 'Arte digital, impressão em canvas.', 980, ['Arte Digital'], 1100],
            [$perfilBruno, 'Mar de Morros', 'Fotografia panorâmica.', 520, ['Fotografia'], 500],
            [$perfilBruno, 'Retrato em Xilo', 'Xilogravura, tiragem 3/10.', 380, ['Gravura'], 750],
        ];

        $obras = [];
        foreach ($dados as $i => [$artista, $titulo, $descricao, $valor, $cats, $altura]) {
            $obra = (new Obra($artista))
                ->setTitulo($titulo)
                ->setDescricao($descricao)
                ->setValor($valor)
                // Imagens de exemplo com alturas variadas para testar o layout estilo Pinterest
                ->setImagem(sprintf('https://picsum.photos/seed/artig%d/600/%d', $i + 1, $altura))
                ->definirCategorias(array_map(fn (string $c) => $categorias[$c], $cats));
            $manager->persist($obra);
            $obras[] = $obra;
        }

        $obras[6]->setStatus(StatusObra::Vendida); // uma vendida para testar o "esgotada"

        $obras[0]->curtir($visitante);
        $obras[0]->curtir($bruno);
        $obras[4]->curtir($visitante);
        $visitante->seguir($perfilAna);
        $bruno->seguir($perfilAna);

        $comentario = (new Comentario($obras[0], $visitante))->setTexto('Que cores lindas! Ainda está disponível?');
        $resposta = (new Comentario($obras[0], $ana, $comentario))->setTexto('Obrigada, Carla! Está sim 😊');
        $manager->persist($comentario);
        $manager->persist($resposta);

        $manager->flush();
    }

    private function usuario(string $nome, string $email, string $telefone): Usuario
    {
        $usuario = (new Usuario())->setNome($nome)->setEmail($email)->setTelefone($telefone);

        return $usuario->setSenha($this->hasher->hashPassword($usuario, 'senha123'));
    }
}
