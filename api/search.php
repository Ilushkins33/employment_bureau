<?php
// api/search.php — поиск и сопоставление кандидатов с вакансиями
require_once __DIR__ . "/helpers.php";

try {

$db     = getDb();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// ── GET ?action=vacancies — поиск вакансий ────────────────────────────────────

if ($method === 'GET' && $action === 'vacancies') {
    $where  = ["v.status = 'open'"];
    $params = [];

    if (!empty($_GET['q'])) {
        $where[]  = '(v.title LIKE ? OR v.description LIKE ? OR v.category LIKE ?)';
        $params[] = '%' . $_GET['q'] . '%';
        $params[] = '%' . $_GET['q'] . '%';
        $params[] = '%' . $_GET['q'] . '%';
    }
    if (!empty($_GET['location'])) {
        $where[]  = 'v.location LIKE ?';
        $params[] = '%' . $_GET['location'] . '%';
    }
    if (!empty($_GET['category'])) {
        $where[]  = 'v.category = ?';
        $params[] = $_GET['category'];
    }
    if (!empty($_GET['employment'])) {
        $where[]  = 'v.employment = ?';
        $params[] = $_GET['employment'];
    }
    if (!empty($_GET['experience'])) {
        $where[]  = 'v.experience = ?';
        $params[] = $_GET['experience'];
    }
    if (!empty($_GET['salary_from'])) {
        $where[]  = '(v.salary_to IS NULL OR v.salary_to >= ?)';
        $params[] = (int)$_GET['salary_from'];
    }

    $sql = 'SELECT v.*, u.company, u.first_name, u.last_name,
                   (SELECT COUNT(*) FROM applications a WHERE a.vacancy_id = v.id) AS applications_count
            FROM vacancies v
            JOIN users u ON u.id = v.employer_id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY v.created_at DESC
            LIMIT 50';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    jsonOk($stmt->fetchAll());
}

// ── GET ?action=resumes — поиск резюме (admin, employer) ─────────────────────

if ($method === 'GET' && $action === 'resumes') {
    $auth   = requireRole(Role::ADMIN, Role::EMPLOYER);
    $where  = ["r.status = 'active'"];
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
    if (!empty($_GET['salary_to'])) {
        $where[]  = '(r.salary_from IS NULL OR r.salary_from <= ?)';
        $params[] = (int)$_GET['salary_to'];
    }
    if (!empty($_GET['skill'])) {
        $where[]  = 'r.skills LIKE ?';
        $params[] = '%' . $_GET['skill'] . '%';
    }

    $sql = 'SELECT r.*, u.first_name, u.last_name, u.email, u.phone
            FROM resumes r
            JOIN users u ON u.id = r.applicant_id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY r.created_at DESC
            LIMIT 50';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row['skills'] = json_decode($row['skills'] ?? '[]', true);
    }

    jsonOk($rows);
}

// ── GET ?action=match&vacancy_id= — сопоставление кандидатов с вакансией ─────
// Алгоритм: считаем score по совпадению навыков, зарплате, локации

if ($method === 'GET' && $action === 'match') {
    $auth      = requireRole(Role::ADMIN, Role::EMPLOYER);
    $vacancyId = isset($_GET['vacancy_id']) ? (int)$_GET['vacancy_id'] : null;
    if (!$vacancyId) jsonFail('Укажите vacancy_id');

    // Получаем вакансию
    $stmt = $db->prepare('SELECT * FROM vacancies WHERE id = ?');
    $stmt->execute([$vacancyId]);
    $vacancy = $stmt->fetch();
    if (!$vacancy) jsonFail('Вакансия не найдена', 404);

    // Работодатель — только свои вакансии
    if ($auth['role'] === Role::EMPLOYER && $vacancy['employer_id'] !== $auth['uid'])
        jsonFail('Доступ запрещён', 403);

    // Все активные резюме
    $stmt = $db->prepare(
        'SELECT r.*, u.first_name, u.last_name, u.email, u.phone
         FROM resumes r
         JOIN users u ON u.id = r.applicant_id
         WHERE r.status = ?'
    );
    $stmt->execute(['active']);
    $resumes = $stmt->fetchAll();

    // Ключевые слова из вакансии
    $vacancyWords = array_unique(array_filter(
        preg_split('/[\s,\.]+/', strtolower($vacancy['title'] . ' ' . $vacancy['description'])),
        fn($w) => mb_strlen($w) > 3
    ));

    $results = [];
    foreach ($resumes as $resume) {
        $skills = json_decode($resume['skills'] ?? '[]', true);
        $score  = 0;
        $matched = [];

        // 1. Совпадение навыков с названием/описанием вакансии
        foreach ($skills as $skill) {
            $skillLower = strtolower($skill);
            foreach ($vacancyWords as $word) {
                if (str_contains($skillLower, $word) || str_contains($word, $skillLower)) {
                    $score += 20;
                    $matched[] = $skill;
                    break;
                }
            }
        }

        // 2. Совпадение локации
        if ($vacancy['location'] && $resume['location']) {
            if (stripos($resume['location'], $vacancy['location']) !== false ||
                stripos($vacancy['location'], $resume['location']) !== false) {
                $score += 15;
            }
        }

        // 3. Совпадение по зарплате
        if ($vacancy['salary_from'] && $resume['salary_from']) {
            if ($resume['salary_from'] <= ($vacancy['salary_to'] ?? PHP_INT_MAX) &&
                $resume['salary_from'] >= ($vacancy['salary_from'] * 0.7)) {
                $score += 10;
            }
        }

        // 4. Слова из резюме совпадают с вакансией
        $resumeText  = strtolower($resume['title'] . ' ' . $resume['description']);
        $resumeWords = array_unique(array_filter(
            preg_split('/[\s,\.]+/', $resumeText),
            fn($w) => mb_strlen($w) > 3
        ));
        $commonWords = array_intersect($vacancyWords, $resumeWords);
        $score += count($commonWords) * 3;

        $resume['skills']        = $skills;
        $resume['match_score']   = min($score, 100);
        $resume['matched_skills']= array_unique($matched);
        $results[] = $resume;
    }

    // Сортируем по score DESC
    usort($results, fn($a, $b) => $b['match_score'] <=> $a['match_score']);

    jsonOk([
        'vacancy' => $vacancy,
        'results' => array_slice($results, 0, 20),
    ]);
}

jsonFail('Маршрут не найден', 404);

} catch (PDOException $e) {
    jsonFail('Ошибка базы данных: ' . $e->getMessage(), 500);
} catch (Throwable $e) {
    jsonFail('Внутренняя ошибка сервера', 500);
}
