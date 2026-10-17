<?php
// If already logged in, redirect to dashboard
if (Auth::check()) {
    redirect('/dashboard');
}

$rsProfile = Db::fetchOne("SELECT nama_rs FROM rs_profile LIMIT 1");
$rsName    = $rsProfile['nama_rs'] ?? '';

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verifyOrFail();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        Flash::set('error', 'Username dan password tidak boleh kosong.', 'error');
    } elseif (!Auth::attempt($username, $password)) {
        Flash::set('error', 'Username atau password salah. Silakan coba lagi.', 'error');
    } else {
        $redirect = $_GET['r'] ?? '/dashboard';
        // Safety check: only allow internal redirect
        if (!str_starts_with($redirect, '/')) $redirect = '/dashboard';
        redirect($redirect);
    }
}

$pageTitle = 'Masuk';
view('auth/login', compact('pageTitle', 'rsName'));
