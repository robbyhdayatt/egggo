<?php
include '../config/database.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . $folder_base . '/auth/login.php');
    exit();
}
if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit();
}

$id = $_GET['id'];
$stmt = $koneksi->prepare("DELETE FROM kandang WHERE id_kandang = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    header('Location: index.php?status=sukses_hapus');
} else {
    header('Location: index.php?status=gagal_hapus');
}
?>