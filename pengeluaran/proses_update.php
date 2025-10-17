<?php
include '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_pengeluaran = $_POST['id_pengeluaran'];
    $id_kandang = $_POST['id_kandang'];
    $tanggal_pengeluaran = $_POST['tanggal_pengeluaran'];
    $kategori = $_POST['kategori'];
    $jumlah = str_replace('.', '', $_POST['jumlah']);
    $keterangan = $_POST['keterangan'];

    $stmt = $koneksi->prepare("
        UPDATE pengeluaran 
        SET id_kandang = ?, tanggal_pengeluaran = ?, kategori = ?, jumlah = ?, keterangan = ? 
        WHERE id_pengeluaran = ?
    ");
    $stmt->bind_param("issisi", $id_kandang, $tanggal_pengeluaran, $kategori, $jumlah, $keterangan, $id_pengeluaran);

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