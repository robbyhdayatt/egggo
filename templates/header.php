<?php
include_once(__DIR__ . '/../config/database.php');

// Proteksi halaman: cek jika user belum login, tendang ke halaman login
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . $folder_base . '/auth/login.php');
    exit();
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>EggGo - Sistem Manajemen Kandang</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="<?php echo $folder_base; ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="wrapper d-flex flex-column min-vh-100 w-100">
    <header class="topbar">
        <button class="mobile-nav-toggle" id="mobileNavToggle">
            <i class="fas fa-bars"></i>
        </button>
    </header>

    <div class="main-content">