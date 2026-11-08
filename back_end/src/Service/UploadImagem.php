<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Valida e salva imagens enviadas em public/uploads/<pasta>.
 * Retorna o caminho público (ex.: /uploads/obras/ab12cd.jpg).
 */
final class UploadImagem
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/public')]
        private readonly string $pastaPublica,
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function salvar(?UploadedFile $arquivo, string $pasta): string
    {
        $violacoes = $this->validator->validate($arquivo, [
            new NotNull(message: 'Envie uma imagem.'),
            new Image(
                maxSize: '8M',
                mimeTypes: ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
                mimeTypesMessage: 'Envie uma imagem JPG, PNG, WEBP ou GIF.',
            ),
        ]);
        if (count($violacoes) > 0) {
            throw new ValidationFailedException($arquivo, $violacoes);
        }

        $nome = bin2hex(random_bytes(12)).'.'.($arquivo->guessExtension() ?? 'jpg');
        $arquivo->move($this->pastaPublica.'/uploads/'.$pasta, $nome);

        return '/uploads/'.$pasta.'/'.$nome;
    }

    /** Remove um arquivo antigo (ignora URLs externas, como as das fixtures). */
    public function remover(?string $caminhoPublico): void
    {
        if (null === $caminhoPublico || !str_starts_with($caminhoPublico, '/uploads/')) {
            return;
        }

        $arquivo = $this->pastaPublica.$caminhoPublico;
        if (is_file($arquivo)) {
            unlink($arquivo);
        }
    }
}
