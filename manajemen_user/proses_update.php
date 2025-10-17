<?php
include '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . $folder_base . '/auth/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_user = $_POST['id_user'];
    $nama_lengkap = $_POST['nama_lengkap'];
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Cek apakah password diisi atau tidak
    if (!empty($password)) {
        // Jika password diisi, update password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $koneksi->prepare("UPDATE users SET nama_lengkap = ?, username = ?, password = ? WHERE id_user = ?");
        $stmt->bind_param("sssi", $nama_lengkap, $username, $hashed_password, $id_user);
    } else {
        // Jika password kosong, jangan update password
        $stmt = $koneksi->prepare("UPDATE users SET nama_lengkap = ?, username = ? WHERE id_user = ?");
        $stmt->bind_param("ssi", $nama_lengkap, $username, $id_user);
    }
    
    if ($stmt->execute()) {
        header('Location: index.php?status=sukses_update');
    } else {
        echo "Gagal mengupdate data user!";
    }
} else {
    header('Location: index.php');
}
?>