# Сервис генерации документов

Загружаете шаблон в docx, заполняете форму, получаете PDF и docx. Документы выпускаются от имени разных организаций, реквизиты подставляются из их карточек.

Стек: Laravel 13, React 19 + TypeScript, SQLite, Docker. Конвертацию docx в PDF делает LibreOffice внутри контейнера Gotenberg.

## Запуск

```bash
docker compose up -d --build
```

Приложение: http://localhost:8080

Устанавливать ничего не нужно, PHP, Composer, Node и LibreOffice работают в контейнерах. При первом запуске создаётся база, применяются миграции и загружаются демонстрационные данные: две организации и три шаблона (счёт, акт, договор).

Остановка:

```bash
docker compose down
```

## Метки в шаблоне

В docx пишутся метки вида `${client_name}`. При загрузке файла они находятся автоматически, из них строится форма заполнения.

| Метка | Значение |
|---|---|
| `${org.name}`, `${org.inn}`, `${org.bank_account}`, `${org.director_name}` | Реквизиты выбранной организации |
| `${doc.number}`, `${doc.date}` | Номер и дата документа |
| `${client_name}`, `${total_amount}`, любые другие | Поля формы, заполняет пользователь |

Доступные метки организации совпадают с полями её карточки: `full_name`, `inn`, `kpp`, `ogrn`, `legal_address`, `actual_address`, `phone`, `email`, `bank_name`, `bank_account`, `bank_corr_account`, `bank_bik`, `director_name`, `director_position`.

Тип поля определяется по названию метки: `*_date` становится датой, `*_amount` и `*_price` числом, `*_address` многострочным текстом. Тип, подпись и обязательность можно изменить в карточке шаблона.

## Порядок работы

1. Организации: реквизиты, банковские данные, подписант, логотип.
2. Шаблоны: загрузка docx, разбор меток, настройка полей.
3. Карточка шаблона: подписи и типы полей, загрузка новых версий файла, откат к прошлым.
4. Генерация: выбор организации и шаблона, заполнение формы, предпросмотр PDF.
5. Документы: журнал выпущенных форм, скачивание PDF и docx.

## Структура

```
docker compose
├── nginx      :8080  статика React, проксирование /api в PHP
├── app               PHP 8.4-FPM, Laravel, работа с docx
└── gotenberg         LibreOffice, конвертация docx в pdf по HTTP
```

```
backend/
├── app/
│   ├── Http/{Controllers,Requests,Resources}
│   ├── Models
│   ├── Rules/DocxFile.php                      проверка загружаемого файла
│   └── Services/
│       ├── Templates/PlaceholderExtractor.php  поиск меток в docx
│       ├── Templates/TemplateService.php       загрузка, версии, поля
│       ├── Documents/DocumentGenerator.php     подстановка значений
│       └── Documents/GotenbergClient.php       конвертация в PDF
├── database/{migrations,seeders,factories}
└── tests/{Feature,Unit}

frontend/src/
├── api/     запросы и хуки React Query
└── pages/   генерация, шаблоны, карточка шаблона, организации, журнал
```

Таблицы: `organizations`, `templates`, `template_versions`, `template_fields`, `documents`.

Документ хранит ссылку на версию шаблона, по которой создан. Если шаблон изменят, ранее выпущенные документы останутся прежними.

## Решения по реализации

Конвертация вынесена в Gotenberg, а не сделана PHP-библиотекой. Библиотеки вроде dompdf собирают PDF заново и теряют исходную вёрстку, тогда как LibreOffice сохраняет шрифты, таблицы и колонтитулы. Отдельный контейнер также держит образ PHP лёгким.

Метки ищутся через PhpWord, а не регулярным выражением. Word разрывает метку на части внутри XML, если внутри неё менялось форматирование, `TemplateProcessor` такие разрывы склеивает.

Загружаемый файл проверяется правилом `DocxFile`. Стандартное `mimes:docx` определяет тип по содержимому, а системная библиотека распознаёт docx то как `application/zip`, то как `application/octet-stream`, из-за чего в контейнере ломалась загрузка. Правило проверяет, что внутри архива есть `word/document.xml`.

При замене файла шаблона создаётся новая версия, старая остаётся. Настройки полей при этом сохраняются, новые метки добавляются, исчезнувшие удаляются.

Значения экранируются перед вставкой: символы `&`, `<`, `>` ломают XML внутри docx, после чего Word отказывается открывать файл. Переносы строк превращаются в переносы Word.

## API

```
GET    /api/health                                    состояние сервиса и конвертера

GET    /api/organizations                             список
POST   /api/organizations                             создание, multipart для логотипа
GET    /api/organizations/{id}
PUT    /api/organizations/{id}
DELETE /api/organizations/{id}

GET    /api/templates?organization_id=                общие шаблоны и шаблоны организации
POST   /api/templates                                 загрузка шаблона, multipart
GET    /api/templates/{id}                            карточка: версии, поля, метки
PUT    /api/templates/{id}
DELETE /api/templates/{id}

POST   /api/templates/{id}/versions                   новая версия файла
GET    /api/templates/{id}/versions/{v}/download      исходный docx
POST   /api/templates/{id}/versions/{v}/restore       сделать версию актуальной

GET    /api/templates/{id}/fields                     настройки полей
PUT    /api/templates/{id}/fields

GET    /api/documents?organization_id=&template_id=   журнал
POST   /api/documents                                 генерация
POST   /api/documents/preview                         PDF без сохранения
GET    /api/documents/{id}/download?format=pdf|docx
DELETE /api/documents/{id}
```

## Тесты

```bash
docker compose exec app php artisan test
```

17 тестов: разбор меток, загрузка шаблонов и версионирование, подстановка реквизитов, форматирование дат и чисел, экранирование спецсимволов, валидация, скачивание файлов. Конвертер в тестах подменяется заглушкой, поэтому запуск не зависит от контейнера Gotenberg.

## Разработка

Фронтенд с горячей перезагрузкой при запущенных контейнерах:

```bash
cd frontend && npm install && npm run dev
```

Запросы к `/api` проксируются на `http://localhost:8080`.

Пересоздать базу с демонстрационными данными:

```bash
docker compose exec app php artisan migrate:fresh --seed
```

## Дальнейшие шаги

Табличные позиции в счёте через `cloneRow`, сумма прописью без ручного ввода, редактирование docx в браузере через OnlyOffice, очередь для пакетной генерации, авторизация с разграничением доступа по организациям.
