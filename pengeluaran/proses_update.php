<?php
include '../templates/header.php'; 
global $current_user_role, $current_assigned_kandang_id;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_pengeluaran = $_POST['id_pengeluaran'];
    $id_kandang = $_POST['id_kandang'];
    $tanggal_pengeluaran = $_POST['tanggal_pengeluaran'];
    $id_kategori = (int)$_POST['id_kategori']; // <--- PERUBAHAN
    $jumlah = str_replace('.', '', $_POST['jumlah']);
    $keterangan = $_POST['keterangan'];

    // Validasi Hak Akses
    if ($current_user_role === 'Karyawan' && $id_kandang != $current_assigned_kandang_id) {
         header('Location: index.php?status=error&msg=AksesDitolak');
         exit();
    }
    
    // Validasi input dasar
     if (empty($id_pengeluaran) || empty($id_kandang) || empty($tanggal_pengeluaran) || empty($id_kategori) || empty($keterangan)) {
         header('Location: index.php?status=error&msg=InputTidakLengkap');
         exit();
    }

    // --- PERUBAHAN QUERY UPDATE ---
    $stmt = $koneksi->prepare("
        UPDATE pengeluaran 
        SET id_kandang = ?, tanggal_pengeluaran = ?, id_kategori = ?, jumlah = ?, keterangan = ? 
        WHERE id_pengeluaran = ?
    ");
    $stmt->bind_param("isissi", $id_kandang, $tanggal_pengeluaran, $id_kategori, $jumlah, $keterangan, $id_pengeluaran);
    // --- AKHIR PERUBAHAN ---

    if ($stmt->execute()) {
        header('Location: index.php?status=sukses_update');
        exit();
    } else {
        error_log("Gagal update pengeluaran: " . $stmt->error);
        header('Location: index.php?status=error&msg=GagalUpdate');
        exit();
    }
} else {
    header('Location: index.php');
}
?>