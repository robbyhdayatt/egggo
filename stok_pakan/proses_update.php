<?php
include '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . $folder_base . '/auth/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_stok = $_POST['id_stok'];
    $tanggal_beli = $_POST['tanggal_beli'];
    $nama_pakan = $_POST['nama_pakan'];
    $jumlah_kg = $_POST['jumlah_kg'];

    // <<<--- PERUBAHAN DI SINI: Hapus titik sebelum menyimpan ---<<<
    $harga_total = str_replace('.', '', $_POST['harga_total']);

    $stmt = $koneksi->prepare("UPDATE stok_pakan SET tanggal_beli = ?, nama_pakan = ?, jumlah_kg = ?, harga_total = ? WHERE id_stok = ?");
    $stmt->bind_param("ssdis", $tanggal_beli, $nama_pakan, $jumlah_kg, $harga_total, $id_stok);
    
    if ($stmt->execute()) {
        header('Location: index.php?status=sukses_update');
    } else {
        echo "Gagal mengupdate data!";
    }
} else {
    header('Location: index.php');
}
?>