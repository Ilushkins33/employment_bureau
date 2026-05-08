<?php
// api/interviews.php — назначение и управление собеседованиями
require_once __DIR__ . '/helpers.php';

try {

$db     = getDb();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

// ── GET ?action=list — список собеседований ───────────────────────────────────
// admin: все; employer: по своим вакансиям; applicant: свои

if ($method === 'GET' && $action === 'list') {
    $auth   = requireAuth();
    $where  = ['1=1'];
    $params = [];

    if ($auth['role'] === Role::APPLICANT) {
        $where[]  = 'i.applicant_id = ?';
        $params[] = $auth['uid'];
    } elseif ($auth['role'] === Role::EMPLOYER) {
        $where[]  = 'i.employer_id = ?';
        $params[] = $auth['uid'];
    }

    if (!empty($_GET['status'])) {
        $where[]  = 'i.status = ?';
        $params[] = $_GET['status'];
    }
    if (!empty($_GET['vacancy_id'])) {
        $where[]  = 'i.vacancy_id = ?';
        $params[] = (int)$_GET['vacancy_id'];
    }

    $sql = 'SELECT i.*,
                   v.title         AS vacancy_title,
                   ua.first_name   AS applicant_first,
                   ua.last_name    AS applicant_last,
                   ua.email        AS applicant_email,
                   ua.phone        AS applicant_phone,
                   ue.first_name   AS employer_first,
                   ue.last_name    AS employer_last,
                   ue.company      AS employer_company
            FROM interviews i
            JOIN vacancies v  ON v.id  = i.vacancy_id
            JOIN users ua     ON ua.id = i.applicant_id
            JOIN users ue     ON ue.id = i.employer_id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY i.scheduled_at ASC';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    jsonOk($stmt->fetchAll());
}

// ── GET ?action=one&id= — одно собеседование ─────────────────────────────────

if ($method === 'GET' && $action === 'one' && $id) {
    $auth = requireAuth();

    $stmt = $db->prepare(
        'SELECT i.*,
                v.title        AS vacancy_title,
                ua.first_name  AS applicant_first,
                ua.last_name   AS applicant_last,
                ua.email       AS applicant_email,
                ua.phone       AS applicant_phone,
                ue.first_name  AS employer_first,
                ue.last_name   AS employer_last,
                ue.company     AS employer_company
         FROM interviews i
         JOIN vacancies v  ON v.id  = i.vacancy_id
         JOIN users ua     ON ua.id = i.applicant_id
         JOIN users ue     ON ue.id = i.employer_id
         WHERE i.id = ?'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) jsonFail('Собеседование не найдено', 404);

    if ($auth['role'] === Role::APPLICANT && $row['applicant_id'] !== $auth['uid'])
        jsonFail('Доступ запрещён', 403);
    if ($auth['role'] === Role::EMPLOYER && $row['employer_id'] !== $auth['uid'])
        jsonFail('Доступ запрещён', 403);

    jsonOk($row);
}

// ── POST ?action=schedule — назначить собеседование (employer, admin) ─────────

if ($method === 'POST' && $action === 'schedule') {
    $auth = requireRole(Role::EMPLOYER, Role::ADMIN);
    $data = body();

    $applicationId = isset($data['application_id']) ? (int)$data['application_id'] : null;
    $scheduledAt   = trim($data['scheduled_at'] ?? '');
    $location      = trim($data['location'] ?? '');
    $format        = $data['format'] ?? 'office';
    $notes         = trim($data['notes'] ?? '');

    if (!$applicationId) jsonFail('Укажите application_id');
    if (!$scheduledAt)   jsonFail('Укажите дату и время собеседования');
    if (!in_array($format, ['office', 'online', 'phone']))
        jsonFail('Недопустимый формат собеседования');

    // Проверяем отклик
    $stmt = $db->prepare(
        'SELECT a.*, v.employer_id FROM applications a
         JOIN vacancies v ON v.id = a.vacancy_id
         WHERE a.id = ?'
    );
    $stmt->execute([$applicationId]);
    $app = $stmt->fetch();
    if (!$app) jsonFail('Отклик не найден', 404);

    if ($auth['role'] === Role::EMPLOYER && $app['employer_id'] !== $auth['uid'])
        jsonFail('Доступ запрещён', 403);

    // Нельзя назначить собеседование на отклонённый отклик
    if ($app['status'] === 'rejected')
        jsonFail('Нельзя назначить собеседование на отклонённый отклик');

    // Проверяем, нет ли уже запланированного собеседования по этому отклику
    $dup = $db->prepare(
        "SELECT id FROM interviews WHERE application_id = ? AND status = 'scheduled'"
    );
    $dup->execute([$applicationId]);
    if ($dup->fetch()) jsonFail('По этому отклику уже назначено активное собеседование');

    $db->prepare(
        'INSERT INTO interviews (application_id, vacancy_id, applicant_id, employer_id, scheduled_at, location, format, notes)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        $applicationId,
        $app['vacancy_id'],
        $app['applicant_id'],
        $app['employer_id'],
        $scheduledAt,
        $location ?: null,
        $format,
        $notes ?: null,
    ]);

    $newId = (int)$db->lastInsertId();
    $stmt  = $db->prepare('SELECT * FROM interviews WHERE id = ?');
    $stmt->execute([$newId]);
    jsonOk($stmt->fetch(), 201);
}

// ── PUT ?action=update&id= — изменить детали собеседования (employer, admin) ──

if ($method === 'PUT' && $action === 'update' && $id) {
    $auth = requireRole(Role::EMPLOYER, Role::ADMIN);
    $data = body();

    $stmt = $db->prepare('SELECT * FROM interviews WHERE id = ?');
    $stmt->execute([$id]);
    $interview = $stmt->fetch();
    if (!$interview) jsonFail('Собеседование не найдено', 404);

    if ($auth['role'] === Role::EMPLOYER && $interview['employer_id'] !== $auth['uid'])
        jsonFail('Доступ запрещён', 403);

    $scheduledAt = trim($data['scheduled_at'] ?? $interview['scheduled_at']);
    $location    = trim($data['location']     ?? $interview['location'] ?? '');
    $format      = $data['format']            ?? $interview['format'];
    $notes       = trim($data['notes']        ?? $interview['notes'] ?? '');
    $status      = $data['status']            ?? $interview['status'];

    if (!in_array($format, ['office', 'online', 'phone']))
        jsonFail('Недопустимый формат собеседования');
    if (!in_array($status, ['scheduled', 'completed', 'cancelled']))
        jsonFail('Недопустимый статус');

    $db->prepare(
        'UPDATE interviews SET scheduled_at=?, location=?, format=?, notes=?, status=? WHERE id=?'
    )->execute([$scheduledAt, $location ?: null, $format, $notes ?: null, $status, $id]);

    $stmt->execute([$id]);
    jsonOk($stmt->fetch());
}

// ── DELETE ?action=cancel&id= — отменить собеседование ───────────────────────

if ($method === 'DELETE' && $action === 'cancel' && $id) {
    $auth = requireRole(Role::EMPLOYER, Role::ADMIN);

    $stmt = $db->prepare('SELECT * FROM interviews WHERE id = ?');
    $stmt->execute([$id]);
    $interview = $stmt->fetch();
    if (!$interview) jsonFail('Собеседование не найдено', 404);

    if ($auth['role'] === Role::EMPLOYER && $interview['employer_id'] !== $auth['uid'])
        jsonFail('Доступ запрещён', 403);

    $db->prepare("UPDATE interviews SET status = 'cancelled' WHERE id = ?")->execute([$id]);
    jsonOk(['cancelled' => true]);
}

jsonFail('Маршрут не найден', 404);

} catch (PDOException $e) {
    jsonFail('Ошибка базы данных: ' . $e->getMessage(), 500);
} catch (Throwable $e) {
    jsonFail('Внутренняя ошибка сервера', 500);
}
