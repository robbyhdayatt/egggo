<?php
include '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . $folder_base . '/auth/login.php');
    exit();
}

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $koneksi->prepare("DELETE FROM stok_pakan WHERE id_stok = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        header('Location: index.php?status=sukses_hapus');
    } else {
        header('Location: index.php?status=gagal_hapus');
    }
} else {
    header('Location: index.php');
}
?>