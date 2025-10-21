<?php
include '../templates/header.php'; 
// Cek Pimpinan
if ($current_user_role !== 'Pimpinan') { header('Location: ' . $folder_base . '/index.php'); exit(); }

if (isset($_GET['id'])) {
    $id_kategori = $_GET['id'];
    if (!filter_var($id_kategori, FILTER_VALIDATE_INT)) {
         header('Location: index.php?status=error'); exit();
    }
    
    // Pengecekan keamanan: jangan hapus kategori yg masih dipakai (opsional)
    // $check = $koneksi->query("SELECT COUNT(*) as total FROM pengeluaran WHERE id_kategori = $id_kategori");
    // if ($check->fetch_assoc()['total'] > 0) {
    //     header('Location: index.php?status=error&msg=KategoriDipakai'); exit();
    // }

    $stmt = $koneksi->prepare("DELETE FROM kategori_pengeluaran WHERE id_kategori = ?");
    $stmt->bind_param("i", $id_kategori);
    if ($stmt->execute()) {
        header('Location: index.php?status=sukses_hapus');
    } else {
         header('Location: index.php?status=error');
    }
    $stmt->close();
    exit();
}
header('Location: index.php');
exit();
?>