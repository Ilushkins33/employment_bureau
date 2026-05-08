# Система автоматизации бюро по трудоустройству

Веб-приложение для автоматизации деятельности бюро по трудоустройству.  
Разработчик: ООО «СофтКрафт Лаб».

---

## Стек технологий

| Компонент | Технология |
|-----------|-----------|
| Backend   | PHP 8.1+  |
| База данных | SQLite 3 (создаётся автоматически) |
| Frontend  | HTML5, CSS3, JS |
| Веб-сервер | Apache 2.4+ |

---
## Установка на Apache

### 1. Скопируйте папку проекта в `htdocs`:
```
C:\xampp\htdocs\employment_bureau\
```

### 2. Запустите Apache в XAMPP.


### 3. Открыть в браузере

```
http://localhost/employment_bureau/frontend/index.html
```

---


## Демо-аккаунты

База данных и тестовые данные создаются **автоматически** при первом запросе к API.

| Роль | Email | Пароль |
|------|-------|--------|
| Администратор (сотрудник бюро) | admin@bureau.ru | Admin123! |
| Работодатель | employer@demo.ru | Employer123! |
| Соискатель | applicant@demo.ru | Applicant123! |

### Расширенные тестовые данные

Для заполнения базы дополнительными пользователями, вакансиями и резюме:

```
http://localhost/employment_bureau/api/seed.php
```

---

## Структура проекта

```
employment_bureau/
├── api/                    # Backend (PHP REST API)
│   ├── helpers.php         # Подключение к БД, токены, утилиты
│   ├── auth.php            # Регистрация, вход, текущий пользователь
│   ├── vacancies.php       # Управление вакансиями
│   ├── resumes.php         # Управление резюме
│   ├── applications.php    # Отклики на вакансии
│   ├── interviews.php      # Собеседования
│   ├── search.php          # Поиск и сопоставление кандидатов
│   └── analytics.php       # Аналитика и отчётность
│
├── frontend/               # Frontend (HTML + CSS + JS)
│   ├── index.html          # Главная страница
│   ├── login.html          # Вход
│   ├── register.html       # Регистрация
│   ├── vacancies.html      # Список вакансий
│   ├── vacancy.html        # Карточка вакансии
│   ├── resumes.html        # Список резюме (admin/employer)
│   ├── search.html         # Поиск и подбор кандидатов
│   ├── interviews.html     # Собеседования
│   ├── analytics.html      # Аналитика (admin)
│   ├── dashboard.html      # Личный кабинет
│   ├── shared.css          # Общие стили
│   └── shared.js           # Общие утилиты (API, сессия, toast)
│
├── database/               # SQLite база данных (создаётся автоматически)
│   └── bureau.sqlite       # Файл БД (появляется после первого запроса)
│
├── seed.php                # Скрипт тестовых данных (удалить в продакшене)
└── .htaccess               # Настройки Apache (CORS, Authorization)
```

---

## API-эндпоинты

Базовый URL: `/api/`  
Аутентификация: `Authorization: Bearer <token>`

| Модуль | Метод | Эндпоинт | Доступ |
|--------|-------|----------|--------|
| **Auth** | GET | `/auth.php?action=me` | Авторизован |
| | POST | `/auth.php?action=register` | Все |
| | POST | `/auth.php?action=login` | Все |
| **Вакансии** | GET | `/vacancies.php?action=list` | Все |
| | GET | `/vacancies.php?action=one&id=` | Все |
| | POST | `/vacancies.php?action=create` | Employer, Admin |
| | PUT | `/vacancies.php?action=update&id=` | Employer, Admin |
| | DELETE | `/vacancies.php?action=delete&id=` | Employer, Admin |
| **Резюме** | GET | `/resumes.php?action=list` | Admin, Employer |
| | GET | `/resumes.php?action=my` | Applicant |
| | POST | `/resumes.php?action=create` | Applicant |
| | PUT | `/resumes.php?action=update&id=` | Applicant |
| | DELETE | `/resumes.php?action=delete&id=` | Applicant, Admin |
| **Отклики** | GET | `/applications.php?action=list` | Авторизован |
| | POST | `/applications.php?action=apply` | Applicant |
| | PUT | `/applications.php?action=status&id=` | Employer, Admin |
| | DELETE | `/applications.php?action=delete&id=` | Applicant, Admin |
| **Собеседования** | GET | `/interviews.php?action=list` | Авторизован |
| | GET | `/interviews.php?action=one&id=` | Авторизован |
| | POST | `/interviews.php?action=schedule` | Employer, Admin |
| | PUT | `/interviews.php?action=update&id=` | Employer, Admin |
| | DELETE | `/interviews.php?action=cancel&id=` | Employer, Admin |
| **Поиск** | GET | `/search.php?action=vacancies` | Все |
| | GET | `/search.php?action=resumes` | Admin, Employer |
| | GET | `/search.php?action=match&vacancy_id=` | Admin, Employer |
| **Аналитика** | GET | `/analytics.php?action=summary` | Admin |
| | GET | `/analytics.php?action=by_category` | Admin |
| | GET | `/analytics.php?action=top_employers` | Admin |
