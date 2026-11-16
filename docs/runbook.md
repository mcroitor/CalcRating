# CalcRating — Production Runbook

Документ описывает развёртывание, первичную инициализацию, резервное копирование и восстановление SQLite, откат релизов и мониторинг приложения CalcRating.

---

## 1. Структура сервера и права доступа

Рекомендуемая раскладка с релизными каталогами и общими (`shared`) данными:

```text
/var/www/calcrating/
  releases/
    20261116-120000/        # код релиза + vendor/ + public/build/
  shared/
    .env                    # production-конфиг (из .env.production.example)
    database/
      database.sqlite       # основная БД SQLite (WAL)
    storage/
      app/public/clubs/     # загруженные логотипы клубов
      logs/                 # laravel-*.log и audit-*.log (retention 90 дней)
      framework/
    backups/                # бэкапы БД (вне webroot)
  current -> releases/20261116-120000
```

- Document root веб-сервера: `/var/www/calcrating/current/public` (пример конфига — `docs/nginx.conf.example`).
- Пользователь PHP-FPM (`www-data`) должен иметь права на запись в `shared/database/`, `shared/storage/`, `shared/backups/` и `current/bootstrap/cache/`.
- Файлы `.env`, `database/`, `storage/` (кроме `public/storage` → `shared/storage/app/public`) и `vendor/` должны быть закрыты от HTTP-доступа.
- PHP `php.ini` (FPM): `memory_limit >= 256M`, `max_execution_time >= 60`, `upload_max_filesize = 12M`, `post_max_size = 16M`.

---

## 2. Первичная установка (чистое окружение)

1. Подготовить `shared/.env` на основе `.env.production.example`, задать `APP_URL`, `MAIL_*`, `TRUSTED_PROXIES`, сгенерировать `APP_KEY` (`php artisan key:generate --force`).
2. Создать пустой файл БД и связать `shared`-каталоги:
   ```bash
   touch /var/www/calcrating/shared/database/database.sqlite
   ln -sfn /var/www/calcrating/shared/.env /var/www/calcrating/current/.env
   rm -rf /var/www/calcrating/current/storage
   ln -sfn /var/www/calcrating/shared/storage /var/www/calcrating/current/storage
   php /var/www/calcrating/current/artisan storage:link
   ```
3. Накатить миграции (**без** `--seed`, так как `DatabaseSeeder` заблокирован в `production`):
   ```bash
   php /var/www/calcrating/current/artisan migrate --force
   ```
4. Создать первого администратора (пароль запрашивается интерактивно и не попадает в историю shell или `.env`; либо с `--send-reset` уходит ссылка на почту):
   ```bash
   php /var/www/calcrating/current/artisan admin:bootstrap admin@yourdomain.com --name="Имя" --surname="Фамилия"
   ```
5. Прогреть кэши:
   ```bash
   php /var/www/calcrating/current/artisan config:cache
   php /var/www/calcrating/current/artisan route:cache
   php /var/www/calcrating/current/artisan view:cache
   ```

---

## 3. Регулярный деплой нового релиза

1. Собрать/доставить новый каталог релиза `releases/$TAG` с `vendor/` (через `composer install --no-dev --optimize-autoloader`) и `public/build/` (из CI-артефакта `public-build` или `npm ci && npm run build`).
2. Подключить `shared/.env` и `shared/storage`, создать `storage:link` внутри нового релиза.
3. **Обязательный бэкап БД перед миграциями:**
   ```bash
   php /var/www/calcrating/current/artisan db:backup
   ```
4. Выполнить миграции и прогреть кэши в новом релизе:
   ```bash
   php /var/www/calcrating/releases/$TAG/artisan migrate --force
   php /var/www/calcrating/releases/$TAG/artisan config:cache
   php /var/www/calcrating/releases/$TAG/artisan route:cache
   php /var/www/calcrating/releases/$TAG/artisan view:cache
   ```
5. Атомарно переключить симлинк `current` и перезагрузить PHP-FPM (сброс opcache):
   ```bash
   ln -sfn /var/www/calcrating/releases/$TAG /var/www/calcrating/current
   sudo systemctl reload php8.4-fpm
   ```
6. Выполнить smoke-проверку:
   - `curl -fsS https://calcrating.example.com/up` → `200 OK`;
   - открыть `/`, `/clubs`, `/players`, `/tournaments`, `/login`.

---

## 4. Резервное копирование и восстановление SQLite

### 4.1. Регламент бэкапов
- Прямое копирование файла `database.sqlite` на работающей БД **запрещено** (при активном WAL копия может быть неконсистентной).
- Используется команда `php artisan db:backup` (внутри выполняет `VACUUM INTO` и удаляет копии старше `BACKUP_RETENTION_DAYS`, по умолчанию 14 дней).
- Планировщик Laravel (`routes/console.php`) запускает `db:backup` ежедневно в `03:00`. Для работы планировщика в cron должен быть прописан:
  ```cron
  * * * * * cd /var/www/calcrating/current && php artisan schedule:run >> /dev/null 2>&1
  ```
- Audit-логи (`shared/storage/logs/audit-*.log`) хранятся **90 дней** (канал `audit` в `config/logging.php`) и в бэкап БД не включаются.
- Логотипы клубов хранятся в `shared/storage/app/public/clubs` и резервируются файловым бэкапом `shared/storage/app/public`.

### 4.2. Проверка бэкапа (раз в релиз)
```bash
php /var/www/calcrating/current/artisan db:verify-backup /var/www/calcrating/shared/backups/db-YYYYMMDD-HHMMSS.sqlite
```
Команда проверяет `PRAGMA integrity_check` (`ok`) и наличие таблиц `users`, `clubs`, `players`, `tournaments`, `ratings`.

### 4.3. Восстановление БД из бэкапа
1. Включить режим обслуживания:
   ```bash
   php /var/www/calcrating/current/artisan down
   ```
2. Проверить целостность выбранного бэкапа через `db:verify-backup`.
3. Сохранить повреждённую/текущую БД и её WAL-файлы, затем подложить копию:
   ```bash
   mv /var/www/calcrating/shared/database/database.sqlite /var/www/calcrating/shared/database/database.sqlite.broken
   rm -f /var/www/calcrating/shared/database/database.sqlite-wal /var/www/calcrating/shared/database/database.sqlite-shm
   cp /var/www/calcrating/shared/backups/db-YYYYMMDD-HHMMSS.sqlite /var/www/calcrating/shared/database/database.sqlite
   ```
4. Очистить кэши и вывести приложение из maintenance mode:
   ```bash
   php /var/www/calcrating/current/artisan cache:clear
   php /var/www/calcrating/current/artisan up
   ```

---

## 5. План отката релиза

1. Переключить симлинк `current` на предыдущий релиз `$PREV_TAG`:
   ```bash
   ln -sfn /var/www/calcrating/releases/$PREV_TAG /var/www/calcrating/current
   sudo systemctl reload php8.4-fpm
   ```
2. Если в отменяемом релизе была **необратимая** миграция БД — восстановить БД из предрелизного бэкапа (см. §4.3). Для обратимых миграций допустим `php artisan migrate:rollback --force`.
3. Очистить кэши:
   ```bash
   php /var/www/calcrating/current/artisan config:clear
   php /var/www/calcrating/current/artisan route:clear
   php /var/www/calcrating/current/artisan view:clear
   ```
4. Проверить `/up`, вход в систему, `/clubs`, `/players`, `/tournaments`.

---

## 6. Мониторинг, кэширование и безопасность

- **Health check:** `GET /up` (используется балансировщиком/мониторингом).
- **Метрики и алерты:**
  - HTTP `5xx` и ошибки в `shared/storage/logs/laravel-*.log`;
  - свободное место на диске (БД + бэкапы + логи);
  - свежесть последнего файла в `shared/backups/db-*.sqlite` (< 26 часов);
  - рост ошибок входа и конфликтов `409` в логах.
- **Кэширование и `Vary: Cookie`:** HTML-ответы зависят от cookie `locale`. Прокси/CDN **не должен** кэшировать HTML-страницы без учёта `Vary: Cookie` (в `SetLocale` заголовок выставляется автоматически; в `docs/nginx.conf.example` для PHP задано `Cache-Control: no-store, private`).
- **Безопасность:** `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, регулярный `composer audit` и обновление зависимостей.

---

## 7. Контакты и эскалация

| Роль | Зона ответственности | Контакт / канал |
|---|---|---|
| Дежурный инженер / DevOps | Сервер, Nginx, PHP-FPM, бэкапы и откат | `ops@calcrating.example.com` |
| Ответственный разработчик | Приложение, миграции, Elo/жеребьёвка, audit-логи | `dev@calcrating.example.com` |
| Главный администратор системы | Управление пользователями (`admin:bootstrap`, блокировки), клубами | `admin@calcrating.example.com` |
