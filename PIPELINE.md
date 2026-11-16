# Pipeline разработки CalcRating

Документ описывает, как ведётся разработка проекта: стадии, навыки, контрольные точки, порядок задач и CI/CD. Обязательные правила для агента — в [AGENTS.md](AGENTS.md). Требования — в [technical.md](technical.md) и `tasks/task_000.md` … `tasks/task_010.md`.

## 1. Назначение и границы

**Цель:** пройти путь от документации до рабочего production-приложения без потери управляемости и без преждевременной реализации.

**Границы:**

- 11 задач: `000`–`010`;
- стек: PHP 8.4+, Laravel 12.x, SQLite 3, Breeze + Tailwind + Vite;
- один агент может вести последовательно; при параллелизации — отдельные subagents с явно указанным навыком;
- AI-функции, микросервисы, мобильные приложения и внешние API в scope не входят.

**Главный принцип:** каждая задача — вертикальный срез. Она заканчивается не «кодом», а рабочим результатом: код + миграция + тесты + UI + документация + зелёный CI.

## 2. Принципы

1. **Сначала контракт, потом код.** Сценарии, схема, маршруты и UI-состояния фиксируются до реализации.
2. **TDD для домена.** Сначала падающие тесты, затем минимальная реализация.
3. **Навык — это роль на стадии.** Навыки не существуют отдельно от процесса.
4. **Риск проверяется рано.** SQLite-конкурентность, PDF-кириллица, Elo и жеребьёвка проверяются spikes-задачами до того, как станут блокерами.
5. **Документация — часть DoD.** README, runbook и CHANGELOG обновляются вместе с кодом.
6. **Никакого scope creep.** Всё, что вне DoD задачи, попадает в «Открытые вопросы» или следующую задачу.
7. **Решения не меняются молча.** Изменение фиксируется в `questions.md`, затем в `technical.md` и задачах.

## 3. Стадии конвейера

Стадия 0 выполняется один раз. Стадии 1–7 проходятся для каждой задачи.

| Стадия | Навыки | Вход | Выход | Gate |
|---|---|---|---|---|
| **0. Inception** | `problem-decomposer`, `architect`, `technical-documentation-specialist`; опционально `research-synthesizer` | ТЗ, `questions.md`, задачи | `PIPELINE.md`, decision log, risk register, шаблоны DoR/DoD | **G0:** решения зафиксированы, противоречий нет |
| **1. Frame** | `problem-decomposer`, `test-scenario-writer`; при необходимости `architect` | Файл задачи, DoD, зависимости | Task brief, Gherkin-сценарии, список тестов, карта влияния на схему/маршруты/UI | **G1:** DoR выполнен, сценарии трассируются в DoD |
| **2. Design** | `architect`, `database-developer`; для UI — `frontend-designer` | Task brief, сценарии | План миграций, контракты сервисов, карта маршрутов, состояния экранов | **G2:** архитектурный ревью, порядок миграций, нет конфликтов |
| **3. Implement** | `test-writer` → `php-developer`, `database-developer`, `frontend-designer`, `tailwind-designer`; `web-developer` — интеграционный ревьюер | Design | Падающие тесты → код, миграции, Blade-шаблоны | **G3:** red → green, Pint, слои не нарушены |
| **4. Verify** | `test-writer`, `test-scenario-writer`, `architect` | Реализация | Отчёт тестов, дефекты, обновлённый risk register | **G4:** acceptance + edge cases + права + `409` + `migrate:fresh --seed` |
| **5. Review** | `architect`; опционально `ponytail`, `communication-adapter` | Отчёт тестов | Review notes, исправления, ADR-обновления | **G5:** нет critical/major, нет over-engineering |
| **6. Document** | `technical-documentation-specialist`; опционально `research-synthesizer` | Проверенный код | README, runbook, CHANGELOG, статус задачи | **G6:** документация соответствует поведению |
| **7. CI / release** | `devops-engineer`, `test-writer` | Зелёная сборка | CI-артефакт, деплой, backup/restore, rollback | **G7:** CI green, `/up`, проверены backup и откат |

### Что именно делает каждая стадия

**Frame.** Прочитать задачу и зависимости, проверить DoR, выделить пользовательские сценарии и технические риски. Для UI-задач — Gherkin; для алгоритмов — список unit-кейсов и property-проверок.

**Design.** Для схемы БД — `database-developer`: типы, индексы, внешние ключи, порядок миграций. Для сервисов — `architect`: границы, транзакции, DTO, исключения. Для UI — `frontend-designer`: состояния «пусто/загрузка/ошибка/успех», мобильная и десктопная раскладка.

**Implement.** `test-writer` пишет тесты по сценариям, `php-developer` реализует Laravel-код, `database-developer` проверяет SQL и индексы, `frontend-designer` и `tailwind-designer` собирают Blade/Tailwind. `web-developer` смотрит сквозной путь route → controller → service → model → view → SQLite.

**Verify.** `test-writer` добавляет edge cases: права, пустые данные, конфликт версий, повторные запросы, границы формул. `test-scenario-writer` прогоняет acceptance-сценарии. `architect` проверяет транзакции и отсутствие N+1.

**Review.** `architect` ищет архитектурный дрейф, скрытые транзакции в контроллерах, обходы Policy и optimistic locking. `ponytail` — по желанию — проверяет, не появилось ли лишних абстракций.

**Document.** `technical-documentation-specialist` обновляет README, CHANGELOG, runbook и описания маршрутов. `research-synthesizer` — при необходимости — сверяет решения с `questions.md`.

**CI / release.** `devops-engineer` поддерживает workflow, артефакты, backup SQLite, restore и rollback. `test-writer` добавляет smoke-тесты.

## 4. Матрица «задача → навык»

| Задача | Ведущий навык | Подключаемые навыки | Что проверяем |
|---|---|---|---|
| **000** Деплой/CI | `devops-engineer` | `technical-documentation-specialist`, `test-writer`, `architect` | CI, backup SQLite, restore, rollback, `/up` |
| **001** Каркас/auth | `php-developer` | `database-developer`, `test-writer`, `devops-engineer`, `architect` | Роли, WAL, `users.version`, Breeze, build |
| **002** Шахматисты | `php-developer` | `database-developer`, `frontend-designer`, `tailwind-designer`, `test-writer` | CRUD, история рейтинга, Chart.js, `players.version` |
| **003** Импорт/экспорт | `php-developer` | `database-developer`, `test-writer`, `technical-documentation-specialist` | CSV/XLSX/ODS, PDF-кириллица, дедупликация |
| **004** Турниры | `php-developer` | `database-developer`, `test-scenario-writer`, `test-writer`, `frontend-designer`, `tailwind-designer` | Статусы, `rating_before`, `rating_mode`, tiebreak |
| **005** Жеребьёвка | `php-developer` | `test-writer`, `test-scenario-writer`, `architect` | Нет повторных пар, bye, ничья в олимпийской сетке |
| **006** Результаты/Elo | `php-developer` | `test-writer`, `database-developer`, `architect` | Оба режима Elo, транзакция закрытия, `409` |
| **007** Экспорт турнира | `php-developer` | `frontend-designer`, `tailwind-designer`, `test-writer`, `technical-documentation-specialist` | PDF/XLSX/HTML, tiebreak, режим в шапке |
| **008** Админ-панель | `php-developer` | `frontend-designer`, `tailwind-designer`, `test-writer`, `test-scenario-writer`, `technical-documentation-specialist` | Роли, блокировка, последний админ, audit |
| **009** Локализация | `php-developer` | `translator`, `frontend-designer`, `tailwind-designer`, `test-writer`, `test-scenario-writer`, `technical-documentation-specialist` | i18n, `SetLocale`, паритет ключей, fallback `en`, письма, экспорты |
| **010** Клубы | `php-developer` | `database-developer`, `architect`, `frontend-designer`, `tailwind-designer`, `test-writer`, `test-scenario-writer`, `technical-documentation-specialist` | `clubs`/`club_user`/`club_player`, `tournaments.club_id`, изоляция прав, логотип, `409`, i18n |

`web-developer` подключается на задачах 004–007 и 010 как интеграционный ревьюер сквозных сценариев.

### Навыки, которые не задействуются

`ai-*`, `moodle-developer`, `mariadb-administrator`, `cpp-developer`, `linux-cpp-developer`, `windows-cpp-developer`, `bootstrap-designer`, `skeleton-designer`, `academic-manuscript-editor`, `course-localization-specialist`, `curriculum-developer`, `quiz-developer`, `assessment-writer`, `ocr-specialist`, `digital-art-evaluator`, `report` — нет соответствующей предметной области.

## 5. Порядок работ

### Критический путь

```text
001 → 002 → 004 → 005 → 006 → 007 → 000 (финальная приёмка)
```

### Параллельные ветки

- `003` — после `002`. Лучше запускать рано: это spike на библиотеки PDF/XLSX и кириллицу.
- `008` — после `001`. Независимая админ-панель.
- `009` — после `001`–`008`: переводит готовые экраны; от `000` не зависит.
- `010` — после `001`–`009`: вводит клубы, клубный штат, членство игроков и привязку турниров к клубам.
- `000` — CI-скелет после `001`, финальная приёмка после `007`–`010`.

### Рекомендуемая последовательность для одного исполнителя

```text
001 → 002 → 003 → 004 → 005 → 006 → 007 → 008 → 009 → 010 → 000
```

### Два параллельных потока

```text
Поток A: 001 → 002 → 004 → 005 → 006 → 007
Поток B:       003 → 008
Локализация: 009 (после 001–008)
Клубы:       010 (после 001–009)
Ops:         000 (CI с 001, релиз в конце)
```

### Фазы и контрольные точки

| Фаза | Задачи | Результат | Checkpoint |
|---|---|---|---|
| Фундамент | 001 + CI-скелет 000 | Laravel, SQLite/WAL, auth, роли, сборка | **C0:** вход, роли, CI |
| Реестр | 002 + 003 | Игроки, история рейтинга, импорт/экспорт | **C1:** карточка игрока и файлы |
| Турниры | 004 + 005 | Турниры, участники, жеребьёвка | **C2:** расписание туров |
| Рейтинг | 006 | Результаты, оба режима Elo, закрытие | **C3:** завершённый турнир и история |
| Отчётность, админ и локализация | 007 + 008 + 009 | Протоколы, админ-панель, мультиязычный интерфейс | **C4:** экспорт, управление пользователями, языки |
| Клубы | 010 | Клубы, штат клуба, членство игроков, клубные турниры | **C4b:** клубная изоляция прав и логотипы |
| Релиз | 000 финально | Деплой, backup, rollback | **C5:** release drill |

## 6. DoR и DoD

### Definition of Ready

- зависимости задачи закрыты;
- acceptance-сценарии или список тестов написаны;
- понятно влияние на схему БД, маршруты и UI;
- риски и открытые вопросы задачи обновлены;
- нет блокирующего открытого вопроса.

### Definition of Done

- код, миграция, тесты и Blade-шаблоны;
- все acceptance-сценарии и edge cases зелёные;
- `php artisan test`, `npm run build`, `vendor/bin/pint --test`;
- проверены права, конкурентность и `409`, где применимо;
- README/CHANGELOG/runbook обновлены;
- CI зелёный;
- разделы «Риски и допущения» и «Открытые вопросы» задачи актуализированы.

## 7. Риски и spikes

| Риск | Когда проверяем | Навыки | Критерий |
|---|---|---|---|
| SQLite: конкурентная запись | Spike после `001` | `database-developer`, `test-writer` | Два параллельных обновления → `409`, данные не потеряны |
| PDF/XLSX и кириллица | Spike в `003` | `php-developer`, `technical-documentation-specialist` | CSV/XLSX/ODS читаются, PDF без кракозябр |
| Сложность жеребьёвки | Перед `005` | `problem-decomposer`, `test-writer` | Нет повторных пар, корректный bye, ничья блокирует раунд |
| Двусмысленность Elo/K | Перед `006` | `test-writer`, `architect` | Golden-тесты для `average` и `per_game`, границы `K` |
| Блокировка последнего админа | В `008` | `test-writer`, `architect` | Нельзя остаться без активного администратора |
| Расхождение переводов и захардкоженные строки | В `009` | `translator`, `test-writer` | Паритет ключей `en`/`ru`, fallback `en`, нет кириллицы в Blade/`app` |
| Клубная изоляция прав и логотипы | В `010` | `architect`, `test-writer` | Судья чужого клуба получает `403`, нет `svg`, минимум один менеджер у активного клуба |
| N+1 и медленные страницы | В `002`, `006`, `007` | `php-developer`, `architect` | Eager loading, время ответа в пределах ТЗ |
| Scope creep | На review каждой задачи | `ponytail` | Нет функций вне DoD |
| Расхождение документации и кода | На каждой задаче | `technical-documentation-specialist` | README/runbook/CHANGELOG соответствуют поведению |

**Spike** — короткая исследовательская работа без обязательства довести фичу до production. Результат spike: вывод, тест или прототип, который снимает риск. Spike не заменяет задачу и не расширяет её scope.

## 8. CI/CD

### Pull request

```text
composer validate --strict
composer install
npm ci
npm run build
php artisan test
vendor/bin/pint --test
composer audit
```

### Main

То же, что для PR, плюс:

- артефакт `public/build`;
- деплой на staging;
- smoke-проверка `/up`;
- фиксация версии и changelog.

### Release

```text
backup SQLite → migrate → deploy → smoke → rollback point
```

При ошибке:

1. переключить релиз на предыдущий;
2. восстановить БД из `.backup`, если миграция необратима;
3. очистить кэши;
4. проверить `/up` и ключевые страницы.

Node.js нужен только на этапе сборки. Production получает готовый `public/build`.

## 9. Артефакты

- `AGENTS.md` — обязательные правила для агента.
- `PIPELINE.md` — этот документ.
- `technical.md`, `questions.md` — ТЗ и решения.
- `tasks/task_000.md` … `tasks/task_010.md` — scope и DoD.
- `README.md` — обзор, установка, ссылки.
- `CHANGELOG.md` — по мере релизов.
- Runbook — формируется в `task_000`.

ADR-лист отдельно не ведём: решения фиксируются в `questions.md`. Отдельный ADR создаётся только при существенном отклонении от принятой архитектуры.

## 10. Шаблоны

### Gherkin-сценарий

```gherkin
Функция: <название>
  Сценарий: <ситуация>
    Дано <исходное состояние>
    Когда <действие>
    Тогда <ожидаемый результат>
    И <проверяемое ограничение>
```

### Task brief

```text
Задача:
Цель:
Зависимости:
Влияние на схему:
Влияние на маршруты:
Влияние на UI:
Риски:
Открытые вопросы:
Сценарии/тесты:
DoD:
```

## 11. Управление изменениями

1. Новое решение или изменение архитектуры — сначала в `questions.md`.
2. Затем обновляются `technical.md`, затронутые задачи и `README.md`.
3. После изменения перезапускается gate G0: проверка противоречий.
4. Открытый вопрос не блокирует задачу, если он не влияет на DoD; иначе задача остаётся в статусе blocked.
5. Изменение scope фиксируется в задаче, а не «по памяти».

## 12. Ближайшие шаги

1. Инициализировать git и зафиксировать baseline документации.
2. Начать `task_001` со стадии Frame: Gherkin-сценарии входа/ролей и проверка DoR.
3. После `001` прогнать spike по WAL и optimistic locking — главный технический риск проекта.
4. Вести задачи по критическому пути, не переходя к следующей без G4.
