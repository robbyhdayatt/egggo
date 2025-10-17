<?php
session_start();

include '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . $folder_base . '/auth/login.php');
    exit();
}

if (isset($_GET['id']) && !empty($_GET['id'])) {
    $id_laporan = (int)$_GET['id'];
    $sql = "DELETE FROM laporan_harian WHERE id_laporan = ?";
    $stmt = $koneksi->prepare($sql);
    $stmt->bind_param("i", $id_laporan);

    if ($stmt->execute()) {
        // Jika berhasil, atur pesan sukses di session (opsional, untuk notifikasi)
        $_SESSION['success_message'] = "Data laporan berhasil dihapus.";
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit();

    } else {
        echo "Error: Gagal menghapus data. " . $stmt->error;
    }
    $stmt->close();
} else {
    header('Location: index.php');
    exit();
}

$koneksi->close();
?>