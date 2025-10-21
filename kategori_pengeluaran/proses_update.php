<?php
include '../templates/header.php'; 
// Cek Pimpinan
if ($current_user_role !== 'Pimpinan') { header('Location: ' . $folder_base . '/index.php'); exit(); }

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_kategori = $_POST['id_kategori'];
    $nama_kategori = trim($_POST['nama_kategori']);
    $status = $_POST['status'];

    if (empty($id_kategori) || empty($nama_kategori) || empty($status)) {
         header('Location: index.php?status=error'); exit();
    }

    $stmt = $koneksi->prepare("UPDATE kategori_pengeluaran SET nama_kategori = ?, status = ? WHERE id_kategori = ?");
    $stmt->bind_param("ssi", $nama_kategori, $status, $id_kategori);
    if ($stmt->execute()) {
        header('Location: index.php?status=sukses_update');
    } else {
         header('Location: index.php?status=error');
    }
    $stmt->close();
    exit();
}
header('Location: index.php');
exit();
?>