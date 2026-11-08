# Arti.g — Back-end (Symfony 8.1)

API REST da galeria de arte. O front (React) conversa com ela via JSON + token JWT.

## 1. Instalar (uma vez só)

Requisitos: PHP 8.4+, Composer, MySQL/MariaDB (XAMPP serve).

```bash
# Pacotes (o Symfony Flex cria as configs que faltam e registra os bundles)
composer require symfony/orm-pack symfony/security-bundle lexik/jwt-authentication-bundle \
    nelmio/cors-bundle symfony/serializer-pack symfony/validator symfony/mime
composer require --dev symfony/maker-bundle doctrine/doctrine-fixtures-bundle
```

> Se o Composer perguntar *"Do you want to include Docker configuration from recipes?"*, responda **n**.
> Se ele avisar que `config/packages/security.yaml` já existe, **mantenha o nosso** (ele já está configurado).

Crie o arquivo **`.env.local`** (não vai para o git) com o seu banco:

```dotenv
# XAMPP padrão: usuário root, sem senha
DATABASE_URL="mysql://root:@127.0.0.1:3306/artig?serverVersion=10.4.32-MariaDB&charset=utf8mb4"
```

Depois:

```bash
php bin/console lexik:jwt:generate-keypair         # chaves que assinam o token
php bin/console doctrine:database:create
php bin/console make:migration                     # gera o SQL das tabelas
php bin/console doctrine:migrations:migrate
php bin/console doctrine:fixtures:load             # dados de exemplo (opcional)
symfony serve -d                                   # ou: php -S localhost:8000 -t public
```

Logins de exemplo (senha `senha123`): `visitante@artig.com`, `ana@artig.com` (artista), `bruno@artig.com` (artista).

## 2. Banco de dados

| Tabela | O que é | Relações |
|---|---|---|
| `usuario` | qualquer pessoa cadastrada | 1:1 `perfil_artista` · 1:N `comentario` |
| `perfil_artista` | dados extras do artista (CPF/CNPJ, bio) | 1:N `obra` |
| `obra` | a arte publicada | N:1 `perfil_artista` · 1:N `comentario` |
| `categoria` | Pintura, Fotografia… | |
| `comentario` | feedback numa obra (pode ser resposta a outro) | N:1 `obra`, N:1 `usuario`, N:1 `comentario` |
| `obra_categoria` | **N:N** obra ↔ categoria | |
| `curtida` | **N:N** usuário ↔ obra | |
| `seguidor` | **N:N** usuário ↔ artista | |

Por que **composição** (Usuario + PerfilArtista) e não herança: a regra diz que um visitante pode virar artista depois.
Com herança no Doctrine, um objeto não muda de classe; com composição basta criar o `PerfilArtista`.

## 3. Regras de negócio implementadas

- **Obra vendida** continua no feed por 7 dias (`app.dias_obra_vendida_no_feed` em `config/services.yaml`), não recebe curtidas e não pode ser comprada.
- **Artista** pode curtir a própria obra, mas não pode seguir a si mesmo nem comprar a própria obra.
- **Sem cascade**: excluir obra/comentário é *exclusão lógica* (`excluidaEm`); curtidas e comentários continuam no banco.
- **Visitante vira artista** via `POST /api/me/artista`.
- **Valor** da obra sem limite (só não pode ser negativo). **Comentário** até 5000 caracteres. **Imagem** JPG/PNG/WEBP/GIF até 8 MB.
- **CPF/CNPJ** validados com dígito verificador e nunca aparecem no JSON. **Senha** nunca aparece no JSON.

## 4. Arquitetura (onde fica cada coisa)

```
src/
  Entity/           tabelas (Doctrine) + grupos de serialização (#[Groups])
  Repository/       consultas (feed com busca/filtro/paginação, estatísticas)
  Controller/       endpoints — finos, só orquestram
  Service/
    CrudService     CRUD genérico: preencher/salvar/excluir qualquer entidade
    UploadImagem    salva imagens em public/uploads/
  Security/Voter/   QUEM pode editar/excluir (dono da obra, autor do comentário)
  Dto/              formato dos JSON de entrada (validados)
  Validator/        validador de CPF/CNPJ
  EventListener/    erros em JSON padronizado; dados do usuário no login
```

O **CrudService** é a ideia do "CRUD que recebe um objeto": ele funciona para qualquer entidade.
Os dois cuidados para ele não virar falha de segurança estão resolvidos assim:
1. **Permissão** → Voters (`ObraVoter`, `ComentarioVoter`) chamados nos controllers.
2. **Campos** → só os campos do grupo de escrita (`obra:escrita`, `usuario:editar`…) são aceitos; o resto do JSON é ignorado.

## 5. Endpoints (para o React)

Base: `http://localhost:8000/api`. Envie `Authorization: Bearer <token>` nas rotas com 🔒.

### Autenticação
| Método | Rota | Corpo | Resposta |
|---|---|---|---|
| POST | `/registro` | `{nome, email, senha, telefone, documento?, bio?}` (com `documento` já vira artista) | `201 {token, usuario}` |
| POST | `/login` | `{email, senha}` | `{token, usuario}` |

### Usuário logado 🔒
| Método | Rota | Observação |
|---|---|---|
| GET | `/me` | dados do usuário (`ehArtista`, `roles`, `perfilArtista`) |
| PATCH | `/me` | `{nome?, email?, telefone?, bio?}` (trocou o e-mail → faça login de novo) |
| POST | `/me/foto` | multipart, campo `foto` |
| POST | `/me/artista` | `{documento, bio?}` → `{token, usuario}` (guarde o token novo) |
| GET | `/me/estatisticas` | artista: `{totalSeguidores, totalObras, obrasVendidas, totalCurtidas}` |
| GET | `/me/obras` | artista: todas as suas obras |
| GET | `/me/seguindo` | artistas que sigo |

### Obras
| Método | Rota | Observação |
|---|---|---|
| GET | `/obras?busca=&categoria=&artista=&pagina=1&porPagina=20` | feed → `{itens, total, pagina, porPagina}` |
| GET | `/obras/{id}` | detalhe (com `curtidaPorMim`) |
| POST 🔒 | `/obras` | artista, **multipart**: `titulo, descricao, valor, categorias[], imagem` |
| PATCH 🔒 | `/obras/{id}` | dono: `{titulo?, descricao?, valor?, status?: "disponivel"\|"vendida", categorias?: [ids]}` |
| POST 🔒 | `/obras/{id}/imagem` | dono, multipart `imagem` |
| DELETE 🔒 | `/obras/{id}` | dono |
| POST / DELETE 🔒 | `/obras/{id}/curtida` | curtir / descurtir |
| GET 🔒 | `/obras/{id}/comprar` | `{url}` do WhatsApp do artista → `window.open(url)` |

### Comentários (feedbacks)
| Método | Rota | Observação |
|---|---|---|
| GET | `/obras/{id}/comentarios` | lista (cada um tem `respostaAId` e `autorEhArtistaDaObra`) |
| POST 🔒 | `/obras/{id}/comentarios` | `{texto, respostaA?: idComentario}` |
| PATCH 🔒 | `/comentarios/{id}` | autor: `{texto}` |
| DELETE 🔒 | `/comentarios/{id}` | autor ou artista dono da obra |

### Artistas e categorias
| Método | Rota | Observação |
|---|---|---|
| GET | `/artistas?busca=&pagina=` | lista |
| GET | `/artistas/{id}` | perfil (com `seguidoPorMim`) |
| GET | `/artistas/{id}/obras` | obras do artista |
| POST / DELETE 🔒 | `/artistas/{id}/seguidores` | seguir / deixar de seguir |
| GET | `/categorias` | lista |

### Erros
Sempre no formato `{"erro": "mensagem", "campos": {"email": ["E-mail inválido."]}}` (`campos` só em 422).
`401` = sem token ou token expirado (mande para o login) · `403` = sem permissão · `404` = não encontrado · `409` = regra de negócio (ex.: obra vendida).

### Imagens
`imagem` e `fotoPerfil` vêm como `/uploads/...` (some `http://localhost:8000` na frente) ou como URL completa (dados de exemplo).

### Exemplo no React
```js
const API = 'http://localhost:8000/api';

const login = async (email, senha) => {
  const r = await fetch(`${API}/login`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, senha }),
  });
  if (!r.ok) throw new Error('E-mail ou senha inválidos');
  const { token, usuario } = await r.json();
  localStorage.setItem('token', token);
  return usuario;
};

const api = (rota, opcoes = {}) =>
  fetch(`${API}${rota}`, {
    ...opcoes,
    headers: { Authorization: `Bearer ${localStorage.getItem('token')}`, ...opcoes.headers },
  });

// Publicar obra (multipart — NÃO defina Content-Type, o navegador faz isso)
const form = new FormData();
form.append('titulo', 'Minha obra');
form.append('valor', '350');
form.append('categorias[]', '1');
form.append('imagem', arquivoDoInput);
await api('/obras', { method: 'POST', body: form });
```

O CORS (NelmioCorsBundle) já libera `localhost` em qualquer porta (Vite usa 5173), configurado em `.env` → `CORS_ALLOW_ORIGIN`.
