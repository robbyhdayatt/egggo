<?php

include '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Karyawan') {
    echo json_encode(['success' => false, 'message' => 'Akses tidak sah.']);
    exit();
}

if (!isset($_POST['id_laporan']) || !is_numeric($_POST['id_laporan'])) {
    echo json_encode(['success' => false, 'message' => 'ID Laporan tidak valid.']);
    exit();
}

$id_laporan = (int)$_POST['id_laporan'];
$current_user_id = $_SESSION['user_id'];

$stmt_check = $koneksi->prepare("SELECT tanggal, edit_requested_at, edit_approved_at FROM laporan_harian WHERE id_laporan = ?");
if (!$stmt_check) {
     $_SESSION['error_message_detail'] = 'Query check gagal disiapkan: ' . $koneksi->error;
     echo json_encode(['success' => false, 'message' => 'Gagal menyiapkan query validasi.']);
     exit();
}
$stmt_check->bind_param("i", $id_laporan);
$stmt_check->execute();
$result_check = $stmt_check->get_result();
$laporan = $result_check->fetch_assoc();
$stmt_check->close();

if (!$laporan) {
    echo json_encode(['success' => false, 'message' => 'Data laporan tidak ditemukan.']);
    exit();
}

if (!empty($laporan['edit_approved_at'])) {
    echo json_encode(['success' => false, 'message' => 'Laporan ini sudah diapprove sebelumnya.']);
    exit();
}
if (!empty($laporan['edit_requested_at'])) {
    echo json_encode(['success' => false, 'message' => 'Permintaan approval sudah pernah diajukan.']);
    exit();
}

$now = date('Y-m-d H:i:s');
$stmt_update = $koneksi->prepare("
    UPDATE laporan_harian
    SET edit_requested_by = ?, edit_requested_at = ?
    WHERE id_laporan = ?
");
if (!$stmt_update) {
     $_SESSION['error_message_detail'] = 'Query update gagal disiapkan: ' . $koneksi->error;
     echo json_encode(['success' => false, 'message' => 'Gagal menyiapkan query update.']);
     exit();
}
$stmt_update->bind_param("isi", $current_user_id, $now, $id_laporan);

if ($stmt_update->execute()) {
    $_SESSION['success_message'] = 'Permintaan approval untuk laporan tanggal ' . date('d M Y', strtotime($laporan['tanggal'])) . ' berhasil diajukan!';
    echo json_encode(['success' => true]);
} else {
    $_SESSION['error_message_detail'] = 'Gagal mengupdate database: ' . $stmt_update->error;
    echo json_encode(['success' => false, 'message' => 'Gagal mengupdate database.']);
}

$stmt_update->close();
$koneksi->close();
?>