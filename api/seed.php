<?php
// seed.php — демо-данные
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/helpers.php';

$db = getDb();

// Пароль для всех новых демо-аккаунтов
$hashEmployer  = password_hash('Employer123!',  PASSWORD_BCRYPT);
$hashApplicant = password_hash('Applicant123!', PASSWORD_BCRYPT);

$errors  = [];
$success = [];

// ── Пользователи ──────────────────────────────────────────────────────────────

$newUsers = [
    ['hr@stroytrest.ru',   $hashEmployer,  'employer',  'Марина',   'Волкова',   '+79132001122', 'ООО «СтройТрест»'],
    ['jobs@irksoft.ru',    $hashEmployer,  'employer',  'Алексей',  'Новиков',   '+79501234567', 'АО «ИркСофт»'],
    ['office@medplus.ru',  $hashEmployer,  'employer',  'Светлана', 'Орлова',    '+79143334455', 'Клиника «МедПлюс»'],
    ['anna.k@mail.ru',     $hashApplicant, 'applicant', 'Анна',     'Кузнецова', '+79147778899', null],
    ['sergey.m@mail.ru',   $hashApplicant, 'applicant', 'Сергей',   'Морозов',   '+79221112233', null],
    ['elena.p@inbox.ru',   $hashApplicant, 'applicant', 'Елена',    'Павлова',   '+79085556677', null],
    ['nikita.z@yandex.ru', $hashApplicant, 'applicant', 'Никита',   'Захаров',   '+79169990011', null],
];

$stmtUser = $db->prepare(
    'INSERT INTO users (email,password,role,first_name,last_name,phone,company) VALUES (?,?,?,?,?,?,?)'
);

foreach ($newUsers as $u) {
    try {
        $stmtUser->execute($u);
        $success[] = "Пользователь <b>{$u[3]} {$u[4]}</b> ({$u[0]}) — создан";
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), 'UNIQUE')) {
            $errors[] = "Пользователь <b>{$u[0]}</b> — уже существует, пропущен";
        } else {
            $errors[] = "Пользователь {$u[0]}: " . $e->getMessage();
        }
    }
}

// ── Получаем ID работодателей по email ───────────────────────────────────────

function userId(PDO $db, string $email): ?int {
    $s = $db->prepare('SELECT id FROM users WHERE email = ?');
    $s->execute([$email]);
    $r = $s->fetch();
    return $r ? (int)$r['id'] : null;
}

$idStroy  = userId($db, 'hr@stroytrest.ru');
$idIrk    = userId($db, 'jobs@irksoft.ru');
$idMed    = userId($db, 'office@medplus.ru');
$idAnna   = userId($db, 'anna.k@mail.ru');
$idSergey = userId($db, 'sergey.m@mail.ru');
$idElena  = userId($db, 'elena.p@inbox.ru');
$idNikita = userId($db, 'nikita.z@yandex.ru');
$idDemo   = userId($db, 'applicant@demo.ru'); // уже существующий

// ── Вакансии ──────────────────────────────────────────────────────────────────

$vacancies = [
    // СтройТрест
    [$idStroy, 'Инженер-строитель',
     'Контроль качества строительных работ, ведение исполнительной документации, взаимодействие с подрядчиками.',
     70000, 100000, 'Иркутск', 'Производство', '3-5 лет', 'full', 'open'],

    [$idStroy, 'Сметчик',
     'Составление смет в программе Гранд-Смета, анализ проектной документации, взаимодействие с заказчиками.',
     60000, 85000, 'Иркутск', 'Финансы', '1-3 года', 'full', 'open'],

    [$idStroy, 'Прораб',
     'Организация и контроль строительно-монтажных работ на объекте, соблюдение сроков и технологий.',
     80000, 120000, 'Иркутск', 'Производство', '3-5 лет', 'full', 'open'],

    [$idStroy, 'Водитель-экспедитор',
     'Доставка строительных материалов по городу и области, категория B/C.',
     45000, 65000, 'Иркутск', 'Производство', 'до 1 года', 'full', 'closed'],

    // ИркСофт
    [$idIrk, 'Python-разработчик',
     'Разработка backend-сервисов на Python/FastAPI, проектирование REST API, работа с PostgreSQL и Redis.',
     90000, 140000, 'Иркутск', 'IT', '1-3 года', 'remote', 'open'],

    [$idIrk, 'DevOps-инженер',
     'Настройка CI/CD (GitLab), администрирование Linux-серверов, работа с Docker и Kubernetes.',
     100000, 150000, 'Иркутск', 'IT', '3-5 лет', 'full', 'open'],

    [$idIrk, 'QA-инженер',
     'Ручное и автоматизированное тестирование веб-приложений, написание тест-кейсов, работа с Postman и Selenium.',
     65000, 90000, 'Иркутск', 'IT', '1-3 года', 'full', 'open'],

    [$idIrk, 'Системный администратор',
     'Поддержка ИТ-инфраструктуры компании, администрирование Windows Server и Linux, настройка сетевого оборудования.',
     60000, 85000, 'Иркутск', 'IT', '1-3 года', 'full', 'open'],

    [$idIrk, 'iOS-разработчик',
     'Разработка мобильных приложений на Swift, работа с UIKit и SwiftUI.',
     95000, 135000, 'Иркутск', 'IT', '1-3 года', 'remote', 'open'],

    // МедПлюс
    [$idMed, 'Врач-терапевт',
     'Ведение приёма пациентов, постановка диагнозов, назначение лечения. Работа в современной клинике с хорошим оснащением.',
     80000, 110000, 'Иркутск', 'Медицина', '3-5 лет', 'full', 'open'],

    [$idMed, 'Медицинская сестра',
     'Выполнение врачебных назначений, уход за пациентами, ведение медицинской документации.',
     45000, 60000, 'Иркутск', 'Медицина', 'до 1 года', 'full', 'open'],

    [$idMed, 'Администратор клиники',
     'Запись пациентов, работа с кассой, взаимодействие с врачами, ведение документооборота.',
     40000, 55000, 'Иркутск', 'Продажи', 'без опыта', 'full', 'open'],
];

$stmtVac = $db->prepare(
    'INSERT INTO vacancies (employer_id,title,description,salary_from,salary_to,location,category,experience,employment,status)
     VALUES (?,?,?,?,?,?,?,?,?,?)'
);

$vacancyIds = []; // title => id

foreach ($vacancies as $v) {
    if (!$v[0]) { $errors[] = "Вакансия «{$v[1]}» — работодатель не найден, пропущена"; continue; }
    try {
        $stmtVac->execute($v);
        $newId = (int)$db->lastInsertId();
        $vacancyIds[$v[1]] = $newId;
        $success[] = "Вакансия <b>{$v[1]}</b> — создана (id {$newId})";
    } catch (PDOException $e) {
        $errors[] = "Вакансия «{$v[1]}»: " . $e->getMessage();
    }
}

// ── Резюме ────────────────────────────────────────────────────────────────────

$resumes = [
    [$idAnna, 'Бухгалтер / экономист',
     'Опыт ведения бухгалтерского учёта более 4 лет. Уверенно работаю в 1С:Бухгалтерия, готовлю отчётность в ФНС и ПФР.',
     '["1С:Бухгалтерия","Excel","Налоговая отчётность","Зарплата и кадры","Финансовый анализ"]',
     '4 года — ООО «ТоргСнаб», бухгалтер по расчётам с поставщиками',
     'БГУ, Бухгалтерский учёт и аудит, 2019', 65000, 'Иркутск', 'active'],

    [$idAnna, 'Финансовый аналитик',
     'Ищу позицию аналитика. Опыт подготовки управленческой отчётности, анализа P&L, работы с Excel на продвинутом уровне.',
     '["Excel","Power BI","Финансовое моделирование","SQL","Tableau"]',
     '4 года — ООО «ТоргСнаб», бухгалтер (часть задач аналитика)',
     'БГУ, Бухгалтерский учёт и аудит, 2019', 80000, 'Иркутск', 'active'],

    [$idSergey, 'Python / DevOps инженер',
     'Занимаюсь разработкой и инфраструктурой уже 3 года. Основной стек — Python + FastAPI, деплой через Docker и GitLab CI.',
     '["Python","FastAPI","Docker","Linux","GitLab CI","PostgreSQL","Redis","Bash"]',
     '3 года — СКБ «Контур», backend-разработчик и частично DevOps',
     'ИГУ, Прикладная информатика, 2020', 110000, 'Иркутск', 'active'],

    [$idElena, 'Администратор / офис-менеджер',
     'Организационный опыт 2 года: работа с документами, координация встреч, работа с клиентами. Ищу стабильное место.',
     '["MS Office","1С","Документооборот","CRM","Деловая переписка"]',
     '2 года — Медицинский центр «Здоровье», старший администратор',
     'ИРНИТУ, Управление персоналом, 2021', 45000, 'Иркутск', 'active'],

    [$idNikita, 'Frontend-разработчик',
     'Занимаюсь вёрсткой и разработкой интерфейсов. Хорошо знаю HTML/CSS/JS, работал с Vue.js и React.',
     '["HTML","CSS","JavaScript","Vue.js","React","Figma","Git","SASS"]',
     '1.5 года — Веб-студия «Пиксель», Junior Frontend Developer',
     'ИРНИТУ, Информационные технологии, 2023', 75000, 'Иркутск', 'active'],
];

$stmtRes = $db->prepare(
    'INSERT INTO resumes (applicant_id,title,description,skills,experience,education,salary_from,location,status)
     VALUES (?,?,?,?,?,?,?,?,?)'
);

$resumeIds = []; // "applicant_id:title" => id

foreach ($resumes as $r) {
    if (!$r[0]) { $errors[] = "Резюме «{$r[1]}» — соискатель не найден, пропущено"; continue; }
    try {
        $stmtRes->execute($r);
        $newId = (int)$db->lastInsertId();
        $resumeIds[$r[0] . ':' . $r[1]] = $newId;
        $success[] = "Резюме <b>{$r[1]}</b> — создано (id {$newId})";
    } catch (PDOException $e) {
        $errors[] = "Резюме «{$r[1]}»: " . $e->getMessage();
    }
}

// ── Отклики ───────────────────────────────────────────────────────────────────

// Хелпер: получить id резюме по соискателю и названию
function rId(array $ids, int $uid, string $title): ?int {
    return $ids[$uid . ':' . $title] ?? null;
}

// Хелпер: получить id вакансии по названию
function vId(array $ids, string $title): ?int {
    return $ids[$title] ?? null;
}

$applications = [
    // Никита → Frontend-разработчик (ТехноГрупп, вакансия уже была в seed)
    [vId($vacancyIds, 'Frontend-разработчик'), $idNikita,
     rId($resumeIds, $idNikita, 'Frontend-разработчик'),
     'Добрый день! Занимаюсь frontend-разработкой 1.5 года, хорошо знаю Vue.js. Готов к тестовому заданию.', 'pending'],

    // Никита → Python-разработчик (ИркСофт)
    [vId($vacancyIds, 'Python-разработчик'), $idNikita,
     rId($resumeIds, $idNikita, 'Frontend-разработчик'),
     'Интересует переход в сторону Python, имею базовые знания языка и большое желание развиваться.', 'pending'],

    // Сергей → DevOps (принят)
    [vId($vacancyIds, 'DevOps-инженер'), $idSergey,
     rId($resumeIds, $idSergey, 'Python / DevOps инженер'),
     'Работаю с Docker и GitLab CI в продакшене уже 2 года. Буду рад обсудить детали на собеседовании.', 'accepted'],

    // Сергей → Python
    [vId($vacancyIds, 'Python-разработчик'), $idSergey,
     rId($resumeIds, $idSergey, 'Python / DevOps инженер'),
     'Основной язык — Python, есть опыт FastAPI и работы с PostgreSQL. Прикладываю резюме.', 'pending'],

    // Анна → Сметчик (отклонена)
    [vId($vacancyIds, 'Сметчик'), $idAnna,
     rId($resumeIds, $idAnna, 'Финансовый аналитик'),
     'Имею опыт работы с финансовой документацией, быстро освою Гранд-Смету.', 'rejected'],

    // Анна → QA-инженер
    [vId($vacancyIds, 'QA-инженер'), $idAnna,
     rId($resumeIds, $idAnna, 'Бухгалтер / экономист'),
     'Хочу сменить сферу деятельности. Готова пройти обучение и выполнить тестовое задание.', 'pending'],

    // Елена → Администратор клиники
    [vId($vacancyIds, 'Администратор клиники'), $idElena,
     rId($resumeIds, $idElena, 'Администратор / офис-менеджер'),
     'Работала администратором в медцентре 2 года, знаю специфику. Хочу продолжить в этой сфере.', 'pending'],

    // Елена → Системный администратор — без резюме
    [vId($vacancyIds, 'Системный администратор'), $idElena, null,
     'Есть базовые знания сетей и Windows Server, хочу развиваться в этом направлении.', 'pending'],

    // Дмитрий (demo) → QA-инженер
    [vId($vacancyIds, 'QA-инженер'), $idDemo, null,
     'Помимо разработки занимался ручным тестированием REST API через Postman. Интересует переход в QA.', 'pending'],

    // Дмитрий → Python-разработчик
    [vId($vacancyIds, 'Python-разработчик'), $idDemo, null,
     'Хочу развиваться в Python, имею опыт веб-разработки на PHP и базовые знания Python.', 'pending'],
];

$stmtApp = $db->prepare(
    'INSERT INTO applications (vacancy_id,applicant_id,resume_id,cover_letter,status) VALUES (?,?,?,?,?)'
);

foreach ($applications as $a) {
    if (!$a[0]) { $errors[] = "Отклик — вакансия не найдена (возможно уже была в БД), пропущен"; continue; }
    if (!$a[1]) { $errors[] = "Отклик — соискатель не найден, пропущен"; continue; }
    try {
        $stmtApp->execute($a);
        $success[] = "Отклик applicant_id={$a[1]} → vacancy_id={$a[0]} — создан";
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), 'UNIQUE')) {
            $errors[] = "Отклик applicant_id={$a[1]} → vacancy_id={$a[0]} — уже существует, пропущен";
        } else {
            $errors[] = "Отклик applicant_id={$a[1]} → vacancy_id={$a[0]}: " . $e->getMessage();
        }
    }
}

// ── Вывод результата ──────────────────────────────────────────────────────────
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
</head>
<body>
  <h2> Результат выполнения</h2>

  <?php foreach ($success as $msg): ?>
    <div class="ok">✓ <?= $msg ?></div>
  <?php endforeach; ?>

  <?php foreach ($errors as $msg): ?>
    <div class="err">✗ <?= $msg ?></div>
  <?php endforeach; ?>

  <div class="summary">
    Создано: <b><?= count($success) ?></b> &nbsp;|&nbsp;
    Пропущено / ошибок: <b><?= count($errors) ?></b>
  </div>

  <div style="margin-top:20px;">
    <a href="frontend/index.html" style="color:#58a6ff;">← Перейти на сайт</a>
  </div>
</body>
</html>
