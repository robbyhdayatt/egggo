<?php
// proses_approval.php
include '../config/database.php'; // Sesuaikan path jika perlu

// Mulai session untuk mendapatkan user_id dan role
// session_start(); // Biasanya sudah dipanggil di database.php

// Pastikan hanya Pimpinan yang bisa akses
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Pimpinan') {
    $_SESSION['error_message'] = "Akses tidak sah.";
    // Tentukan folder base jika belum ada
     if (!isset($folder_base)) {
         // Coba ambil dari koneksi jika tersedia
         if (isset($koneksi)) {
             $query_base = $koneksi->query("SELECT nilai_konfigurasi FROM konfigurasi WHERE nama_konfigurasi = 'folder_base'");
             if ($query_base && $query_base->num_rows > 0) { $folder_base = $query_base->fetch_assoc()['nilai_konfigurasi']; }
             else { $folder_base = '/egggo'; }
         } else { $folder_base = '/egggo'; }
     }
    header('Location: ' . ($folder_base ?? '/egggo') . '/index.php');
    exit();
}

// Cek apakah data POST ada
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id_laporan']) || !is_numeric($_POST['id_laporan']) || !isset($_POST['action'])) {
     header('Location: index.php?status=gagal'); // Redirect jika data tidak lengkap
     exit();
}

$id_laporan = (int)$_POST['id_laporan'];
$action = $_POST['action'];
$current_pimpinan_id = $_SESSION['user_id'];
$now = date('Y-m-d H:i:s');

$redirect_status = ''; // Variabel untuk status redirect

if ($action === 'approve') {
    // Update data untuk approval
    $stmt = $koneksi->prepare("
        UPDATE laporan_harian
        SET edit_approved_at = ?, edit_approved_by = ?
        WHERE id_laporan = ? AND edit_requested_at IS NOT NULL AND edit_approved_at IS NULL
    ");
     if (!$stmt) {
         $redirect_status = 'approve_gagal'; // Error prepare
     } else {
        $stmt->bind_param("sii", $now, $current_pimpinan_id, $id_laporan);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $redirect_status = 'approve_sukses';
        } else {
            // Bisa jadi sudah diapprove/ditolak oleh Pimpinan lain, atau error
            $redirect_status = 'approve_gagal';
            // Log error jika perlu: error_log("Gagal approve id $id_laporan: " . $stmt->error);
        }
        $stmt->close();
     }

} elseif ($action === 'reject') { // --- MODIFIKASI: Tambahkan blok ini ---
    // Update data untuk menolak (hapus request)
    $stmt = $koneksi->prepare("
        UPDATE laporan_harian
        SET edit_requested_at = NULL, edit_requested_by = NULL,
            edit_approved_at = NULL, edit_approved_by = NULL -- Pastikan approval juga null
        WHERE id_laporan = ? AND edit_approved_at IS NULL -- Hanya bisa tolak yg belum diapprove
    ");

     if (!$stmt) {
         $redirect_status = 'reject_gagal'; // Error prepare
     } else {
        $stmt->bind_param("i", $id_laporan);
        if ($stmt->execute()) {
            // Berhasil meskipun affected_rows bisa 0 (jika request sudah tidak ada)
             $redirect_status = 'reject_sukses';
        } else {
             $redirect_status = 'reject_gagal';
             // Log error jika perlu: error_log("Gagal reject id $id_laporan: " . $stmt->error);
        }
         $stmt->close();
     }
    // --- AKHIR MODIFIKASI ---

} else {
    $redirect_status = 'gagal'; // Aksi tidak dikenal
}

$koneksi->close();
header('Location: index.php?status=' . $redirect_status);
exit();
?>