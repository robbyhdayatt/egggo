<?php
include '../config/database.php';

// Ambil parameter filter dari URL untuk redirect kembali
$id_kandang = $_GET['id_kandang'] ?? '';
$tgl_awal = $_GET['tgl_awal'] ?? '';
$tgl_akhir = $_GET['tgl_akhir'] ?? '';
$redirect_params = http_build_query([
    'id_kandang' => $id_kandang,
    'tgl_awal' => $tgl_awal,
    'tgl_akhir' => $tgl_akhir,
    'status' => 'sukses_hapus' // Tambahkan status hapus
]);

if (isset($_GET['id'])) {
    $id_laporan = $_GET['id'];
    $stmt = $koneksi->prepare("DELETE FROM laporan_harian WHERE id_laporan = ?");
    $stmt->bind_param("i", $id_laporan);
    if ($stmt->execute()) {
        header('Location: index.php?' . $redirect_params);
        exit();
    } else {
        die("Gagal menghapus data laporan: " . $stmt->error);
    }
} else {
    // Redirect kembali ke index jika ID tidak ada
    header('Location: index.php?' . str_replace('&status=sukses_hapus', '', $redirect_params));
    exit();
}
?>