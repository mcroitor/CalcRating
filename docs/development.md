# Разработка CalcRating

Документ содержит инструкции для локальной разработки, тестирования и ссылки на проектную документацию.

## Документация проекта

- [`technical.md`](../technical.md) — техническое задание и целевая архитектура.
- [`questions.md`](../questions.md) — принятые архитектурные решения.
- [`AGENTS.md`](../AGENTS.md) — обязательные правила для агента.
- [`PIPELINE.md`](../PIPELINE.md) — стадии разработки, навыки, gate'ы и CI/CD.
- `tasks/task_000.md` … `tasks/task_010.md` — план реализации по задачам.
- [`CHANGELOG.md`](../CHANGELOG.md) — история изменений.

## Локальный запуск (dev)

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan storage:link
```

Запуск Vite в режиме watch и PHP-сервера в двух терминалах:

```bash
npm run dev
php artisan serve
```

Приложение будет доступно по адресу `http://127.0.0.1:8000`.

> `PHP_CLI_SERVER_WORKERS` в `.env` закомментирован: многопроцессный dev-сервер работает только на POSIX и требует `php artisan serve --no-reload`. На Windows переменная игнорируется.

### Учётные записи по умолчанию (только dev / demo)

Команда `php artisan migrate --seed` заполняет базу демонстрационными шахматистами, черновиком турнира и тремя тестовыми пользователями (в `production` запуск `DatabaseSeeder` заблокирован):

| Роль | Email | Пароль | Назначение |
|---|---|---|---|
| `admin` | `admin@example.com` | `password` | Полный доступ, админ-панель `/admin/users`, удаление турниров |
| `arbiter` | `arbiter@example.com` | `password` | Ведение реестра игроков, проведение и экспорт турниров |
| `guest` | `guest@example.com` | `password` | Просмотр реестра и турниров (чтение) |

## Сборка фронтенда и проверки

```bash
npm ci && npm run build
php vendor/bin/pint --test
php artisan test
```
