<?php
// api/auth.php — регистрация, вход, текущий пользователь
require_once __DIR__ . "/helpers.php";

try {

$db     = getDb();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// ── GET ?action=me ────────────────────────────────────────────────────────────

if ($method === 'GET' && $action === 'me') {
    $auth = requireAuth();

    $stmt = $db->prepare('SELECT id,email,role,first_name,last_name,phone,company,created_at FROM users WHERE id = ?');
    $stmt->execute([$auth['uid']]);
    $user = $stmt->fetch();

    if (!$user) jsonFail('Пользователь не найден', 404);

    $user['name'] = $user['first_name'] . ' ' . $user['last_name'];
    jsonOk($user);
}

// ── POST ?action=register ─────────────────────────────────────────────────────

if ($method === 'POST' && $action === 'register') {
    $data = body();

    $email     = strtolower(trim($data['email'] ?? ''));
    $password  = $data['password'] ?? '';
    $role      = $data['role'] ?? '';
    $firstName = trim($data['first_name'] ?? '');
    $lastName  = trim($data['last_name'] ?? '');
    $phone     = trim($data['phone'] ?? '');
    $company   = trim($data['company'] ?? '');

    if (!$email || !$password || !$role || !$firstName || !$lastName)
        jsonFail('Заполните все обязательные поля');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        jsonFail('Некорректный email');
    if (strlen($password) < 8)
        jsonFail('Пароль минимум 8 символов');
    if (!in_array($role, [Role::EMPLOYER, Role::APPLICANT]))
        jsonFail('Недопустимая роль');

    $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) jsonFail('Email уже зарегистрирован');

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $db->prepare('INSERT INTO users (email,password,role,first_name,last_name,phone,company) VALUES (?,?,?,?,?,?,?)')
       ->execute([$email, $hash, $role, $firstName, $lastName, $phone ?: null, $company ?: null]);

    $userId = (int)$db->lastInsertId();
    $token  = makeToken($userId, $role);

    jsonOk([
        'token' => $token,
        'user'  => [
            'id'         => $userId,
            'email'      => $email,
            'role'       => $role,
            'first_name' => $firstName,
            'last_name'  => $lastName,
            'name'       => $firstName . ' ' . $lastName,
            'phone'      => $phone ?: null,
            'company'    => $company ?: null,
        ],
    ], 201);
}

// ── POST ?action=login ────────────────────────────────────────────────────────

if ($method === 'POST' && $action === 'login') {
    $data = body();

    $email    = strtolower(trim($data['email'] ?? ''));
    $password = $data['password'] ?? '';

    if (!$email || !$password) jsonFail('Email и пароль обязательны');

    $stmt = $db->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $row = $stmt->fetch();

    if (!$row || !password_verify($password, $row['password']))
        jsonFail('Неверный логин или пароль', 401);

    $token = makeToken($row['id'], $row['role']);

    jsonOk([
        'token' => $token,
        'user'  => [
            'id'         => $row['id'],
            'email'      => $row['email'],
            'role'       => $row['role'],
            'first_name' => $row['first_name'],
            'last_name'  => $row['last_name'],
            'name'       => $row['first_name'] . ' ' . $row['last_name'],
            'phone'      => $row['phone'],
            'company'    => $row['company'],
        ],
    ]);
}

jsonFail('Маршрут не найден', 404);

} catch (PDOException $e) {
    jsonFail('Ошибка базы данных: ' . $e->getMessage(), 500);
} catch (Throwable $e) {
    jsonFail('Внутренняя ошибка сервера', 500);
}
