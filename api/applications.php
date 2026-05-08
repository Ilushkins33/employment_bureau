<?php
// api/applications.php — отклики на вакансии
require_once __DIR__ . "/helpers.php";

try {

$db     = getDb();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

// ── GET ?action=list — список откликов ────────────────────────────────────────
// admin: все; employer: по своим вакансиям; applicant: свои отклики

if ($method === 'GET' && $action === 'list') {
    $auth   = requireAuth();
    $where  = ['1=1'];
    $params = [];

    if ($auth['role'] === Role::APPLICANT) {
        $where[]  = 'a.applicant_id = ?';
        $params[] = $auth['uid'];
    } elseif ($auth['role'] === Role::EMPLOYER) {
        $where[]  = 'v.employer_id = ?';
        $params[] = $auth['uid'];
    }

    if (!empty($_GET['vacancy_id'])) {
        $where[]  = 'a.vacancy_id = ?';
        $params[] = (int)$_GET['vacancy_id'];
    }
    if (!empty($_GET['status'])) {
        $where[]  = 'a.status = ?';
        $params[] = $_GET['status'];
    }

    $sql = 'SELECT a.*,
                   v.title       AS vacancy_title,
                   v.employer_id,
                   u.first_name  AS applicant_first,
                   u.last_name   AS applicant_last,
                   u.email       AS applicant_email,
                   u.phone       AS applicant_phone,
                   r.title       AS resume_title
            FROM applications a
            JOIN vacancies v ON v.id = a.vacancy_id
            JOIN users u     ON u.id = a.applicant_id
            LEFT JOIN resumes r ON r.id = a.resume_id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY a.created_at DESC';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    jsonOk($stmt->fetchAll());
}

// ── GET ?action=one&id= — один отклик ────────────────────────────────────────

if ($method === 'GET' && $action === 'one' && $id) {
    $auth = requireAuth();

    $stmt = $db->prepare(
        'SELECT a.*,
                v.title       AS vacancy_title,
                v.employer_id,
                u.first_name  AS applicant_first,
                u.last_name   AS applicant_last,
                u.email       AS applicant_email,
                r.title       AS resume_title,
                r.skills      AS resume_skills,
                r.description AS resume_description
         FROM applications a
         JOIN vacancies v ON v.id = a.vacancy_id
         JOIN users u     ON u.id = a.applicant_id
         LEFT JOIN resumes r ON r.id = a.resume_id
         WHERE a.id = ?'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) jsonFail('Отклик не найден', 404);

    // Проверка доступа
    if ($auth['role'] === Role::APPLICANT && $row['applicant_id'] !== $auth['uid'])
        jsonFail('Доступ запрещён', 403);
    if ($auth['role'] === Role::EMPLOYER && $row['employer_id'] !== $auth['uid'])
        jsonFail('Доступ запрещён', 403);

    if ($row['resume_skills']) {
        $row['resume_skills'] = json_decode($row['resume_skills'], true);
    }

    jsonOk($row);
}

// ── POST ?action=apply — подать отклик (applicant) ────────────────────────────

if ($method === 'POST' && $action === 'apply') {
    $auth = requireRole(Role::APPLICANT);
    $data = body();

    $vacancyId   = isset($data['vacancy_id'])   ? (int)$data['vacancy_id']   : null;
    $resumeId    = isset($data['resume_id'])     ? (int)$data['resume_id']    : null;
    $coverLetter = trim($data['cover_letter']    ?? '');

    if (!$vacancyId) jsonFail('Укажите вакансию');

    // Вакансия существует и открыта
    $stmt = $db->prepare("SELECT id, status FROM vacancies WHERE id = ?");
    $stmt->execute([$vacancyId]);
    $vacancy = $stmt->fetch();
    if (!$vacancy) jsonFail('Вакансия не найдена', 404);
    if ($vacancy['status'] !== 'open') jsonFail('Вакансия закрыта');

    // Проверяем что резюме принадлежит соискателю
    if ($resumeId) {
        $rStmt = $db->prepare('SELECT id FROM resumes WHERE id = ? AND applicant_id = ?');
        $rStmt->execute([$resumeId, $auth['uid']]);
        if (!$rStmt->fetch()) jsonFail('Резюме не найдено');
    }

    // Нет дублей
    $dup = $db->prepare('SELECT id FROM applications WHERE vacancy_id = ? AND applicant_id = ?');
    $dup->execute([$vacancyId, $auth['uid']]);
    if ($dup->fetch()) jsonFail('Вы уже откликались на эту вакансию');

    $db->prepare(
        'INSERT INTO applications (vacancy_id, applicant_id, resume_id, cover_letter) VALUES (?,?,?,?)'
    )->execute([$vacancyId, $auth['uid'], $resumeId ?: null, $coverLetter ?: null]);

    $newId = (int)$db->lastInsertId();
    $stmt  = $db->prepare('SELECT * FROM applications WHERE id = ?');
    $stmt->execute([$newId]);
    jsonOk($stmt->fetch(), 201);
}

// ── PUT ?action=status&id= — изменить статус отклика (employer, admin) ────────

if ($method === 'PUT' && $action === 'status' && $id) {
    $auth = requireRole(Role::EMPLOYER, Role::ADMIN);
    $data = body();

    $status = $data['status'] ?? '';
    if (!in_array($status, ['pending', 'accepted', 'rejected']))
        jsonFail('Недопустимый статус');

    $stmt = $db->prepare(
        'SELECT a.*, v.employer_id FROM applications a JOIN vacancies v ON v.id = a.vacancy_id WHERE a.id = ?'
    );
    $stmt->execute([$id]);
    $app = $stmt->fetch();
    if (!$app) jsonFail('Отклик не найден', 404);

    if ($auth['role'] === Role::EMPLOYER && $app['employer_id'] !== $auth['uid'])
        jsonFail('Доступ запрещён', 403);

    $db->prepare('UPDATE applications SET status = ? WHERE id = ?')->execute([$status, $id]);

    // Если вакансия принята — закрываем её автоматически
    if ($status === 'accepted') {
        $db->prepare("UPDATE vacancies SET status = 'closed' WHERE id = ?")
           ->execute([$app['vacancy_id']]);
    }

    $stmt2 = $db->prepare('SELECT * FROM applications WHERE id = ?');
    $stmt2->execute([$id]);
    jsonOk($stmt2->fetch());
}

// ── DELETE ?action=delete&id= — отозвать отклик (applicant) ──────────────────

if ($method === 'DELETE' && $action === 'delete' && $id) {
    $auth = requireRole(Role::APPLICANT, Role::ADMIN);

    $stmt = $db->prepare('SELECT applicant_id, status FROM applications WHERE id = ?');
    $stmt->execute([$id]);
    $app = $stmt->fetch();
    if (!$app) jsonFail('Отклик не найден', 404);

    if ($auth['role'] === Role::APPLICANT && $app['applicant_id'] !== $auth['uid'])
        jsonFail('Доступ запрещён', 403);
    if ($auth['role'] === Role::APPLICANT && $app['status'] !== 'pending')
        jsonFail('Нельзя отозвать уже рассмотренный отклик');

    $db->prepare('DELETE FROM applications WHERE id = ?')->execute([$id]);
    jsonOk(['deleted' => true]);
}

jsonFail('Маршрут не найден', 404);

} catch (PDOException $e) {
    jsonFail('Ошибка базы данных: ' . $e->getMessage(), 500);
} catch (Throwable $e) {
    jsonFail('Внутренняя ошибка сервера', 500);
}
