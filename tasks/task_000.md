# Задача 000: Деплой, CI и эксплуатация

## 1. Цель
Подготовить приложение к production-эксплуатации: конфигурация окружения, CI-сборка, резервное копирование SQLite, план отката, мониторинг и базовый runbook.

## 2. Зависимости
- Может выполняться параллельно после Задачи 001 (базовый каркас и БД).
- Финальная приемка — после Задач 007–010.

## 3. Требования к реализации

### 3.1. Production-конфигурация
- `.env.production.example`:
  - `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://...`;
  - `DB_CONNECTION=sqlite`, `DB_DATABASE=/absolute/path/database/database.sqlite`;
  - `SESSION_DRIVER=database`, `CACHE_STORE=database`, `QUEUE_CONNECTION=sync` (или `database` при наличии worker);
  - `LOG_CHANNEL=daily`, `LOG_LEVEL=warning`;
  - `SESSION_SECURE_COOKIE=true`, `TRUSTED_PROXIES` для reverse proxy;
  - настройки `MAIL_*` для сброса пароля.
- Web-сервер: document root — `public/`; PHP-FPM 8.4; запрет доступа к `.env`, `database/`, `storage/`, `vendor/`.
- Лимиты: `client_max_body_size` для импорта, `max_execution_time` и `memory_limit` для PDF/XLSX.
- Права: `storage/` и `bootstrap/cache/` доступны на запись PHP; файл SQLite доступен PHP, но не публично; для публичной отдачи логотипов клубов (`task_010`) создаётся симлинк `php artisan storage:link` на общий (`shared`) каталог `storage/app/public`.

### 3.2. CI-сборка
- GitHub Actions (или аналог) с PHP 8.4, Node.js 24 (соответствует требованию Node.js 20+), SQLite.
- Шаги:
  1. `composer validate --strict`;
  2. `composer install --no-interaction --prefer-dist`;
  3. `npm ci`;
  4. `npm run build`;
  5. `php artisan test`;
  6. (опционально) `vendor/bin/pint --test`.
- Кэширование Composer и npm; артефакт `public/build` для деплоя.

### 3.3. SQLite: резервное копирование и восстановление
- Бэкап выполняется командой `sqlite3 database.sqlite ".backup 'backups/db-YYYYMMDD-HHMMSS.sqlite'"` или `VACUUM INTO`.
- Копирование файла на работающей БД без `.backup` не допускается.
- Планировщик Laravel: ежедневный бэкап, retention 7–30 дней, хранение вне webroot.
- Проверка восстановления: раз в релиз развернуть копию БД и выполнить smoke-тест.
- Для WAL — выполнять checkpoint перед бэкапом или использовать `.backup`, который учитывает WAL.
- Ротация audit-логов: канал `audit` пишет в `storage/logs/audit-YYYY-MM-DD.log`; хранение 90 дней; старые файлы удаляются планировщиком; бэкап audit-логов не делается (см. `questions.md` №12).

### 3.4. План отката
- Релизы в отдельных каталогах + symlink `current`.
- Перед миграциями — обязательный бэкап БД.
- Откат:
  1. Переключить symlink на предыдущий релиз;
  2. Восстановить БД из бэкапа, если миграция необратима;
  3. Очистить кэши (`config:clear`, `route:clear`, `view:clear`);
  4. Проверить `/up` и ключевые страницы.
- `php artisan migrate:rollback` использовать только для обратимых миграций.

### 3.5. Мониторинг и безопасность
- Health check `/up`.
- Мониторинг 5xx, времени ответа, свободного места, успешности бэкапов и ошибок входа.
- HTTPS, secure cookies, rate limiting для login и password reset.
- Кэширование: HTML зависит от cookie `locale`, поэтому прокси/CDN не кэширует HTML без `Vary: Cookie` (см. `task_009`).
- `APP_DEBUG=false`; стек-трейсы не показываются пользователю.
- Регулярное обновление зависимостей и проверка `composer audit`.

### 3.6. Runbook
- Первичная инициализация администратора на чистой БД: `php artisan migrate --force`, затем `php artisan admin:bootstrap admin@yourdomain.tld --send-reset` (или интерактивный ввод пароля без `--send-reset`); `db:seed` в `production` не используется и заблокирован.
- Деплой, откат, восстановление БД, ротация логов, проверка бэкапов.
- Контакты ответственных и порядок эскалации.

### 3.7. Подзадачи реализации
- [x] **0.1. Production-конфиг:** `.env.production.example`, настройки PHP/веб-сервера, права на файлы.
- [x] **0.2. CI:** workflow с PHP 8.4, Node.js 24 (требование — 20+), SQLite, тестами и сборкой ассетов.
- [x] **0.3. Бэкап SQLite:** команда/планировщик, retention, проверка восстановления.
- [x] **0.4. Откат:** релизные каталоги, symlink, бэкап перед миграциями, инструкция.
- [x] **0.5. Мониторинг:** `/up`, логи, алерты, проверка диска и бэкапов.
- [x] **0.6. Runbook:** документ по деплою, откату и восстановлению.

## 4. Критерии приемки (DoD)
1. Приложение разворачивается из CI-артефакта на чистом окружении.
2. CI-пайплайн проходит: composer, npm build, тесты.
3. Бэкап SQLite создается и успешно восстанавливается в отдельную копию.
4. Откат на предыдущий релиз и восстановление БД отработаны на учебном стенде.
5. `/up` возвращает успешный статус; `APP_DEBUG=false` в production.
6. Команда `php artisan admin:bootstrap` создаёт (или повышает с `--promote`) администратора без хардкода пароля, а `DatabaseSeeder` заблокирован при `APP_ENV=production`.
7. Runbook содержит bootstrap администратора, deploy, rollback, restore и проверку бэкапов.

## 5. Проверка и тесты
- CI: успешный прогон на pull request и на main.
- Тест команды бэкапа: файл создается, открывается SQLite, содержит актуальные данные.
- Тест восстановления: restored DB проходит smoke-тест.
- Smoke-тест после деплоя: главная, список игроков, список турниров, вход.
- Проверка прав: `.env` и `database.sqlite` недоступны по HTTP.

## 6. Риски и допущения

- SQLite допускает только одного писателя; WAL помогает чтению, но не параллельной записи.
- Бэкап простым копированием файла может быть поврежден; используется `.backup`/`VACUUM INTO`.
- Shared-хостинг может не иметь SSH, планировщика и `sqlite3` CLI; тогда нужен VPS или другой способ бэкапа.
- Сброс пароля зависит от доставки почты; нужен надежный SMTP-провайдер.
- Без queue worker отправка почты и тяжелые экспорты могут замедлять запросы.
- Секреты хранятся в `.env`; доступ к файлу ограничен.

## 7. Открытые вопросы

- Какой хостинг и ОС используются в production?
- Кто terminates TLS и настроен ли reverse proxy?
- Какие retention и offsite-хранение нужны для бэкапов?
- Какие RPO/RTO?
- Доступен ли queue worker?
- Есть ли `sqlite3` CLI на production?
- Кто имеет доступ к деплою и секретам?
