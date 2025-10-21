<?php
include '../templates/header.php'; 

if ($current_user_role !== 'Pimpinan') { header('Location: ' . $folder_base . '/index.php'); exit(); }

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama_kategori = trim($_POST['nama_kategori']);
    $status = $_POST['status'];

    if (empty($nama_kategori) || empty($status)) {
        header('Location: index.php?status=error'); exit();
    }

    $stmt = $koneksi->prepare("INSERT INTO kategori_pengeluaran (nama_kategori, status) VALUES (?, ?)");
    $stmt->bind_param("ss", $nama_kategori, $status);
    if ($stmt->execute()) {
        header('Location: index.php?status=sukses_tambah');
    } else {
        header('Location: index.php?status=error');
    }
    $stmt->close();
    exit();
}
header('Location: index.php');
exit();
?>