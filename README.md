# Secret: одноразовые секреты со сквозным шифрованием

Сервис для передачи паролей, токенов и небольших файлов по одноразовой ссылке. Данные шифруются в браузере, сервер хранит только шифротекст и не знает ключа.

- **Прототип:** https://secret-testwork-ktceukpc.on-forge.com
- **Исходный код:** https://github.com/Acinonux/secret-testwork
- **Исходный промпт:** [PROMPT.md](PROMPT.md)
- **Лог расхода токенов и разбор:** [docs/tokens/usage.md](docs/tokens/usage.md)
- **Подробная документация:** [docs/PROJECT.md](docs/PROJECT.md)

## Как это работает

1. Браузер генерирует случайный 256-битный ключ и шифрует секрет (AES-256-GCM, Web Crypto API).
2. На сервер уходит только шифротекст и срок жизни; сервер возвращает идентификатор.
3. Ссылка имеет вид `https://host/s/{id}#{ключ}`. Часть после `#` браузер никогда не отправляет на сервер.
4. Получатель открывает ссылку и нажимает «Показать». Сервер в одной транзакции отдаёт шифротекст и удаляет запись, браузер расшифровывает его ключом из ссылки и стирает ключ из адресной строки.
5. Непрочитанные секреты удаляются по истечении срока (5 минут – 7 дней): при чтении проверяется срок, а планировщик раз в минуту чистит базу.

Просто открыть ссылку недостаточно, нужно нажать «Показать»: так превью ссылок в мессенджерах не «сжигают» секрет.

## Возможности

- Текст или файл до 5 МБ (имя и тип файла тоже шифруются).
- Выбор срока жизни: 5 минут, 1 час, 1 день, 7 дней.
- Одноразовое чтение, защищённое от гонок (`SELECT … FOR UPDATE`).
- Строгий CSP без inline-скриптов, `Referrer-Policy: no-referrer`, `Cache-Control: no-store`, HSTS.
- Ограничение частоты запросов; IP и User-Agent не сохраняются.

## Стек

Laravel 12, Inertia 2 + Vue 3 (TypeScript), Tailwind, MySQL 8 локально / PostgreSQL на проде, Laravel Sail, Pest, Vitest. Деплой: Laravel Forge.

## Локальный запуск

Нужен Docker.

```bash
git clone https://github.com/Acinonux/secret-testwork.git secret && cd secret
cp .env.example .env
docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd)":/var/www/html -w /var/www/html laravelsail/php84-composer:latest composer install
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm ci
./vendor/bin/sail npm run build
```

Приложение откроется на http://localhost. Для удаления истёкших секретов локально: `./vendor/bin/sail artisan schedule:work`.

## Тесты

```bash
./vendor/bin/sail artisan test      # API, очистка, страницы, заголовки безопасности
./vendor/bin/sail npm run test      # шифрование (Vitest)
./vendor/bin/sail npm run typecheck
```

## Ограничения

- Кто получил ссылку целиком, тот может прочитать секрет: передавайте её по доверенному каналу.
- Сервер, отдающий JS, теоретически может подменить код страницы. Это общее ограничение всех веб-приложений со сквозным шифрованием.
- Сервер видит размер шифротекста.
- Если ключ в ссылке повреждён, секрет всё равно удаляется при попытке чтения.

## Разработка с AI

Проект сделан в Claude Code (Opus 5.5) по этапам из [docs/PROJECT.md](docs/PROJECT.md#14-план-реализации), каждый этап — отдельный коммит. Для экономии токенов: короткий [CLAUDE.md](CLAUDE.md) вместо чтения всего проекта, каркас из стандартных команд Laravel, проверка тестами вместо повторного ревью кода моделью.

Лог расхода токенов собран из локальных логов Claude Code скриптом [scripts/token-usage.py](scripts/token-usage.py):

```bash
python3 scripts/token-usage.py
```
