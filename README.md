<p align="center">
  <a href="https://laravel.com" target="_blank">
    <img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo">
  </a>
</p>

<p align="center">
  <a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
  <a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
  <a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
  <a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

# Система уведомлений Laravel

REST API для управления уведомлениями с поддержкой множественных каналов доставки и генерации отчётов.

## Содержание

- [Основные возможности](#основные-возможности)
- [Установка](#установка)
- [Архитектура](#архитектура)
- [API Документация](#api-документация)
- [Каналы уведомлений](#каналы-уведомлений)
- [Генерация отчётов](#генерация-отчётов)
- [Console команды](#console-команды)
- [Качество кода](#качество-кода)
- [Тестирование](#тестирование)
- [Что можно улучшить](#что-можно-улучшить)

## Основные возможности

- ✅ **RESTful API** для работы с уведомлениями
- ✅ **Мультиканальность** — поддержка Email и Telegram
- ✅ **Генерация отчётов** со статистикой по каналам и ошибкам
- ✅ **Асинхронная обработка** через Laravel Jobs
- ✅ **Отслеживание статуса** в реальном времени
- ✅ **Расширяемая архитектура** — лёгкое добавление новых каналов
- ✅ **Идемпотентность** — защита от дублирования запросов через Idempotency-Key
- ✅ **Exactly-once доставка** — гарантия одинакового результата при повторных запросах
- ✅ **At-least-once гарантия** — уведомление будет доставлено хотя бы один раз
- ✅ **Персистентность** — уведомления хранятся в базе до успешной доставки
- ✅ **Rate Limiting** — ограничение частоты запросов через Laravel RateLimiter
- ✅ **Тестирование** — настройка и выполнение тестов
- ✅ **Логирование** — отслеживание действий системы с уведомлениями

## Установка

```bash
# Клонировать репозиторий
git clone https://github.com/sofvlad/laravel-notifier-test.git
cd laravel-notifier-test

# Настроить окружение
cp .env.example .env

# Запустить Docker
docker-compose up -d

# Сгерировать ключ приложения
docker-compose exec app php artisan key:generate

# Добавить данные в базу
docker-compose exec app php artisan db:seed

# Сгенерировать токен для тестового пользователя
docker compose exec app php artisan user:create-token test@example.com
```

## Архитектура

### Структура проекта

```
app/
├── Actions/                          # Бизнес-логика в виде изолированных действий
│   └── Notifications/
│       ├── StoreNotificationAction.php     # Создание нового уведомления
│       ├── GetNotificationAction.php       # Получение уведомления по UUID
│       ├── ListNotificationsAction.php     # Получение списка уведомлений с пагинацией
│       └── Report/
│           ├── GenerateReportAction.php    # Запуск генерации отчёта
│           ├── GetReportStatusAction.php   # Проверка статуса генерации отчёта
│           └── DownloadReportAction.php    # Скачивание готового отчёта
├── Http/Controllers/Api/             # Контроллеры REST API
│   ├── NotificationController.php          # CRUD операции с уведомлениями
│   └── NotificationsReportController.php   # Управление генерацией отчётов
├── Http/Middleware/                # Middleware слой
│   └── IdempotencyMiddleware.php           # Обработка идемпотентности запросов
├── Contracts/Notifications/
│   └── ChannelInterface.php        # Контракт для каналов уведомлений (Strategy Pattern)
├── Enums/
│   ├── NotificationChannel.php     # Типы каналов: email, telegram
│   ├── NotificationStatus.php      # Статусы: pending, sent, failed
│   └── ReportStatus.php            # Статусы отчётов: pending, processing, completed, failed
├── Models/
│   ├── Notification.php            # Модель уведомления с отношениями и scope-ами
│   ├── NotificationsReport.php     # Модель отчёта с историей генерации
│   └── User.php                    # Модель пользователя с аутентификацией через Sanctum
├── Services/                       # Слой сервисов
│   ├── IdempotencyService.php      # Логика идемпотентности (блокировки, кэширование)
│   ├── Notifications/
│   │   ├── ChannelManager.php          # Диспетчер каналов (выбирает нужный канал)
│   │   ├── NotificationService.php     # Оркестрация операций с уведомлениями
│   │   └── Reports/
│   │       └── NotificationsReportService.php  # Логика генерации статистических отчётов
└── Jobs/
    ├── SendNotification.php        # Асинхронная отправка уведомления через очередь
    └── GenerateNotificationsReport.php  # Асинхронная генерация отчёта
```

### Паттерны проектирования

- **Action Pattern** — каждый действие реализует `ActionInterface`, что позволяет держать тонкий контроллер
- **Strategy Pattern** — каждый канал уведомлений реализует `ChannelInterface`, что позволяет легко добавлять новые каналы без изменения существующего кода
- **Service Layer** — бизнес-логика инкапсулирована в сервисах (`NotificationService`, `NotificationsReportService`)
- **Action Pattern** — сложные операции разбиты на атомарные действия (`Actions/Notifications/`)
- **Manager Pattern** — `ChannelManager` централизованно управляет доступными каналами

### Связи компонентов

```
API Controller → Action → Service → Job → Channel
     ↓              ↓         ↓        ↓       ↓
  Request      Business   Logic   Queue   Email/
                 Logic           Worker  Telegram
```

1. **Контроллер** принимает HTTP-запрос и валидирует входные данные
2. **Action** обрабатывает конкретную бизнес-операцию
3. **Service** оркестрирует несколько действий и работает с моделями
4. **Job** помещает задачу в очередь для асинхронной обработки
5. **Channel** реализует конкретный механизм доставки уведомления

## API Документация

### Аутентификация

Все API запросы требуют аутентификации через Laravel Sanctum:

```bash
curl -H "Authorization: Bearer {token}" ...
```

### Уведомления

#### Создать уведомление

```http
POST /api/v1/notifications
Idempotency-Key: <unique-key>
Content-Type: application/json
Authorization: Bearer {token}

{
  "user_ids": [1],
  "message": "Ваш заказ доставлен",
  "channel": "email",
  "priority": "critical"
}
```

**Параметры:**
- `user_id` (integer) — ID пользователя-получателя
- `user_ids` (array) — ID пользователя-получателя
- `message` (string, required) — Текст сообщения (макс. 500 символов)
- `channel` (string, required) — Канал: `email` или `telegram`
- `priority` (string, required) — Приоритет: `default` или `critical`

⚠️ **Важно:** Необходимо указать либо `user_id` (один пользователь), либо `user_ids` (массив пользователей). Указание обоих параметров одновременно недопустимо.

**Ответ:**
```json
{
    "items": [
        {
            "id": 1,
            "uuid": "3d5fbc90-1728-4831-acd8-28a9de411e57",
            "user_id": 1,
            "message": "Ваш заказ доставлен",
            "status": "pending",
            "attempt": 0,
            "last_attempt_at": null,
            "next_attempt_at": null,
            "channel": "email",
            "priority": "critical",
            "error_message": null,
            "sent_at": null,
            "created_at": "2026-06-11T09:12:14.000000Z",
            "updated_at": "2026-06-11T09:12:14.000000Z"
        }
    ]
}
```

#### Получить уведомление

```http
GET /api/v1/notifications/{notification_uuid}
Authorization: Bearer {token}
```

**Ответ:**
```json
{
    "id": 1,
    "uuid": "3d5fbc90-1728-4831-acd8-28a9de411e57",
    "user_id": 1,
    "message": "Ваш заказ доставлен",
    "status": "sent",
    "attempt": 1,
    "last_attempt_at": "2026-06-11T09:12:14.000000Z",
    "next_attempt_at": null,
    "channel": "email",
    "priority": "critical",
    "error_message": null,
    "sent_at": "2026-06-11T09:12:14.000000Z",
    "created_at": "2026-06-11T09:12:14.000000Z",
    "updated_at": "2026-06-11T09:12:14.000000Z"
}
```

### Отчёты

#### Запросить генерацию отчёта

```http
POST /api/v1/reports/notifications/generate
Idempotency-Key: <unique-key>
Content-Type: application/json
Authorization: Bearer {token}

{
  "period_start": "2024-01-01",
  "period_end": "2024-01-31",
  "user_id": 1
}
```

**Параметры:**
- `period_start` (string, required) — Начало периода (YYYY-MM-DD)
- `period_end` (string, required) — Конец периода (YYYY-MM-DD)
- `user_id` (integer, optional) — ID пользователя (если не указан — все пользователи)

**Ответ (202 Accepted):**
```json
{
    "report_uuid": "550e8400-e29b-41d4-a716-446655440000",
    "status": "pending",
    "period": {
        "start": "2024-01-01T00:00:00Z",
        "end": "2024-01-31T23:59:59Z"
    },
    "message": "Report generation started"
}
```

#### Проверить статус отчёта

```http
GET /api/v1/reports/notifications/{report_uuid}
Authorization: Bearer {token}
```

**Ответ:**
```json
{
    "report_uuid": "550e8400-e29b-41d4-a716-446655440000",
    "status": "completed",
    "period": {
        "start": "2024-01-01T00:00:00Z",
        "end": "2024-01-31T23:59:59Z"
    }
}
```

**Возможные статусы:**
- `pending` — Отчёт поставлен в очередь
- `processing` — Генерация отчёта в процессе
- `completed` — Отчёт готов к скачиванию
- `failed` — Ошибка генерации

#### Скачать отчёт

```http
GET /api/v1/reports/notifications/{report_uuid}/download
Authorization: Bearer {token}
```

**Ответ (успешно):**
```json
{
    "report_id": "550e8400-e29b-41d4-a716-446655440000",
    "period": {
        "start": "2024-01-01T00:00:00Z",
        "end": "2024-01-31T23:59:59Z"
    },
    "user_id": 1,
    "summary": {
        "total_notifications": 150
    },
    "by_channel": [
        {
            "channel": "email",
            "total": 100,
            "errors": 5
        },
        {
            "channel": "telegram",
            "total": 50,
            "errors": 2
        }
    ],
    "generated_at": "2024-01-15T12:00:00Z"
}
```

## Каналы уведомлений

### Структура канала

Каждый канал реализует контракт `ChannelInterface`:

```php
namespace App\Contracts\Notifications;

use App\Models\Notification;

interface ChannelInterface
{
    public function getName(): string;
    public function send(Notification $notification): void;
}
```

### Добавление нового канала

Для добавления нового канала необходимо:

1. **Создать Enum значение** в `NotificationChannel`:
   ```php
   case SMS = 'sms';
   ```

2. **Реализовать класс канала**:
   ```php
   namespace App\Services\Notifications\Channels;
   
   use App\Contracts\Notifications\ChannelInterface;
   use App\Models\Notification;
   
   class SmsChannel implements ChannelInterface
   {
       public function getName(): string
       {
           return NotificationChannel::SMS->value;
       }
   
       public function send(Notification $notification): void
       {
           // Реализация отправки SMS
       }
   }
   ```
3. **Зарегистрировать в `config/notifications.php`**
   ```php
   return [
       'channels' => [
           'sms' => SmsChannel::class,
       ],
   ];
   ```

## Генерация отчётов

### Что содержит отчёт

Отчёт по уведомлениям включает:

- **Общая статистика** — общее количество уведомлений
- **По каналам** — разбивка по каждому каналу:
    - Количество отправленных уведомлений
    - Количество ошибок
- **Период** — даты начала и конца отчётного периода
- **Временная метка** — время генерации отчёта

### Формат отчёта

Отчёты генерируются в формате JSON:

```json
{
    "report_id": "550e8400-e29b-41d4-a716-446655440000",
    "period": {
        "start": "2024-01-01T00:00:00Z",
        "end": "2024-01-31T23:59:59Z"
    },
    "user_id": 1,
    "summary": {
        "total_notifications": 150
    },
    "by_channel": [
        {
            "channel": "email",
            "total": 100,
            "errors": 5
        },
        {
            "channel": "telegram",
            "total": 50,
            "errors": 2
        }
    ],
    "generated_at": "2024-01-15T12:00:00Z"
}
```

### Асинхронная обработка

Генерация отчётов выполняется асинхронно через Laravel Jobs:

Это позволяет обрабатывать большие объёмы данных без блокировки API.

## Console команды

### Управление пользователями

#### Создать пользователя

```bash
php artisan user:create {name?} {email?} [--password=]
```

**Параметры:**
- `name` — Имя пользователя (запросит интерактивно, если не указан)
- `email` — Email пользователя (запросит интерактивно, если не указан)
- `--password=` — Пароль (запросит интерактивно, если не указан)

**Примеры:**
```bash
# Интерактивный режим
docker-compose exec app php artisan user:create

# С параметрами
docker-compose exec app php artisan user:create John john@example.com --password=secret123
```

#### Создать API токен

```bash
php artisan user:create-token {email?} [--name=]
```

**Параметры:**
- `email` — Email пользователя (запросит интерактивно, если не указан)
- `--name=` — Имя токена (по умолчанию: "Personal Access Token")

**Примеры:**
```bash
# Интерактивный режим
docker-compose exec app php artisan user:create-token

# С email
docker-compose exec app php artisan user:create-token john@example.com

# С именем токена
docker-compose exec app php artisan user:create-token john@example.com --name="Mobile App Token"
```

**Вывод команды:**
```
API token created for admin [John] (john@example.com):

1|abcdefghijklmnopqrstuvwxyz1234567890ABCDEFGH

Make sure to save this token securely. It will not be shown again.
```

⚠️ **Важно:** Токен показывается только один раз. Сохраните его в безопасном месте!

### Управление токенами

#### Показать токены пользователя

```bash
php artisan user:api-tokens {email?}
```

**Параметры:**
- `email` — Email пользователя (запросит интерактивно, если не указан)

**Пример:**
```bash
docker-compose exec app php artisan user:api-tokens john@example.com
```

**Вывод команды:**
```
Tokens for user [John] (john@example.com):

+---+-----------------------+---------------------+---------------------+--------+---------+
| # | Name                  | Created             | Last Used           | Scopes | Revoked |
+---+-----------------------+---------------------+---------------------+--------+---------+
| 1 | Old Access Token      | 2026-05-28 15:52:15 | 2026-05-28 15:56:43 | *      | No      |
| 2 | Personal Access Token | 2026-02-12 14:25:55 | 2026-03-09 09:46:24 | *      | No      |
+---+-----------------------+---------------------+---------------------+--------+---------+
```

### Список всех команд

```bash
# Показать все доступные команды
docker-compose exec app php artisan list

# Показать команды в группе users
docker-compose exec app php artisan list user
```

## Качество кода

### PHPStan (статический анализатор)

Используется для обнаружения потенциальных ошибок и проблем с типами в коде.

Конфигурация: `phpstan.neon`

Уровень строгости: 5 (средний уровень проверки)

```bash
docker-compose exec app composer analyse
```

### PHP Pint (код-стайлер)

Используется для автоматического форматирования кода согласно стандартам Laravel.

Конфигурация: `pint.neon`

```bash
# Исправить ошибки форматирования
docker-compose exec app composer pint
```

## Тестирование

```bash
# Запустить тесты
docker-compose exec app php artisan test
```

### Feature Tests

- ✅ `NotificationTest` — тесты CRUD операций с уведомлениями:
  - Создание уведомления с валидацией
  - Получение уведомления по UUID
  - Список уведомлений пользователя с фильтрацией
  - Фильтрация по статусу и каналу
  - Валидация длины сообщения и канала
- ✅ `NotificationIdempotencyTest` — тесты идемпотентности запросов:
  - Защита от дублирования с одинаковым Idempotency-Key
  - Возврат того же результата при повторных запросах
- ✅ `NotificationsReportTest` — тесты генерации отчётов:
  - Запрос генерации отчёта
  - Проверка статуса генерации
  - Скачивание готового отчёта
  - Валидация параметров периода

### Unit Tests

- ✅ `SendNotificationTest` — тесты job-класса отправки уведомлений:
  - Проверка количества попыток (5 tries)
  - Backoff для critical приоритета (1, 3, 5, 10 секунд)
  - Установка статуса `sent` при успешной отправке
  - Повторная попытка при временной ошибке
  - Пометка как `failed` при постоянной ошибке
  - Пометка как `failed` при исчерпании попыток
- ✅ `NotificationsReportServiceTest` — тесты сервиса отчётов:
  - Генерация статистики по уведомлениям
  - Разбивка по каналам
  - Обработка ошибок

## Что можно улучшить

- Добавить OpenAPI (Swagger)
- Добавить другие форматы для выгрузки отчётов
- Страница вывода всех уведомлений в системе с фильтрацией
