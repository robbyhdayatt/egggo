<?php

date_default_timezone_set('Asia/Jakarta');

// Pengaturan koneksi database
$host = "localhost";
$user = "root";
$pass = "";
$db   = "db_egggo";

$koneksi = mysqli_connect($host, $user, $pass, $db);

if (!$koneksi) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$query_base = $koneksi->query("SELECT nilai_konfigurasi FROM konfigurasi WHERE nama_konfigurasi = 'folder_base'");
if ($query_base && $query_base->num_rows > 0) {
    $folder_base = $query_base->fetch_assoc()['nilai_konfigurasi'];
} else {
    // Fallback jika data tidak ditemukan di database
    $folder_base = '/egggo'; 
}

?>