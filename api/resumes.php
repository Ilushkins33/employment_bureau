<?php
// api/resumes.php — управление резюме
require_once __DIR__ . "/helpers.php";

try {

$db     = getDb();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

// ── GET ?action=list — список резюме (admin, employer) ───────────────────────

if ($method === 'GET' && $action === 'list') {
    $auth = requireRole(Role::ADMIN, Role::EMPLOYER);

    $where  = ['1=1'];
    $params = [];

    if (!empty($_GET['q'])) {
        $where[]  = '(r.title LIKE ? OR r.description LIKE ? OR r.skills LIKE ?)';
        $params[] = '%' . $_GET['q'] . '%';
        $params[] = '%' . $_GET['q'] . '%';
        $params[] = '%' . $_GET['q'] . '%';
    }
    if (!empty($_GET['location'])) {
        $where[]  = 'r.location LIKE ?';
        $params[] = '%' . $_GET['location'] . '%';
    }
    if (!empty($_GET['experience'])) {
        $where[]  = 'r.experience LIKE ?';
        $params[] = '%' . $_GET['experience'] . '%';
    }
    if (!empty($_GET['salary_to'])) {
        $where[]  = '(r.salary_from IS NULL OR r.salary_from <= ?)';
        $params[] = (int)$_GET['salary_to'];
    }

    // Соискатель видит только свои резюме
    if ($auth['role'] === Role::EMPLOYER) {
        $where[] = "r.status = 'active'";
    }

    $sql = 'SELECT r.*, u.first_name, u.last_name, u.email, u.phone
            FROM resumes r
            JOIN users u ON u.id = r.applicant_id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY r.created_at DESC';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // Декодируем skills из JSON
    foreach ($rows as &$row) {
        $row['skills'] = json_decode($row['skills'] ?? '[]', true);
    }

    jsonOk($rows);
}

// ── GET ?action=my — резюме текущего соискателя ───────────────────────────────

if ($method === 'GET' && $action === 'my') {
    $auth = requireRole(Role::APPLICANT);

    $stmt = $db->prepare('SELECT * FROM resumes WHERE applicant_id = ? ORDER BY created_at DESC');
    $stmt->execute([$auth['uid']]);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row['skills'] = json_decode($row['skills'] ?? '[]', true);
    }

    jsonOk($rows);
}

// ── GET ?action=one&id= — одно резюме ────────────────────────────────────────

if ($method === 'GET' && $action === 'one' && $id) {
    $auth = requireAuth();

    $stmt = $db->prepare(
        'SELECT r.*, u.first_name, u.last_name, u.email, u.phone
         FROM resumes r
         JOIN users u ON u.id = r.applicant_id
         WHERE r.id = ?'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) jsonFail('Резюме не найдено', 404);

    // Соискатель видит только своё
    if ($auth['role'] === Role::APPLICANT && $row['applicant_id'] !== $auth['uid'])
        jsonFail('Доступ запрещён', 403);

    $row['skills'] = json_decode($row['skills'] ?? '[]', true);
    jsonOk($row);
}

// ── POST ?action=create — создать резюме (applicant) ─────────────────────────

if ($method === 'POST' && $action === 'create') {
    $auth = requireRole(Role::APPLICANT);
    $data = body();

    $title      = trim($data['title']       ?? '');
    $description= trim($data['description'] ?? '');
    $skills     = $data['skills'] ?? [];
    $experience = trim($data['experience']  ?? '');
    $education  = trim($data['education']   ?? '');
    $salaryFrom = isset($data['salary_from']) ? (int)$data['salary_from'] : null;
    $location   = trim($data['location']    ?? '');
    $status     = $data['status']           ?? 'active';

    if (!$title || !$description) jsonFail('Название и описание обязательны');

    $db->prepare(
        'INSERT INTO resumes (applicant_id,title,description,skills,experience,education,salary_from,location,status)
         VALUES (?,?,?,?,?,?,?,?,?)'
    )->execute([
        $auth['uid'], $title, $description,
        json_encode($skills, JSON_UNESCAPED_UNICODE),
        $experience, $education, $salaryFrom, $location, $status,
    ]);

    $newId = (int)$db->lastInsertId();
    $stmt  = $db->prepare('SELECT * FROM resumes WHERE id = ?');
    $stmt->execute([$newId]);
    $row = $stmt->fetch();
    $row['skills'] = json_decode($row['skills'], true);
    jsonOk($row, 201);
}

// ── PUT ?action=update&id= — обновить резюме ─────────────────────────────────

if ($method === 'PUT' && $action === 'update' && $id) {
    $auth = requireRole(Role::APPLICANT);
    $data = body();

    $stmt = $db->prepare('SELECT * FROM resumes WHERE id = ?');
    $stmt->execute([$id]);
    $resume = $stmt->fetch();
    if (!$resume) jsonFail('Резюме не найдено', 404);
    if ($resume['applicant_id'] !== $auth['uid']) jsonFail('Доступ запрещён', 403);

    $title      = trim($data['title']       ?? $resume['title']);
    $description= trim($data['description'] ?? $resume['description']);
    $skills     = $data['skills']           ?? json_decode($resume['skills'], true);
    $experience = trim($data['experience']  ?? $resume['experience']);
    $education  = trim($data['education']   ?? $resume['education']);
    $salaryFrom = isset($data['salary_from']) ? (int)$data['salary_from'] : $resume['salary_from'];
    $location   = trim($data['location']    ?? $resume['location']);
    $status     = $data['status']           ?? $resume['status'];

    if (!$title || !$description) jsonFail('Название и описание обязательны');

    $db->prepare(
        'UPDATE resumes SET title=?,description=?,skills=?,experience=?,education=?,salary_from=?,location=?,status=? WHERE id=?'
    )->execute([
        $title, $description,
        json_encode($skills, JSON_UNESCAPED_UNICODE),
        $experience, $education, $salaryFrom, $location, $status, $id,
    ]);

    $stmt->execute([$id]);
    $row = $stmt->fetch();
    $row['skills'] = json_decode($row['skills'], true);
    jsonOk($row);
}

// ── DELETE ?action=delete&id= ─────────────────────────────────────────────────

if ($method === 'DELETE' && $action === 'delete' && $id) {
    $auth = requireRole(Role::APPLICANT, Role::ADMIN);

    $stmt = $db->prepare('SELECT applicant_id FROM resumes WHERE id = ?');
    $stmt->execute([$id]);
    $resume = $stmt->fetch();
    if (!$resume) jsonFail('Резюме не найдено', 404);

    if ($auth['role'] === Role::APPLICANT && $resume['applicant_id'] !== $auth['uid'])
        jsonFail('Доступ запрещён', 403);

    $db->prepare('DELETE FROM resumes WHERE id = ?')->execute([$id]);
    jsonOk(['deleted' => true]);
}

jsonFail('Маршрут не найден', 404);

} catch (PDOException $e) {
    jsonFail('Ошибка базы данных: ' . $e->getMessage(), 500);
} catch (Throwable $e) {
    jsonFail('Внутренняя ошибка сервера', 500);
}
