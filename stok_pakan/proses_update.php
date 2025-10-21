<?php
include '../templates/header.php';
global $current_user_role, $current_assigned_kandang_id;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_stok = $_POST['id_stok'];
    $id_kandang = $_POST['id_kandang'];
    $tanggal_beli = $_POST['tanggal_beli'];
    $nama_pakan = $_POST['nama_pakan'];
    $jumlah_kg = (float)str_replace(',', '.', $_POST['jumlah_kg']);
    $harga_per_kg = str_replace('.', '', $_POST['harga_per_kg']);
    $harga_total = str_replace('.', '', $_POST['harga_total']);

    if ($current_user_role === 'Karyawan' && $id_kandang != $current_assigned_kandang_id) {
         header('Location: index.php?status=error&msg=AksesDitolak');
         exit();
    }

    $stmt = $koneksi->prepare("
        UPDATE stok_pakan 
        SET id_kandang = ?, tanggal_beli = ?, nama_pakan = ?, jumlah_kg = ?, harga_per_kg = ?, harga_total = ?
        WHERE id_stok = ?
    ");
    $stmt->bind_param("issdddi", $id_kandang, $tanggal_beli, $nama_pakan, $jumlah_kg, $harga_per_kg, $harga_total, $id_stok);

    if ($stmt->execute()) {
        header('Location: index.php?status=sukses_update');
        exit();
    } else {
        die("Gagal memperbarui data: " . $stmt->error);
    }
} else {
    header('Location: index.php');
}
?>