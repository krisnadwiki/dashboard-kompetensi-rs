<?php
// ─── Bootstrap ──────────────────────────────────────────────────────────────
define('ROOT',     dirname(__DIR__));
define('VIEWS',    ROOT . '/views');
define('SRC',      ROOT . '/src');
define('APP_VER',  '2.0.0');

// Session
session_start([
    'cookie_lifetime' => 60 * 60 * 8,   // 8 hours
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'use_strict_mode' => false,
]);

// Autoload
spl_autoload_register(function (string $class) {
    $map = [
        'Db'         => SRC . '/Database/Db.php',
        'Auth'       => SRC . '/Auth/Auth.php',
        'Router'     => SRC . '/Router.php',
        'Request'    => SRC . '/Request.php',
        'Response'   => SRC . '/Response.php',
        'Csrf'       => SRC . '/Csrf.php',
        'Flash'      => SRC . '/Flash.php',
    ];
    if (isset($map[$class])) require_once $map[$class];
});

require_once SRC . '/helpers.php';

// ─── Router ─────────────────────────────────────────────────────────────────
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Health endpoint
if ($uri === '/health') {
    header('Content-Type: text/plain');
    echo 'OK';
    exit;
}

$router = new Router($uri, $method);

// Public routes (accessible without login)
$router->get('/view',        SRC . '/Public/view_controller.php');
$router->get('/kompetensi',  SRC . '/Public/view_controller.php');

// Auth routes
$router->get('/',           SRC . '/Auth/login_controller.php');
$router->get('/login',      SRC . '/Auth/login_controller.php');
$router->post('/login',     SRC . '/Auth/login_controller.php');
$router->get('/logout',     SRC . '/Auth/logout_controller.php');

// Dashboard routes (protected)
$router->get('/dashboard',              SRC . '/Dashboard/dashboard_controller.php');
$router->get('/dashboard/kompetensi',   SRC . '/Dashboard/kompetensi_controller.php');

// Master routes (protected)
$router->get('/master',             SRC . '/Master/master_controller.php');
$router->get('/master/profil-rs',   SRC . '/Master/profil_rs_controller.php');
$router->post('/master/profil-rs',  SRC . '/Master/profil_rs_controller.php');
$router->get('/master/users',       SRC . '/Master/users_controller.php');
$router->post('/master/users',      SRC . '/Master/users_controller.php');
$router->post('/master/users/delete', SRC . '/Master/users_controller.php');
$router->get('/master/sinkronisasi',              SRC . '/Master/sinkronisasi_controller.php');
$router->post('/master/sinkronisasi',             SRC . '/Master/sinkronisasi_controller.php');
$router->post('/master/sinkronisasi/delete-log',  SRC . '/Master/sinkronisasi_controller.php');
$router->post('/master/sinkronisasi/clear-logs',  SRC . '/Master/sinkronisasi_controller.php');
$router->post('/master/sinkronisasi/tarik-sirs',  SRC . '/Master/sinkronisasi_controller.php');
$router->post('/master/snapshots/create',         SRC . '/Master/snapshots_controller.php');
$router->post('/master/snapshots/delete',         SRC . '/Master/snapshots_controller.php');

// API
$router->post('/api/sync-log',      SRC . '/Api/sync_log_controller.php');
$router->get('/api/snapshot-data',  SRC . '/Api/snapshot_data_controller.php');
$router->post('/api/ingest-sirs',   SRC . '/Api/ingest_sirs_controller.php');

$router->dispatch();
