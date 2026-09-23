# Identity Service

Стейтлесс JWT-сервис идентификации: регистрация/логин по телефону (OTP), email+пароль
и username+пароль, смена и восстановление пароля, смена идентификатора (телефона),
удаление аккаунта, роли (spatie/laravel-permission) и учёт активных сессий по токенам.

Аутентификация — свой гвард `id-api` (`app/Auth/Guards/IdApiGuard.php`) поверх
`php-open-source-saver/jwt-auth`, не сессии/куки Laravel.

## Требования

- PHP 8.3+, Composer
- Redis — обязателен: на нём держатся OTP-коды, сессии, кэш и роли/права
  (`SESSION_DRIVER`, `CACHE_STORE` = `redis`)
- SQLite (по умолчанию) или любая другая БД, поддерживаемая Laravel

## Установка

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Дальше — JWT-ключ. Один из двух вариантов (см. блок JWT в `.env.example`):

```bash
# HS256 (проще всего для локальной разработки)
php artisan jwt:secret
```

или RS256 через `JWT_ALGO=RS256` + `JWT_PUBLIC_KEY`/`JWT_PRIVATE_KEY`
(`php artisan jwt:generate-certs`).

```bash
touch database/database.sqlite   # если DB_CONNECTION=sqlite
php artisan migrate
```

Запуск: `php artisan serve` (или `composer dev`, поднимает сервер + очередь + логи разом).

## Провайдеры идентификации

Три провайдера, каждый включается/выключается независимо
(`config/identity.php`, `AUTH_PROVIDER_*_ENABLED`):

| Провайдер          | Логин по               | Регистрация       | Восстановление пароля |
|---------------------|-------------------------|--------------------|-------------------------|
| `phone-otp`          | телефон + одноразовый код | не нужна (auto)   | —                       |
| `email-password`     | email + пароль          | нужна верификация | по коду на email        |
| `username-password`  | username + пароль       | опционально верифиц. | нужен rescue-контакт (см. ниже) |

`username` не имеет собственного канала доставки — восстановление пароля для него
работает только если настроить `AUTH_USERNAME_PASSWORD_RESCUE_TABLE` (см. `.env.example`).
Без этого `password/forgot` для username всегда отвечает 400 — это ожидаемое поведение,
а не баг.

## API

Все роуты под `/api/auth/{provider}/...`, где `{provider}` — `phone-otp`,
`email-password` или `username-password`. Полный список — `routes/api.php`.
Дев-эндпоинт `GET /api/auth/me` (раскодированный JWT текущего запроса) не
работает при `APP_ENV=production`.

Коллекция запросов для Bruno — `dev/IdentityService/`.

## Тесты и статический анализ

```bash
composer test       # PHPUnit
composer analyze     # PHPStan (larastan), level 6
composer pint        # code style
```

## Известные ограничения

- Доставка OTP-кодов (SMS/email) пока не подключена к реальному шлюзу — коды
  уходят в лог (`storage/logs/laravel.log`). В `local`-окружении код всегда `1111`.
- CI не настроен — раскатка/репозиторий для неё пока не готовы.
