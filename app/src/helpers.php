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
