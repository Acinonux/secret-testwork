# Secret: документация проекта

Рабочее название: **Secret**. Сервис для передачи секретов (текст или небольшой файл) по одноразовой ссылке со сквозным шифрованием в браузере.

## 1. Требования

### Функциональные
| # | Требование | Как выполняется |
|---|---|---|
| F1 | Данные шифруются в браузере и уходят на сервер уже зашифрованными | Web Crypto API, AES-256-GCM, шифрование до отправки |
| F2 | Сервер не знает ключ и не может прочитать данные | Ключ генерируется в браузере и на сервер не отправляется |
| F3 | Ключ передаётся в ссылке и до сервера не доходит | Ключ лежит во фрагменте URL (`#...`), браузер не отправляет фрагмент в HTTP-запросах |
| F4 | После первого прочтения данные удаляются | Атомарная операция «прочитать и удалить» в транзакции |
| F5 | Данные удаляются после таймаута | `expires_at` + проверка при чтении + плановая очистка раз в минуту |
| F6 | Выбор срока жизни (TTL) | 5 мин, 1 час, 1 день, 7 дней (по умолчанию 1 день) |
| F7 | Загрузка файлов | Файл до 5 МБ шифруется в браузере вместе с именем и MIME-типом |
| F8 | Подтверждение чтения | Открытие ссылки секрет не «сжигает»; нужно нажать «Показать» |

### Нефункциональные
- Без регистрации и учётных записей: сервис анонимный.
- Не хранить IP, User-Agent и другие метаданные отправителя/получателя.
- Только HTTPS (HSTS), строгий CSP, `Referrer-Policy: no-referrer`.
- Защита от перебора и спама: rate limiting.

## 2. Стек

| Слой | Технология |
|---|---|
| Backend | PHP 8.4, Laravel 12 |
| Frontend | Inertia.js 2 + Vue 3 (TypeScript), Tailwind CSS, Vite |
| БД | MySQL 8 |
| Криптография | Web Crypto API (`crypto.subtle`), без сторонних библиотек |
| Локальная разработка | Laravel Sail (Docker) |
| Тесты | Pest (backend), Vitest (крипто-модуль) |
| Хостинг | Laravel Cloud (основной вариант) или Forge + VPS |

## 3. Архитектура

```
 Отправитель (браузер)                 Сервер (Laravel)                MySQL
 ─────────────────────                 ────────────────                ─────
 1. key = random 256 bit
 2. blob = AES-GCM(key, payload)
 3. POST /api/secrets {blob, ttl} ───▶ валидация, сохранение ───────▶ secrets
                                  ◀─── {id}
 4. ссылка = https://host/s/{id}#{key}

 Получатель (браузер)
 5. GET /s/{id}  (фрагмент #key не уходит) ─▶ страница с кнопкой «Показать»
 6. POST /api/secrets/{id}/reveal ─────▶ SELECT … FOR UPDATE + DELETE ─▶ (запись удалена)
                                  ◀─── {blob}
 7. payload = AES-GCM-decrypt(key из location.hash, blob)
```

Сервер только хранит и отдаёт непрозрачный блоб. Даже при полном доступе к БД и логам расшифровать данные нельзя.

## 4. Криптография

### Параметры
- Алгоритм: **AES-256-GCM** (шифрование с аутентификацией: подмена данных обнаруживается при расшифровке).
- Ключ: 32 случайных байта (`crypto.getRandomValues`), в ссылке хранится как base64url (43 символа).
- IV: 12 случайных байт, новый для каждого секрета.
- Формат блоба: `version(1 байт) || iv(12) || ciphertext+tag`, передаётся в base64.

### Полезная нагрузка (до шифрования)
```json
{ "type": "text", "text": "..." }
{ "type": "file", "name": "id_rsa", "mime": "application/octet-stream", "data": "<base64>" }
```
Имя и тип файла шифруются вместе с содержимым, поэтому сервер не видит даже, текст это или файл. Размер блоба видно, но точная длина данных не раскрывается, если добавить паддинг до блока 1 КБ (опционально).

### Модуль `resources/js/lib/crypto.ts`
```ts
export async function encrypt(payload: object): Promise<{ blob: string; key: string }>
export async function decrypt(blob: string, key: string): Promise<object>
```
- `encrypt`: генерирует ключ, IV, шифрует `JSON.stringify(payload)` (UTF-8), возвращает base64-блоб и base64url-ключ.
- `decrypt`: разбирает версию и IV, расшифровывает; при неверном ключе или повреждённых данных бросает `DecryptError`.

### Почему фрагмент URL
Часть URL после `#` по стандарту (RFC 3986) не отправляется на сервер в HTTP-запросе и не попадает в `Referer`. Её видит только JavaScript на странице. После расшифровки фрагмент стирается из адресной строки через `history.replaceState`, чтобы ключ не остался в истории браузера.

## 5. Модель данных

Таблица `secrets`:

| Поле | Тип | Описание |
|---|---|---|
| `id` | `CHAR(32)` PK | Случайный идентификатор (`Str::random(32)`, ~190 бит), не угадывается перебором |
| `payload` | `MEDIUMBLOB` | Зашифрованный блоб (до 16 МБ) |
| `size` | `INT UNSIGNED` | Размер блоба в байтах |
| `expires_at` | `TIMESTAMP` + индекс | Момент истечения |
| `created_at` | `TIMESTAMP` | Время создания |

Без `updated_at`, IP и любых данных о пользователях. Модель `App\Models\Secret`: `$incrementing = false`, `$keyType = 'string'`, `UPDATED_AT = null`, `payload` в `$hidden`.

## 6. HTTP API и маршруты

| Метод | URL | Назначение | Ответ |
|---|---|---|---|
| GET | `/` | Inertia-страница `Create` | HTML |
| GET | `/s/{id}` | Inertia-страница `Show` (данные не трогает) | HTML |
| POST | `/api/secrets` | Создать секрет | `201 {id, expires_at}` |
| POST | `/api/secrets/{id}/reveal` | Получить блоб и удалить запись | `200 {payload}` или `404` |

`/api/*` объявлены в `routes/web.php` (сессия и CSRF через axios-cookie `XSRF-TOKEN`), отвечают JSON.

### `POST /api/secrets`
Запрос: `{ "payload": "<base64>", "ttl": 3600 }`
Валидация (`StoreSecretRequest`):
- `payload`: required, string, base64, длина ≤ 7 MB (5 МБ файла + накладные base64/JSON);
- `ttl`: required, in:300,3600,86400,604800.

### `POST /api/secrets/{id}/reveal`
```php
return DB::transaction(function () use ($id) {
    $secret = Secret::whereKey($id)
        ->where('expires_at', '>', now())
        ->lockForUpdate()
        ->first();
    abort_if(!$secret, 404);
    $payload = $secret->payload;
    $secret->delete();
    return ['payload' => base64_encode($payload)];
});
```
`lockForUpdate` гарантирует, что при двух одновременных запросах данные получит только один. На любой отсутствующий, прочитанный или истёкший секрет ответ одинаковый (`404`), чтобы нельзя было различить эти случаи.

### Почему reveal — это POST, а не GET
Мессенджеры (Telegram, Slack) и почтовые сканеры открывают ссылки для превью через GET. Если бы GET удалял секрет, получатель увидел бы «уже прочитано». Поэтому GET `/s/{id}` только отдаёт страницу, а удаление происходит после нажатия «Показать» (F8).

### Rate limiting
- `POST /api/secrets`: 30 в минуту на IP.
- `POST /api/secrets/{id}/reveal`: 60 в минуту на IP.

Лимитер использует IP только в кеше и не пишет его в БД.

## 7. Удаление по таймауту

1. **При чтении**: запрос учитывает `expires_at > now()`, поэтому истёкший секрет не отдаётся, даже если очистка ещё не прошла.
2. **Плановая очистка**: команда `secrets:purge` выполняет `Secret::where('expires_at', '<=', now())->delete()` чанками и запускается каждую минуту:
   ```php
   // routes/console.php
   Schedule::command('secrets:purge')->everyMinute()->withoutOverlapping();
   ```
   На Laravel Cloud включается флажком Scheduler, на Forge через cron `schedule:run`.

## 8. Frontend

### Структура
```
resources/js/
├── app.ts
├── lib/crypto.ts          # encrypt/decrypt, base64url
├── lib/crypto.test.ts     # Vitest
├── pages/Create.vue       # форма создания
├── pages/Show.vue         # просмотр секрета
└── components/
    ├── TtlSelect.vue
    ├── FileDrop.vue
    └── CopyButton.vue
```

### Create.vue
1. Переключатель «Текст / Файл»; textarea или выбор файла с проверкой ≤ 5 МБ на клиенте.
2. Выбор TTL.
3. Кнопка «Создать ссылку»: `encrypt()` → `axios.post('/api/secrets')` → показать ссылку `origin + '/s/' + id + '#' + key`, кнопку «Копировать» и срок жизни.
4. После создания поле с исходными данными очищается.

### Show.vue
1. При загрузке проверить наличие `location.hash`; если ключа нет, показать ошибку «Ссылка неполная».
2. Предупреждение: «Секрет можно просмотреть только один раз» и кнопка **«Показать»**.
3. По нажатию: `reveal` → `decrypt` → `history.replaceState` без фрагмента.
   - текст: показать в `<pre>` с кнопкой «Копировать»;
   - файл: создать `Blob`, дать ссылку на скачивание через `URL.createObjectURL`.
4. `404`: «Секрет не найден: он уже прочитан или истёк».
5. `DecryptError`: «Неверный ключ в ссылке» (секрет при этом уже удалён, это ожидаемое поведение).

Компоненты не делают никаких внешних запросов: нет аналитики, CDN-шрифтов и т. п.

## 9. Безопасность

### Модель угроз
| Угроза | Защита |
|---|---|
| Утечка БД, бэкапов, логов | В БД только шифротекст; ключа на сервере нет |
| Перехват трафика | HTTPS + HSTS; ключ во фрагменте, в запросы не попадает |
| Перебор ID | 32 случайных символа + rate limiting |
| Превью ссылки ботами | Удаление только по явному POST |
| Двойное чтение (гонка) | `SELECT … FOR UPDATE` в транзакции |
| XSS (кража ключа из `location.hash`) | Vue экранирует вывод, строгий CSP без `unsafe-inline` для скриптов, без сторонних скриптов |
| Подмена шифротекста | AES-GCM проверяет целостность |

### Middleware `SecurityHeaders`
```
Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; connect-src 'self'; frame-ancestors 'none'; base-uri 'none'; form-action 'self'
Referrer-Policy: no-referrer
X-Content-Type-Options: nosniff
Strict-Transport-Security: max-age=31536000; includeSubDomains
Cache-Control: no-store   (для /s/* и /api/*)
```
В dev-режиме CSP дополняется адресом Vite dev-сервера.

### Ограничения (честно описываются в README)
- Сервер, который отдаёт JS, теоретически может подменить код и украсть ключ. Это общее ограничение всех веб-приложений с E2E-шифрованием.
- Кто получил ссылку целиком, тот может прочитать секрет. Ссылку нужно передавать по доверенному каналу.
- Размер шифротекста виден серверу.

## 10. Конфигурация

`.env` (ключевое):
```
APP_URL=https://secret.example.com
DB_CONNECTION=mysql
SESSION_DRIVER=cookie        # не плодить записи в БД
LOG_LEVEL=warning            # тела запросов не логируются
SECRET_MAX_BYTES=7340032
```
`config/secrets.php`: `max_bytes`, `ttl_options`, `default_ttl`.

Лимиты окружения для файлов до 5 МБ:
- PHP: `post_max_size=16M`, `memory_limit=256M`;
- MySQL: `max_allowed_packet=32M`;
- nginx: `client_max_body_size 16M`.

## 11. Локальная разработка (Sail)

```bash
laravel new secret --vue --pest        # стартер Vue + Inertia + TS
cd secret
php artisan sail:install --with=mysql
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm install
./vendor/bin/sail npm run dev
```
Из стартер-кита удаляются аутентификация, dashboard и settings: сервис анонимный.

Полезные команды:
```bash
./vendor/bin/sail artisan test
./vendor/bin/sail npm run test          # vitest
./vendor/bin/sail artisan schedule:work # локальный планировщик
```

## 12. Тестирование

**Pest (Feature):**
- создание секрета → 201, запись в БД, `payload` совпадает с отправленным;
- валидация: нет payload, неверный TTL, превышение размера → 422;
- reveal → 200 и запись удалена; повторный reveal → 404;
- истёкший секрет → 404 даже до очистки;
- `secrets:purge` удаляет только истёкшие;
- GET `/s/{id}` не удаляет запись;
- заголовки безопасности присутствуют;
- rate limit → 429.

**Vitest (Unit):**
- `decrypt(encrypt(x)) == x` для текста, Unicode и бинарного файла;
- неверный ключ / изменённый блоб → `DecryptError`;
- ключ 43 символа base64url, IV каждый раз разный.

## 13. Деплой

### Laravel Cloud (основной)
1. Репозиторий на GitHub → New Application в Laravel Cloud.
2. Добавить ресурс **MySQL**, переменные окружения подставятся автоматически.
3. Build: `composer install --no-dev && npm ci && npm run build`.
4. Deploy: `php artisan migrate --force`.
5. Включить **Scheduler** (для `secrets:purge`).
6. Свой домен (опционально), HTTPS включён по умолчанию.

### Forge + VPS (альтернатива)
Сервер с PHP 8.4 + MySQL 8 + nginx, сайт из GitHub, Let's Encrypt, cron `* * * * * php artisan schedule:run`, лимиты из раздела 10.

## 14. План реализации

Каждый этап — отдельный коммит; после этапа запускаются тесты.

1. **Каркас**: стартер-кит, Sail + MySQL, удаление auth, `CLAUDE.md`.
2. **Backend**: миграция, модель, `SecretController` (store/reveal), Form Request, rate limits, Pest-тесты.
3. **Очистка**: команда `secrets:purge`, расписание, тест.
4. **Крипто-модуль**: `crypto.ts` + Vitest.
5. **UI**: `Create.vue`, `Show.vue`, компоненты; текст, затем файлы.
6. **Безопасность**: middleware заголовков, CSP, `Cache-Control`, тест.
7. **Деплой**: Laravel Cloud, проверка на проде.
8. **Сдача**: README (ссылка на прототип и репозиторий, как запустить), `PROMPT.md`, лог токенов.

## 15. Экономия токенов при разработке с AI

- Документация и `CLAUDE.md` пишутся один раз: AI читает короткий `CLAUDE.md`, а не весь проект.
- Генерировать каркас стандартными командами (`laravel new`, `make:model -m`, `make:request`), а не AI.
- Ставить задачу по одному этапу из раздела 14 с указанием конкретных файлов, без «посмотри проект».
- Не просить AI читать `vendor/`, `node_modules/`, lock-файлы и сгенерированные файлы.
- Проверять результат тестами, а не просьбой AI «перепроверить».
- Короткие сессии по этапам; новая сессия после завершения этапа (меньше накопленного контекста).

### Лог расхода токенов
- В Claude Code: команда `/cost` в конце каждой сессии; скриншот или текст сохраняется в `docs/tokens/`.
- Итоговый отчёт по всем сессиям проекта:
  ```bash
  npx ccusage@latest daily --since 20261120
  npx ccusage@latest session
  ```
  Вывод сохраняется в `docs/tokens/usage.md` и прикладывается к сдаче.

## 16. Результат сдачи
- Ссылка на работающий прототип (Laravel Cloud).
- Ссылка на GitHub-репозиторий.
- `PROMPT.md`: исходный промпт, с которого началась разработка.
- `docs/tokens/`: лог расхода токенов.
