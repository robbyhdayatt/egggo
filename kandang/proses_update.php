<?php
include '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . $folder_base . '/auth/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id_kandang'];
    $nama_kandang = $_POST['nama_kandang'];
    $tgl_masuk_awal = $_POST['tgl_masuk_awal'];
    $populasi_awal = $_POST['populasi_awal'];
    
    // Konversi umur dari minggu (input form) ke hari (untuk disimpan di DB)
    $umur_ayam_awal_minggu = $_POST['umur_ayam_awal'];
    $umur_ayam_awal_hari = $umur_ayam_awal_minggu * 7;
    
    $stok_telur_awal_kg = $_POST['stok_telur_awal_kg'];
    $status = $_POST['status'];

    // Query UPDATE sudah disesuaikan, tidak ada lagi stok_pakan_awal_kg
    $stmt = $koneksi->prepare("UPDATE kandang SET nama_kandang = ?, tgl_masuk_awal = ?, populasi_awal = ?, umur_ayam_awal = ?, stok_telur_awal_kg = ?, status = ? WHERE id_kandang = ?");
    
    // bind_param juga sudah disesuaikan
    $stmt->bind_param("ssiidsi", $nama_kandang, $tgl_masuk_awal, $populasi_awal, $umur_ayam_awal_hari, $stok_telur_awal_kg, $status, $id);
    
    if ($stmt->execute()) {
        header('Location: index.php?status=sukses_update');
    } else {
        echo "Gagal mengupdate data!";
    }
} else {
    header('Location: index.php');
}
?>