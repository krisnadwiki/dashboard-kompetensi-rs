<?php
class Db
{
    private static ?PDO $pdo = null;

    public static function get(): PDO
    {
        if (self::$pdo === null) {
            $host = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? 'db');
            $port = getenv('DB_PORT') ?: ($_ENV['DB_PORT'] ?? '3306');
            $name = getenv('DB_NAME') ?: ($_ENV['DB_NAME'] ?? 'kompetensi_rs');
            $user = getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? 'kompetensi');
            $pass = getenv('DB_PASS') ?: ($_ENV['DB_PASS'] ?? 'Kompetensi@2026!');

            $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
            self::$pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }
        return self::$pdo;
    }

    public static function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::get()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function fetchOne(string $sql, array $params = []): ?array
    {
        $row = self::query($sql, $params)->fetch();
        return $row ?: null;
    }

    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    public static function lastId(): string|false
    {
        return self::get()->lastInsertId();
    }
}
