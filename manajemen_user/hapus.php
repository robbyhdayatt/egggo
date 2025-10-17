<?php
include '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . $folder_base . '/auth/login.php');
    exit();
}

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    // PENTING: Jangan biarkan user menghapus akunnya sendiri
    if ($id == $_SESSION['user_id']) {
        header('Location: index.php?status=gagal_hapus_diri');
        exit();
    }

    $stmt = $koneksi->prepare("DELETE FROM users WHERE id_user = ?");
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