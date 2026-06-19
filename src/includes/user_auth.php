<?php
require_once __DIR__ . '/bootstrap.php';

function user_login(string $username, string $password): bool
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = :u OR email = :u LIMIT 1');
    $stmt->execute([':u' => $username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['real_name'] = $user['real_name'];
        $_SESSION['user_role'] = $user['role'];
        return true;
    }

    return false;
}

function user_register(string $username, string $email, string $password, string $real_name = ''): array
{
    $pdo = db();
    
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = :u OR email = :e');
    $stmt->execute([':u' => $username, ':e' => $email]);
    if ($stmt->fetchColumn() > 0) {
        return ['success' => false, 'message' => '用户名或邮箱已存在'];
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare('INSERT INTO users (username, email, password_hash, real_name) VALUES (:u, :e, :p, :r)');
    
    try {
        $stmt->execute([
            ':u' => $username,
            ':e' => $email,
            ':p' => $hash,
            ':r' => $real_name,
        ]);
        return ['success' => true, 'message' => '注册成功'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => '注册失败：' . $e->getMessage()];
    }
}

function user_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function is_user_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

function require_user_login(): void
{
    if (!is_user_logged_in()) {
        header('Location: /login.php');
        exit;
    }
}

function current_user(): ?array
{
    if (!is_user_logged_in()) {
        return null;
    }
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'real_name' => $_SESSION['real_name'] ?? $_SESSION['username'],
        'role' => $_SESSION['user_role'] ?? 'member',
    ];
}

function is_admin(): bool
{
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function get_user(int $id): ?array
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT id, username, email, real_name, role, created_at FROM users WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function get_all_users(): array
{
    $pdo = db();
    $stmt = $pdo->query('SELECT id, username, email, real_name, role, created_at FROM users ORDER BY created_at DESC');
    return $stmt->fetchAll();
}

function get_users_by_ids(array $ids): array
{
    if (empty($ids)) {
        return [];
    }
    $pdo = db();
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT id, username, real_name FROM users WHERE id IN ($placeholders)");
    $stmt->execute($ids);
    return $stmt->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);
}
