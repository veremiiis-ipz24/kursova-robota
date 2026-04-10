# Менеджер контактів із групами

Повнофункціональний веб-застосунок для керування контактами, розроблений з використанням PHP, MySQL, Python та чистого JavaScript.

## Стек технологій

* **Frontend**: HTML, CSS (шрифти IBM Plex, темна тема), JavaScript (SPA)
* **Backend**: PHP 8+ (патерни Service Layer та Repository)
* **База даних**: MySQL 8+
* **Обробка CSV**: Python 3 (`csv_processor.py`)

## Структура проєкту

```
contact_manager/
├── index.html          # Оболонка SPA
├── css/style.css       # Основні стилі
└── js/
│   ├── api.js          # API-клієнт
│   └── app.js          # Контролер SPA
├── backend/
│   ├── config.php          # Підключення до БД + допоміжні функції
│   ├── entities/           # Contact, User, Group
│   ├── repositories/       # ContactRepo, UserRepo, GroupRepo
│   ├── services/           # ContactSvc, GroupSvc, UserSvc, ImportExportSvc
│   └── api/                # REST-ендпоінти (contacts, groups, users, auth, importexport)
├── python/
│   └── csv_processor.py    # Валідація та генерація CSV
├── database/
│   └── schema.sql          # Схема MySQL
└── tests/
    └── test_csv_processor.py  # 23 unit-тести (unittest)
```

## Налаштування

### 1. База даних

```sql
mysql -u root -p < database/schema.sql
```

Облікові дані адміністратора за замовчуванням: `admin` / `admin123` *(потрібно змінити)*

Оновіть `backend/config.php`, вказавши свої параметри підключення до БД.

### 2. Веб-сервер

PHP-файли з `backend/` повинні бути доступні за шляхом `../backend/api/`.

### 3. Python (для імпорту CSV через PHP)

Потрібен Python 3.6+. Зовнішні бібліотеки не потрібні (використовується лише стандартна бібліотека).

### 4. Запуск тестів

```bash
python3 -m unittest tests.test_csv_processor -v
```

## Дані для входу за замовчуванням

| Логін | Пароль   | Роль  |
| ----- | -------- | ----- |
| admin | admin123 | Admin |

**Після першого входу обов’язково змініть пароль адміністратора.**

## Можливості

* ✅ CRUD для контактів (створення, перегляд, оновлення, видалення)
* ✅ Пошук контактів за ім’ям, телефоном, email
* ✅ Сортування за будь-яким полем
* ✅ Групи — створення, редагування, видалення, прив’язка контактів (many-to-many)
* ✅ Фільтрація контактів за групами
* ✅ Імпорт CSV з валідацією
* ✅ Експорт CSV
* ✅ Керування користувачами (адмін: створення/видалення, зміна паролів)
* ✅ Аутентифікація на основі сесій
* ✅ Розмежування доступу за ролями (admin / user)
* ✅ 23 unit-тести на Python

## Архітектурні патерни

* **Repository Pattern** — усі операції з БД ізольовані в `repositories/`
* **Service Layer** — бізнес-логіка винесена в `services/`
* **Factory Pattern** — `ContactFactory::create()` у `ContactService`

## Гарячі клавіші

* `Ctrl+K` / `Cmd+K` — фокус на полі пошуку
* `Esc` — закрити будь-яке модальне вікно
