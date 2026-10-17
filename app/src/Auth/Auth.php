<?php
class Auth
{
    public static function attempt(string $username, string $password): bool
    {
        $user = Db::fetchOne(
            "SELECT * FROM users WHERE (username = ? OR email = ?) AND is_active = 1 LIMIT 1",
            [$username, $username]
        );

        if (!$user || !password_verify($password, $user['password'])) {
            return false;
        }

        // Regenerate session ID on login (session fixation prevention)
        session_regenerate_id(true);

        $_SESSION['user_id']   = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['username']  = $user['username'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['logged_at'] = time();

        // Update last_login
        Db::query("UPDATE users SET last_login = NOW() WHERE id = ?", [$user['id']]);

        return true;
    }

    public static function check(): bool
    {
        return !empty($_SESSION['user_id']);
    }

    public static function requireAuth(): void
    {
        if (!self::check()) {
            header('Location: /login?r=' . urlencode($_SERVER['REQUEST_URI']));
            exit;
        }
    }

    public static function requireAdmin(): void
    {
        self::requireAuth();
        if (($_SESSION['user_role'] ?? '') !== 'admin') {
            http_response_code(403);
            require VIEWS . '/errors/403.php';
            exit;
        }
    }

    public static function user(): array
    {
        return [
            'id'   => $_SESSION['user_id']   ?? null,
            'name' => $_SESSION['user_name'] ?? '',
            'role' => $_SESSION['user_role'] ?? '',
            'username' => $_SESSION['username'] ?? '',
        ];
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 3600,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function isAdmin(): bool
    {
        return ($_SESSION['user_role'] ?? '') === 'admin';
    }
}
