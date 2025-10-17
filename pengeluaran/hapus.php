<?php
include '../config/database.php';

if (isset($_GET['id'])) {
    $id_pengeluaran = $_GET['id'];
    $stmt = $koneksi->prepare("DELETE FROM pengeluaran WHERE id_pengeluaran = ?");
    $stmt->bind_param("i", $id_pengeluaran);
    if ($stmt->execute()) {
        header('Location: index.php?status=sukses_hapus');
    } else {
        die("Gagal menghapus data.");
    }
} else {
    header('Location: index.php');
}
?>