# CLAUDE.md

Сервис одноразовых секретов с E2E-шифрованием. Полная спецификация: `docs/PROJECT.md` (читать только нужный раздел).

- Стек: Laravel 12, Inertia 2 + Vue 3 (TS), MySQL 8 (прод: PostgreSQL), Sail. Команды запускать через `./vendor/bin/sail`.
- Шифрование только в браузере (`resources/js/lib/crypto.ts`, AES-256-GCM). Ключ живёт в `#fragment`, на сервер не попадает.
- Сервер хранит непрозрачный блоб; reveal = транзакция `lockForUpdate` + delete; истёкшие → 404.
- Не читать `vendor/`, `node_modules/`, `public/build/`, lock-файлы.
- Работать по этапам из раздела 14 `docs/PROJECT.md`; после этапа: `sail artisan test` и `sail npm run test`.
- Не логировать тела запросов, не хранить IP/UA.
