Задание 2. Напишите README.md
Скопируйте шаблон и заполните своими данными:
# [Модуль прогноза погоды]

![PHP Checks](https://github.com/dique1337/dubchenko-pr35-weather/actions/workflows/php-checks.yml)
![PHP Version](https://img.shields.io/badge/PHP-8.1-blue)

## Описание
Модуль предназначен для получения и отображения актуальных метеоданных через внешние API. Реализован в рамках учебного проекта по веб-разработке. Позволяет пользователям просматривать прогноз погоды, а авторизованным пользователям — настраивать список отслеживаемых городов.

## Технологии
- PHP 8.1
- MySQL
- Bootstrap / Tailwind CSS

## Установка и запуск
1. Клонируйте репозиторий:
   ```
   git clone https://github.com/dique1337/dubchenko-pr35-weather.git
   ```
2. Скопируйте папку в OpenServer/domains/
3. Создайте базу данных и импортируйте database.sql
4. Настройте config.php
5. Откройте http://localhost/weathers/

## Роли пользователей
| Роль    | Возможности                        |
|---------|-------------------------------------|
| admin   | Полный доступ                       |
| user    | Работа со своими данными            |

## API
Документация API: [Knowledge Base](https://rererere.youtrack.cloud/articles/DEMO-A-19/API-dokumentaciya)

## Автор
Студент группы ПВТ-9-23, СВГТК им. Абая Кунанбаева
