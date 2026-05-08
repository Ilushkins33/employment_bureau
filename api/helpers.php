<?php
// api/helpers.php — подключение к БД, схема, токены, хелперы
ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);

define('DB_PATH',      __DIR__ . '/../database/bureau.sqlite');
define('TOKEN_SECRET', 'bureau_softcraft_2026');

// CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── Роли ─────────────────────────────────────────────────────────────────────

class Role {
    const ADMIN    = 'admin';      // сотрудник бюро
    const EMPLOYER = 'employer';   // работодатель
    const APPLICANT = 'applicant'; // соискатель
}

// ── База данных ───────────────────────────────────────────────────────────────

function getDb(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dir = dirname(DB_PATH);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA journal_mode=WAL');
        $pdo->exec('PRAGMA foreign_keys=ON');

        initSchema($pdo);
    }

    return $pdo;
}

function initSchema(PDO $db): void {
    $db->exec("
        CREATE TABLE IF NOT EXISTS users (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            email      TEXT NOT NULL UNIQUE,
            password   TEXT NOT NULL,
            role       TEXT NOT NULL CHECK(role IN ('admin','employer','applicant')),
            first_name TEXT NOT NULL,
            last_name  TEXT NOT NULL,
            phone      TEXT,
            company    TEXT,
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS vacancies (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            employer_id  INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            title        TEXT NOT NULL,
            description  TEXT NOT NULL,
            salary_from  INTEGER,
            salary_to    INTEGER,
            location     TEXT,
            category     TEXT,
            experience   TEXT,
            employment   TEXT CHECK(employment IN ('full','part','remote','contract')),
            status       TEXT NOT NULL DEFAULT 'open'
                         CHECK(status IN ('open','closed','draft')),
            created_at   TEXT NOT NULL DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS resumes (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            applicant_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            title        TEXT NOT NULL,
            description  TEXT NOT NULL,
            skills       TEXT DEFAULT '[]',
            experience   TEXT,
            education    TEXT,
            salary_from  INTEGER,
            location     TEXT,
            status       TEXT NOT NULL DEFAULT 'active'
                         CHECK(status IN ('active','hidden')),
            created_at   TEXT NOT NULL DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS applications (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            vacancy_id   INTEGER NOT NULL REFERENCES vacancies(id) ON DELETE CASCADE,
            applicant_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            resume_id    INTEGER REFERENCES resumes(id) ON DELETE SET NULL,
            cover_letter TEXT,
            status       TEXT NOT NULL DEFAULT 'pending'
                         CHECK(status IN ('pending','accepted','rejected')),
            created_at   TEXT NOT NULL DEFAULT (datetime('now')),
            UNIQUE(vacancy_id, applicant_id)
        );

        CREATE TABLE IF NOT EXISTS interviews (
            id             INTEGER PRIMARY KEY AUTOINCREMENT,
            application_id INTEGER NOT NULL REFERENCES applications(id) ON DELETE CASCADE,
            vacancy_id     INTEGER NOT NULL REFERENCES vacancies(id) ON DELETE CASCADE,
            applicant_id   INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            employer_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            scheduled_at   TEXT NOT NULL,
            location       TEXT,
            format         TEXT NOT NULL DEFAULT 'office'
                           CHECK(format IN ('office','online','phone')),
            notes          TEXT,
            status         TEXT NOT NULL DEFAULT 'scheduled'
                           CHECK(status IN ('scheduled','completed','cancelled')),
            created_at     TEXT NOT NULL DEFAULT (datetime('now'))
        );
    ");

        // Seed: создаём демо-аккаунты если таблица пустая
    $count = $db->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ((int)$count === 0) {
        seedDemo($db);
    }
}

function seedDemo(PDO $db): void {
    $users = [
        ['admin@bureau.ru',    'Admin123!',    Role::ADMIN,    'Иван',    'Петров',   null,          null],
        ['employer@demo.ru',   'Employer123!', Role::EMPLOYER, 'Анна',    'Смирнова', '+79001234567', 'ООО «ТехноГрупп»'],
        ['applicant@demo.ru',  'Applicant123!',Role::APPLICANT,'Дмитрий', 'Козлов',   '+79007654321', null],
    ];

    $stmt = $db->prepare(
        'INSERT INTO users (email,password,role,first_name,last_name,phone,company) VALUES (?,?,?,?,?,?,?)'
    );
    foreach ($users as $u) {
        $stmt->execute([$u[0], password_hash($u[1], PASSWORD_BCRYPT), $u[2], $u[3], $u[4], $u[5], $u[6]]);
    }

    $employerId = 2;
    $vacancies = [
        [$employerId, 'PHP-разработчик',      'Разработка и поддержка веб-приложений на PHP/Laravel.',         80000, 120000, 'Иркутск',  'IT',          '1-3 года',    'full'],
        [$employerId, 'Frontend-разработчик', 'Вёрстка и разработка интерфейсов на HTML/CSS/JavaScript.',      70000, 100000, 'Иркутск',  'IT',          'до 1 года',   'remote'],
        [$employerId, 'Менеджер по продажам', 'Активные продажи, работа с клиентской базой.',                   50000,  80000, 'Иркутск',  'Продажи',     '1-3 года',    'full'],
        [$employerId, 'Бухгалтер',            'Ведение бухгалтерского учёта, подготовка отчётности.',           55000,  75000, 'Иркутск',  'Финансы',     '3-5 лет',     'full'],
        [$employerId, 'UX/UI дизайнер',       'Проектирование интерфейсов, подготовка макетов в Figma.',        65000,  95000, 'Иркутск',  'Дизайн',      '1-3 года',    'remote'],
    ];

    $stmt = $db->prepare(
        'INSERT INTO vacancies (employer_id,title,description,salary_from,salary_to,location,category,experience,employment) VALUES (?,?,?,?,?,?,?,?,?)'
    );
    foreach ($vacancies as $v) {
        $stmt->execute($v);
    }

    $applicantId = 3;
    $db->prepare(
        'INSERT INTO resumes (applicant_id,title,description,skills,experience,education,salary_from,location) VALUES (?,?,?,?,?,?,?,?)'
    )->execute([
        $applicantId,
        'PHP / JavaScript разработчик',
        'Занимаюсь веб-разработкой более 2 лет. Опыт работы с Laravel, Vue.js, REST API.',
        '["PHP","JavaScript","Laravel","Vue.js","MySQL","Git"]',
        '2 года в ООО «ВебСтудия» — разработка корпоративных сайтов и API',
        'ИРНИТУ, Информационные системы, 2022',
        70000,
        'Иркутск',
    ]);
}

// ── Ответы ────────────────────────────────────────────────────────────────────

function jsonOk($data, int $code = 200): void {
    ob_end_clean();
    http_response_code($code);
    echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

function jsonFail(string $message, int $code = 400): void {
    ob_end_clean();
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Входные данные ────────────────────────────────────────────────────────────

function body(): array {
    $raw = file_get_contents('php://input');
    if (!$raw) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

// ── Токены ────────────────────────────────────────────────────────────────────

function makeToken(int $userId, string $role): string {
    $payload   = base64_encode(json_encode(['uid' => $userId, 'role' => $role, 'exp' => time() + 86400 * 7]));
    $signature = hash_hmac('sha256', $payload, TOKEN_SECRET);
    return $payload . '.' . $signature;
}

function parseToken(): ?array {
    $header = $_SERVER['HTTP_AUTHORIZATION']
           ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
           ?? (function_exists('apache_request_headers') ? (apache_request_headers()['Authorization'] ?? '') : '')
           ?? '';

    if (!$header || !preg_match('/Bearer\s+(.+)/i', $header, $m)) return null;

    $parts = explode('.', $m[1]);
    if (count($parts) !== 2) return null;

    [$payload, $sig] = $parts;
    if (hash_hmac('sha256', $payload, TOKEN_SECRET) !== $sig) return null;

    $data = json_decode(base64_decode($payload), true);
    if (!$data || $data['exp'] < time()) return null;

    return $data;
}

function requireAuth(): array {
    $data = parseToken();
    if (!$data) jsonFail('Требуется авторизация', 401);
    return $data;
}

function requireRole(string ...$roles): array {
    $data = requireAuth();
    if (!in_array($data['role'], $roles)) jsonFail('Доступ запрещён', 403);
    return $data;
}
