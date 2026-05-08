<?php
// api/analytics.php — аналитика и отчётность (только admin)
require_once __DIR__ . "/helpers.php";

try {

$db     = getDb();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// ── GET ?action=summary — общая сводка ────────────────────────────────────────

if ($method === 'GET' && $action === 'summary') {
    requireRole(Role::ADMIN);

    $data = [
        'users' => [
            'total'      => (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn(),
            'employers'  => (int)$db->query("SELECT COUNT(*) FROM users WHERE role='employer'")->fetchColumn(),
            'applicants' => (int)$db->query("SELECT COUNT(*) FROM users WHERE role='applicant'")->fetchColumn(),
            'admins'     => (int)$db->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn(),
        ],
        'vacancies' => [
            'total'   => (int)$db->query("SELECT COUNT(*) FROM vacancies")->fetchColumn(),
            'open'    => (int)$db->query("SELECT COUNT(*) FROM vacancies WHERE status='open'")->fetchColumn(),
            'closed'  => (int)$db->query("SELECT COUNT(*) FROM vacancies WHERE status='closed'")->fetchColumn(),
            'draft'   => (int)$db->query("SELECT COUNT(*) FROM vacancies WHERE status='draft'")->fetchColumn(),
        ],
        'resumes' => [
            'total'  => (int)$db->query("SELECT COUNT(*) FROM resumes")->fetchColumn(),
            'active' => (int)$db->query("SELECT COUNT(*) FROM resumes WHERE status='active'")->fetchColumn(),
            'hidden' => (int)$db->query("SELECT COUNT(*) FROM resumes WHERE status='hidden'")->fetchColumn(),
        ],
        'applications' => [
            'total'    => (int)$db->query("SELECT COUNT(*) FROM applications")->fetchColumn(),
            'pending'  => (int)$db->query("SELECT COUNT(*) FROM applications WHERE status='pending'")->fetchColumn(),
            'accepted' => (int)$db->query("SELECT COUNT(*) FROM applications WHERE status='accepted'")->fetchColumn(),
            'rejected' => (int)$db->query("SELECT COUNT(*) FROM applications WHERE status='rejected'")->fetchColumn(),
        ],
    ];

    // Успешные трудоустройства (принятые отклики)
    $data['successful_placements'] = $data['applications']['accepted'];

    // Конверсия
    $data['conversion_rate'] = $data['applications']['total'] > 0
        ? round($data['applications']['accepted'] / $data['applications']['total'] * 100, 1)
        : 0;

    jsonOk($data);
}

// ── GET ?action=by_category — вакансии по категориям ─────────────────────────

if ($method === 'GET' && $action === 'by_category') {
    requireRole(Role::ADMIN);

    $rows = $db->query(
        "SELECT category, COUNT(*) AS count
         FROM vacancies
         WHERE category != '' AND category IS NOT NULL
         GROUP BY category
         ORDER BY count DESC"
    )->fetchAll();

    jsonOk($rows);
}

// ── GET ?action=by_location — вакансии по локациям ───────────────────────────

if ($method === 'GET' && $action === 'by_location') {
    requireRole(Role::ADMIN);

    $rows = $db->query(
        "SELECT location, COUNT(*) AS count
         FROM vacancies
         WHERE location != '' AND location IS NOT NULL
         GROUP BY location
         ORDER BY count DESC
         LIMIT 10"
    )->fetchAll();

    jsonOk($rows);
}

// ── GET ?action=recent_registrations — регистрации за последние 7 дней ────────

if ($method === 'GET' && $action === 'recent_registrations') {
    requireRole(Role::ADMIN);

    $rows = $db->query(
        "SELECT date(created_at) AS day, role, COUNT(*) AS count
         FROM users
         WHERE created_at >= datetime('now', '-7 days')
         GROUP BY day, role
         ORDER BY day DESC"
    )->fetchAll();

    jsonOk($rows);
}

// ── GET ?action=top_employers — топ работодателей ─────────────────────────────

if ($method === 'GET' && $action === 'top_employers') {
    requireRole(Role::ADMIN);

    $rows = $db->query(
        "SELECT u.company, u.first_name, u.last_name,
                COUNT(v.id) AS vacancies_count,
                SUM(CASE WHEN v.status='open'   THEN 1 ELSE 0 END) AS open_count,
                SUM(CASE WHEN v.status='closed' THEN 1 ELSE 0 END) AS closed_count
         FROM users u
         LEFT JOIN vacancies v ON v.employer_id = u.id
         WHERE u.role = 'employer'
         GROUP BY u.id
         ORDER BY vacancies_count DESC
         LIMIT 10"
    )->fetchAll();

    jsonOk($rows);
}

// ── GET ?action=recent_applications — последние отклики ───────────────────────

if ($method === 'GET' && $action === 'recent_applications') {
    requireRole(Role::ADMIN);

    $rows = $db->query(
        "SELECT a.id, a.status, a.created_at,
                v.title AS vacancy_title,
                u.first_name AS applicant_first, u.last_name AS applicant_last
         FROM applications a
         JOIN vacancies v ON v.id = a.vacancy_id
         JOIN users u     ON u.id = a.applicant_id
         ORDER BY a.created_at DESC
         LIMIT 10"
    )->fetchAll();

    jsonOk($rows);
}

jsonFail('Маршрут не найден', 404);

} catch (PDOException $e) {
    jsonFail('Ошибка базы данных: ' . $e->getMessage(), 500);
} catch (Throwable $e) {
    jsonFail('Внутренняя ошибка сервера', 500);
}
