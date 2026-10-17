<?php
class Flash
{
    public static function set(string $key, string $message, string $type = 'info'): void
    {
        $_SESSION['flash'][$key] = ['msg' => $message, 'type' => $type];
    }

    public static function get(string $key): ?array
    {
        if (isset($_SESSION['flash'][$key])) {
            $flash = $_SESSION['flash'][$key];
            unset($_SESSION['flash'][$key]);
            return $flash;
        }
        return null;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION['flash'][$key]);
    }

    public static function render(string $key): string
    {
        $f = self::get($key);
        if (!$f) return '';
        $type = htmlspecialchars($f['type']);
        $msg  = htmlspecialchars($f['msg']);
        $icon = match($f['type']) {
            'success' => 'check-circle',
            'error'   => 'x-circle',
            'warning' => 'alert-triangle',
            default   => 'info',
        };
        return <<<HTML
        <div class="flash-message flash-{$type}" role="alert">
          <span class="flash-icon"><i data-lucide="{$icon}"></i></span>
          <span>{$msg}</span>
          <button class="flash-close" onclick="this.parentElement.remove()">&times;</button>
        </div>
        HTML;
    }
}
