# Arti.g — Galeria de Arte

| Pasta | Tecnologia | Como rodar |
|---|---|---|
| `back_end/` | Symfony 8.1 (API REST + JWT) | ver `back_end/README.md` |
| `front_end/` | React | ver `front_end/README.md` |

Primeira vez depois de clonar:
```bash
cd back_end
composer install
# crie o back_end/.env.local com o seu DATABASE_URL
php bin/console lexik:jwt:generate-keypair
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
php bin/console doctrine:fixtures:load
```