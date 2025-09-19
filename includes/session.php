<?php
// includes/session.php
session_start();

function checkAuth() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }
}

function checkAdminAuth() {
    checkAuth();
    if ($_SESSION['role'] !== 'admin') {
        header('Location: dashboard.php');
        exit();
    }
}

function getCurrentUser() {
    if (isset($_SESSION['user_id'])) {
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'email' => $_SESSION['email'],
            'role' => $_SESSION['role']
        ];
    }
    return null;
}
?>