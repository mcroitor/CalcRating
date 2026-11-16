# CalcRating — правила для агента

Этот файл обязателен для любой сессии в репозитории CalcRating. Он задаёт минимальный контракт. Подробный процесс, матрица навыков и CI/CD — в [PIPELINE.md](PIPELINE.md).

## Источники истины

1. [`questions.md`](questions.md) — принятые архитектурные решения и развилки.
2. [`technical.md`](technical.md) — техническое задание и целевая архитектура.
3. `tasks/task_000.md` … `tasks/task_010.md` — scope, требования и DoD конкретной задачи.
4. [`PIPELINE.md`](PIPELINE.md) — стадии, навыки, gate'ы и порядок работ.
5. [`README.md`](README.md) — обзор проекта, установка и запуск.

При конфликте продуктовых решений приоритет такой: `questions.md` → `technical.md` → файл задачи → `README.md`. Процессные правила: `AGENTS.md` → `PIPELINE.md`. Если решение меняется, сначала обнови `questions.md`, затем `technical.md` и затронутые задачи.

## Стек

- PHP 8.4+, Laravel 12.x, SQLite 3.
- Laravel Breeze: Blade + Tailwind CSS + Vite.
- Chart.js через npm; Node.js нужен только для сборки.
- Два режима Elo: `average` (по умолчанию) и `per_game`; режим фиксируется в турнире.

## Обязательный pipeline

Стадия 0 выполняется один раз. Стадии 1–7 проходятся для каждой задачи.

| Стадия | Навыки | Gate |
|---|---|---|
| **0. Inception** | `problem-decomposer`, `architect`, `technical-documentation-specialist` | G0: решения зафиксированы, противоречий нет |
| **1. Frame** | `problem-decomposer`, `test-scenario-writer` | G1: DoR выполнен, сценарии трассируются в DoD |
| **2. Design** | `architect`, `database-developer`, `frontend-designer` | G2: схема, маршруты и UI согласованы |
| **3. Implement** | `test-writer`, `php-developer`, `database-developer`, `frontend-designer`, `tailwind-designer` | G3: red → green, Pint, слои не нарушены |
| **4. Verify** | `test-writer`, `test-scenario-writer`, `architect` | G4: acceptance, права, `409`, `migrate:fresh --seed` |
| **5. Review** | `architect`; при необходимости `ponytail` | G5: нет critical/major и over-engineering |
| **6. Document** | `technical-documentation-specialist` | G6: документация соответствует поведению |
| **7. CI / release** | `devops-engineer`, `test-writer` | G7: CI green, `/up`, backup/restore, rollback |

`web-developer` подключается на сквозных задачах 004–007 и 010 как интеграционный ревьюер: route → controller → service → model → Blade → SQLite.

## Definition of Ready

Задача не начинается, пока:

- зависимости из её раздела «Зависимости» не закрыты;
- acceptance-сценарии или список тестов не написаны;
- понятно влияние на схему БД, маршруты и UI;
- риски и открытые вопросы задачи обновлены;
- нет блокирующего открытого вопроса.

## Definition of Done

Задача не закрывается, пока:

- код, миграции, тесты и Blade-шаблоны готовы;
- все acceptance-сценарии и edge cases зелёные;
- `php artisan test`, `npm run build` и `vendor/bin/pint --test` проходят;
- проверены права, конкурентность и `409`, где применимо;
- README/CHANGELOG/runbook обновлены;
- CI зелёный;
- разделы «Риски и допущения» и «Открытые вопросы» задачи актуализированы.

## Команды проверки

```bash
composer validate --strict
composer install
npm ci
npm run build
php artisan test
vendor/bin/pint --test
php artisan migrate:fresh --seed
```

Если `package-lock.json` ещё нет, первая установка — `npm install`, затем lock-файл коммитится и используется `npm ci`.

## Жёсткие правила

- Бизнес-логика — в `Services`/`Actions`, а не в контроллерах, моделях или Blade.
- Многошаговые изменения — в `DB::transaction()` внутри сервиса. Транзакции короткие; почта, PDF и HTTP-вызовы — вне транзакции.
- SQLite: WAL, `busy_timeout=5000`, `foreign_keys=ON`, `synchronous=NORMAL`.
- `lockForUpdate()` не использовать. Для изменяемых агрегатов — optimistic locking через `version` и compare-and-swap.
- Конфликт версий — `ConcurrentModificationException` и HTTP `409` с сообщением «Данные изменились, обновите страницу».
- Права проверяются на сервере через Policy/Gate и middleware, а не только скрытием кнопок.
- `rating_before` фиксируется при старте турнира; после старта состав и режим не меняются.
- Завершённый турнир не пересчитывается; история рейтингов неизменяема.
- Не менять зафиксированные решения без обновления `questions.md`.
- Не добавлять функциональность вне scope задачи и DoD.
- Не коммитить `.env`, секреты и production-базу.

## Перед началом и по завершении

Перед задачей: прочитай файл задачи, `PIPELINE.md` и связанные разделы `technical.md`/`questions.md`; проверь DoR; напиши сценарии или список тестов.

По завершении: сообщи, что изменено, какие тесты запущены, какие риски остались и какая задача следующая. Если gate не пройден — не переходи к следующей задаче.
