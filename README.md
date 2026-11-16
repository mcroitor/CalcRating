<p align="center">
  <img src="public/images/logo.jpg" alt="CalcRating" width="320">
</p>

# CalcRating

Веб-приложение для ведения реестра шахматистов, организации клубных и открытых турниров и расчёта индивидуального рейтинга по формуле Эло.

## Возможности

- **Реестр шахматистов:** карточки игроков, поиск и фильтры, история изменений рейтинга и график динамики (Chart.js), импорт из CSV/XLSX/ODS с дедупликацией, экспорт в XLSX, PDF и HTML.
- **Турниры и жеребьёвка:** круговая, швейцарская и олимпийская системы, поддержка `bye`, фиксация стартового рейтинга при запуске, турнирная таблица с детерминированным tiebreak и публикацией результатов.
- **Расчёт рейтинга Эло:** два режима расчёта на уровне турнира — по среднему рейтингу соперников (`average`) и по каждой партии (`per_game`).
- **Экспорт протоколов:** итоговые таблицы и листы туров в XLSX, PDF и HTML для печати.
- **Шахматные клубы:** каталог клубов с логотипами, управление штатом (`manager` / `arbiter`), множественное членство игроков и изоляция прав между клубами.
- **Пользователи и доступ:** роли `guest`, `arbiter`, `admin`, оптимистичная блокировка (`409 Conflict`), админ-панель управления пользователями и audit-лог.
- **Мультиязычность:** английский (`en`, по умолчанию) и русский (`ru`) интерфейс с переключением через сессию и cookie.

## Стек

- **Backend:** PHP 8.4+, Laravel 12.x, SQLite 3 (WAL)
- **Frontend:** Blade, Tailwind CSS, Vite, Chart.js
- **Экспорт/импорт:** `maatwebsite/excel`, `barryvdh/laravel-dompdf`

---

## Развёртывание

### Вариант 1: Docker Compose (рекомендуется)

В репозитории подготовлен `docker-compose.yml` со стеком из трёх сервисов:
- `app` — PHP 8.4-FPM (Alpine, non-root `www-data`);
- `web` — Nginx 1.27 (раздача статики, `/build`, `/storage` и healthcheck `/up`);
- `scheduler` — `php artisan schedule:work` для ежедневного резервного копирования SQLite и ротации логов.

1. Подготовьте `.env` на основе `.env.production.example` (или передайте `APP_KEY` через окружение):

```bash
cp .env.production.example .env
# Укажите APP_URL и сгенерируйте APP_KEY (например, через php artisan key:generate --show)
```

2. Соберите и запустите контейнеры (при старте автоматически создаётся файл БД и применяются миграции):

```bash
docker compose up -d --build
```

3. Создайте первого администратора:

```bash
docker compose exec app php artisan admin:bootstrap admin@yourdomain.com --name="Имя" --surname="Фамилия"
```

4. Проверьте работоспособность:

- Приложение доступно на `http://localhost:8080` (порт задаётся через `APP_PORT`).
- Healthcheck: `http://localhost:8080/up`.

Резервное копирование SQLite вручную:

```bash
docker compose exec app php artisan db:backup
```

---

### Вариант 2: Ручное развёртывание (Nginx + PHP-FPM)

#### Требования к окружению

- **PHP 8.4+** с расширениями: `pdo_sqlite`, `sqlite3`, `mbstring`, `dom`, `xml`, `zip`, `gd`, `intl`, `fileinfo`, `curl`, `openssl`.
- **Composer 2.x**.
- **Node.js 20+ / npm 10+** — только на этапе сборки ассетов (`public/build`).
- **SQLite 3**.

#### Установка

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
cp .env.production.example .env
php artisan key:generate
```

Создайте файл базы данных и примените миграции:

```bash
touch database/database.sqlite
php artisan migrate --force
php artisan storage:link
```

Создайте первого администратора (в `APP_ENV=production` команда `db:seed` заблокирована):

```bash
php artisan admin:bootstrap admin@yourdomain.com --name="Имя" --surname="Фамилия"
```

Прогрейте кэши Laravel:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Настройте виртуальный хост Nginx по примеру [`docs/nginx.conf.example`](docs/nginx.conf.example) с document root в `public/` и добавьте в cron планировщик для ежедневных бэкапов:

```cron
* * * * * cd /var/www/calcrating/current && php artisan schedule:run >> /dev/null 2>&1
```

---

## Эксплуатация и документация

- [`docs/runbook.md`](docs/runbook.md) — эксплуатационный runbook (деплой, бэкап и восстановление SQLite, откат релизов, мониторинг).
- [`docs/nginx.conf.example`](docs/nginx.conf.example) — пример конфигурации Nginx + PHP-FPM 8.4.
- [`docs/development.md`](docs/development.md) — инструкции для разработчиков, тесты и проектная документация.
- [`CHANGELOG.md`](CHANGELOG.md) — история изменений.
