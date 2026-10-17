<?php
// ─── View helper ─────────────────────────────────────────────────────────────
function view(string $template, array $data = []): void
{
    extract($data, EXTR_SKIP);
    $file = VIEWS . '/' . ltrim($template, '/') . '.php';
    if (!file_exists($file)) {
        throw new RuntimeException("View not found: $file");
    }
    require $file;
}

// ─── Escape helper ──────────────────────────────────────────────────────────
function e(mixed $val): string
{
    return htmlspecialchars((string)($val ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ─── Redirect helper ────────────────────────────────────────────────────────
function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

// ─── Active nav helper ───────────────────────────────────────────────────────
function navActive(string $path): string
{
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    return str_starts_with($uri, $path) ? 'active' : '';
}

// ─── Format date Indonesia ──────────────────────────────────────────────────
function tglIndo(?string $date): string
{
    if (!$date) return '-';
    $bulan = ['', 'Januari','Februari','Maret','April','Mei','Juni',
              'Juli','Agustus','September','Oktober','November','Desember'];
    [$y, $m, $d] = explode('-', substr($date, 0, 10));
    return (int)$d . ' ' . $bulan[(int)$m] . ' ' . $y;
}

// ─── Application URL helper ─────────────────────────────────────────────────
function appUrl(string $path = ''): string
{
    static $baseUrl = null;
    if ($baseUrl === null) {
        // 1. Prioritas utama: Nilai dari environment variable APP_URL
        $envUrl = getenv('APP_URL') ?: ($_ENV['APP_URL'] ?? '');

        // 2. Jika tidak ada di env server, coba baca langsung file .env
        if (!$envUrl) {
            $candidates = [
                __DIR__ . '/../.env',
                __DIR__ . '/../../.env',
                defined('ROOT') ? ROOT . '/.env' : null,
                defined('ROOT') ? dirname(ROOT) . '/.env' : null,
                '/var/www/html/.env',
                '/var/www/.env'
            ];
            foreach ($candidates as $filePath) {
                if ($filePath && file_exists($filePath) && is_readable($filePath)) {
                    $raw = @file_get_contents($filePath);
                    if ($raw && preg_match('/^\s*APP_URL\s*=\s*([^\r\n#]+)/m', $raw, $matches)) {
                        $candidateUrl = trim($matches[1], " \t\n\r\0\x0B\"'");
                        if (!empty($candidateUrl)) {
                            $envUrl = $candidateUrl;
                            break;
                        }
                    }
                }
            }
        }

        if ($envUrl) {
            $baseUrl = rtrim($envUrl, '/');
        } else {
            // 3. Fallback cerdas: Deteksi protokol (HTTPS / HTTP) dan Host request saat ini
            $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
                || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
                || (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) === 'on');

            $scheme = $isHttps ? 'https' : 'http';
            $host   = $_SERVER['HTTP_HOST'] ?? 'localhost:8088';
            $baseUrl = $scheme . '://' . $host;
        }
    }

    return $path ? $baseUrl . '/' . ltrim($path, '/') : $baseUrl;
}
