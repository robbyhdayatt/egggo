<?php

if (!defined('DATABASE_CONFIG_INCLUDED')) {
    include_once(__DIR__ . '/../config/database.php');
    define('DATABASE_CONFIG_INCLUDED', true);
}

// 1. Cek Login
if (!isset($_SESSION['user_id'])) {
    if (!isset($folder_base)) {
         if (isset($koneksi)) {
             $query_base = $koneksi->query("SELECT nilai_konfigurasi FROM konfigurasi WHERE nama_konfigurasi = 'folder_base'");
             if ($query_base && $query_base->num_rows > 0) { $folder_base = $query_base->fetch_assoc()['nilai_konfigurasi']; }
             else { $folder_base = '/egggo'; }
         } else { $folder_base = '/egggo'; }
    }
    header('Location: ' . $folder_base . '/auth/login.php');
    exit();
}

// 2. Ambil Role, ID Kandang Ditugaskan, dan User ID
$user_id = $_SESSION['user_id']; // Ambil user ID
$user_role = $_SESSION['role'] ?? 'Karyawan';
$assigned_kandang_id = ($user_role === 'Karyawan') ? ($_SESSION['assigned_kandang_id'] ?? null) : null;

global $current_user_id;
global $current_user_role;
global $current_assigned_kandang_id;
$current_user_id = $user_id;
$current_user_role = $user_role;
$current_assigned_kandang_id = $assigned_kandang_id;

// 3. Definisikan Halaman yang Terbatas untuk Karyawan (Secara Umum)
$restricted_dirs_for_karyawan = [
    'kandang',
    'manajemen_user',
    'tujuan_wa',
    'kategori_pengeluaran',
    'approval'
];

// 4. Cek Hak Akses Halaman Menu
$current_dir = basename(dirname($_SERVER['PHP_SELF']));

if ($user_role === 'Karyawan' && in_array($current_dir, $restricted_dirs_for_karyawan)) {
    $_SESSION['error_message'] = "Anda tidak memiliki hak akses ke halaman menu tersebut.";
    if (!isset($folder_base)) { $folder_base = '/egggo'; }
    header('Location: ' . $folder_base . '/index.php');
    exit();
}

// 5. Validasi Tambahan: Jika Karyawan tapi ID kandang tidak valid
if ($user_role === 'Karyawan' && $assigned_kandang_id === null) {
     session_destroy(); // Hancurkan sesi
     if (!isset($folder_base)) { $folder_base = '/egggo'; }
     header('Location: ' . $folder_base . '/auth/login.php?status=no_assignment');
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
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.bootstrap5.min.css">
    <link rel="stylesheet" href="<?php echo $folder_base; ?>/assets/css/style.css">

    <script src="https://code.jquery.com/jquery-3.7.0.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script> </head>
<body>

<?php include_once(__DIR__ . '/sidebar.php'); ?>

<div class="wrapper d-flex flex-column min-vh-100 w-100">
    <header class="topbar">
        </header>

    <div class="main-content">