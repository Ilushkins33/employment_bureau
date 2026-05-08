<?php
// api/vacancies.php — управление вакансиями
require_once __DIR__ . "/helpers.php";

try {

$db     = getDb();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

// ── GET ?action=list — список вакансий (публичный, с фильтрами) ───────────────

if ($method === 'GET' && $action === 'list') {
    $where  = ['1=1'];
    $params = [];

    // Фильтры
    if (!empty($_GET['q'])) {
        $where[]  = '(v.title LIKE ? OR v.description LIKE ?)';
        $params[] = '%' . $_GET['q'] . '%';
        $params[] = '%' . $_GET['q'] . '%';
    }
    if (!empty($_GET['category'])) {
        $where[]  = 'v.category = ?';
        $params[] = $_GET['category'];
    }
    if (!empty($_GET['location'])) {
        $where[]  = 'v.location LIKE ?';
        $params[] = '%' . $_GET['location'] . '%';
    }
    if (!empty($_GET['employment'])) {
        $where[]  = 'v.employment = ?';
        $params[] = $_GET['employment'];
    }
    if (!empty($_GET['salary_from'])) {
        $where[]  = 'v.salary_to >= ?';
        $params[] = (int)$_GET['salary_from'];
    }
    if (!empty($_GET['experience'])) {
        $where[]  = 'v.experience = ?';
        $params[] = $_GET['experience'];
    }

    // Для публичного доступа — только открытые
    $auth = parseToken();
    if (!$auth || $auth['role'] === Role::APPLICANT) {
        $where[]  = "v.status = 'open'";
    } elseif (!empty($_GET['status'])) {
        $where[]  = 'v.status = ?';
        $params[] = $_GET['status'];
    }

    // Если employer — только свои вакансии
    if ($auth && $auth['role'] === Role::EMPLOYER && empty($_GET['all'])) {
        $where[]  = 'v.employer_id = ?';
        $params[] = $auth['uid'];
    }

    $sql = 'SELECT v.*, u.first_name, u.last_name, u.company,
                   (SELECT COUNT(*) FROM applications a WHERE a.vacancy_id = v.id) as applications_count
            FROM vacancies v
            JOIN users u ON u.id = v.employer_id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY v.created_at DESC';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    jsonOk($stmt->fetchAll());
}

// ── GET ?action=one&id= — одна вакансия ──────────────────────────────────────

if ($method === 'GET' && $action === 'one' && $id) {
    $stmt = $db->prepare(
        'SELECT v.*, u.first_name, u.last_name, u.company, u.phone, u.email as employer_email,
                (SELECT COUNT(*) FROM applications a WHERE a.vacancy_id = v.id) as applications_count
         FROM vacancies v
         JOIN users u ON u.id = v.employer_id
         WHERE v.id = ?'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) jsonFail('Вакансия не найдена', 404);
    jsonOk($row);
}

// ── POST ?action=create — создать вакансию (employer, admin) ──────────────────

if ($method === 'POST' && $action === 'create') {
    $auth = requireRole(Role::EMPLOYER, Role::ADMIN);
    $data = body();

    $title      = trim($data['title'] ?? '');
    $description= trim($data['description'] ?? '');
    $salaryFrom = isset($data['salary_from']) ? (int)$data['salary_from'] : null;
    $salaryTo   = isset($data['salary_to'])   ? (int)$data['salary_to']   : null;
    $location   = trim($data['location'] ?? '');
    $category   = trim($data['category'] ?? '');
    $experience = trim($data['experience'] ?? '');
    $employment = $data['employment'] ?? null;
    $status     = $data['status'] ?? 'open';

    if (!$title || !$description) jsonFail('Название и описание обязательны');
    if ($employment && !in_array($employment, ['full','part','remote','contract']))
        jsonFail('Недопустимый тип занятости');

    $employerId = $auth['role'] === Role::ADMIN && !empty($data['employer_id'])
        ? (int)$data['employer_id']
        : $auth['uid'];

    $db->prepare(
        'INSERT INTO vacancies (employer_id,title,description,salary_from,salary_to,location,category,experience,employment,status)
         VALUES (?,?,?,?,?,?,?,?,?,?)'
    )->execute([$employerId, $title, $description, $salaryFrom, $salaryTo, $location, $category, $experience, $employment, $status]);

    $newId = (int)$db->lastInsertId();
    $stmt  = $db->prepare('SELECT * FROM vacancies WHERE id = ?');
    $stmt->execute([$newId]);
    jsonOk($stmt->fetch(), 201);
}

// ── PUT ?action=update&id= — обновить вакансию ────────────────────────────────

if ($method === 'PUT' && $action === 'update' && $id) {
    $auth = requireRole(Role::EMPLOYER, Role::ADMIN);
    $data = body();

    $stmt = $db->prepare('SELECT * FROM vacancies WHERE id = ?');
    $stmt->execute([$id]);
    $vacancy = $stmt->fetch();
    if (!$vacancy) jsonFail('Вакансия не найдена', 404);

    // Работодатель может редактировать только свои вакансии
    if ($auth['role'] === Role::EMPLOYER && $vacancy['employer_id'] !== $auth['uid'])
        jsonFail('Доступ запрещён', 403);

    $title      = trim($data['title']       ?? $vacancy['title']);
    $description= trim($data['description'] ?? $vacancy['description']);
    $salaryFrom = isset($data['salary_from']) ? (int)$data['salary_from'] : $vacancy['salary_from'];
    $salaryTo   = isset($data['salary_to'])   ? (int)$data['salary_to']   : $vacancy['salary_to'];
    $location   = trim($data['location']   ?? $vacancy['location']);
    $category   = trim($data['category']   ?? $vacancy['category']);
    $experience = trim($data['experience'] ?? $vacancy['experience']);
    $employment = $data['employment'] ?? $vacancy['employment'];
    $status     = $data['status']     ?? $vacancy['status'];

    if (!$title || !$description) jsonFail('Название и описание обязательны');

    $db->prepare(
        'UPDATE vacancies SET title=?,description=?,salary_from=?,salary_to=?,location=?,category=?,experience=?,employment=?,status=? WHERE id=?'
    )->execute([$title, $description, $salaryFrom, $salaryTo, $location, $category, $experience, $employment, $status, $id]);

    $stmt->execute([$id]);
    jsonOk($stmt->fetch());
}

// ── DELETE ?action=delete&id= — удалить вакансию ─────────────────────────────

if ($method === 'DELETE' && $action === 'delete' && $id) {
    $auth = requireRole(Role::EMPLOYER, Role::ADMIN);

    $stmt = $db->prepare('SELECT employer_id FROM vacancies WHERE id = ?');
    $stmt->execute([$id]);
    $vacancy = $stmt->fetch();
    if (!$vacancy) jsonFail('Вакансия не найдена', 404);

    if ($auth['role'] === Role::EMPLOYER && $vacancy['employer_id'] !== $auth['uid'])
        jsonFail('Доступ запрещён', 403);

    $db->prepare('DELETE FROM vacancies WHERE id = ?')->execute([$id]);
    jsonOk(['deleted' => true]);
}

jsonFail('Маршрут не найден', 404);

} catch (PDOException $e) {
    jsonFail('Ошибка базы данных: ' . $e->getMessage(), 500);
} catch (Throwable $e) {
    jsonFail('Внутренняя ошибка сервера', 500);
}
