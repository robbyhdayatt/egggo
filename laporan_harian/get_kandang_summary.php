<?php
include '../config/database.php';

// Set header ke JSON
header('Content-Type: application/json');

// Pastikan id_kandang dikirim
if (!isset($_GET['id_kandang']) || empty($_GET['id_kandang'])) {
    echo json_encode(['error' => 'ID Kandang tidak valid.']);
    exit();
}

$id_kandang = (int)$_GET['id_kandang'];
$response = [];

// === PERHITUNGAN SPESIFIK PER KANDANG ===

// 1. Query untuk mendapatkan data awal kandang terpilih (DIUBAH)
$stmt_master = $koneksi->prepare("SELECT populasi_awal, stok_telur_awal_kg FROM kandang WHERE id_kandang = ?");
$stmt_master->bind_param("i", $id_kandang);
$stmt_master->execute();
$master_data = $stmt_master->get_result()->fetch_assoc();
$populasi_awal = $master_data['populasi_awal'] ?? 0;
$stok_telur_awal = $master_data['stok_telur_awal_kg'] ?? 0; // Ambil stok telur awal

// 2. Query untuk menghitung total ayam & telur dari laporan harian kandang terpilih
$stmt_summary_kandang = $koneksi->prepare("
    SELECT
        COALESCE(SUM(ayam_masuk), 0) as total_ayam_masuk,
        COALESCE(SUM(ayam_mati), 0) as total_ayam_mati,
        COALESCE(SUM(ayam_afkir), 0) as total_ayam_afkir,
        COALESCE(SUM(telur_baik_kg + telur_tipis_kg + telur_pecah_kg), 0) as total_produksi_telur
    FROM laporan_harian
    WHERE id_kandang = ?
");
$stmt_summary_kandang->bind_param("i", $id_kandang);
$stmt_summary_kandang->execute();
$summary_data_kandang = $stmt_summary_kandang->get_result()->fetch_assoc();

// Kalkulasi total ayam saat ini untuk kandang terpilih
$total_ayam_sekarang = ($populasi_awal + $summary_data_kandang['total_ayam_masuk']) - ($summary_data_kandang['total_ayam_mati'] + $summary_data_kandang['total_ayam_afkir']);

// Kalkulasi total telur keseluruhan (BARU)
$total_telur_keseluruhan = $stok_telur_awal + $summary_data_kandang['total_produksi_telur'];


// === PERHITUNGAN GLOBAL UNTUK STOK PAKAN TERSEDIA ===
// ... (Logika perhitungan pakan tidak berubah) ...
$total_pakan_dibeli_result = $koneksi->query("SELECT COALESCE(SUM(jumlah_kg), 0) as total FROM stok_pakan");
$total_pakan_dibeli = $total_pakan_dibeli_result->fetch_assoc()['total'];
$total_pakan_terpakai_result = $koneksi->query("SELECT COALESCE(SUM(pakan_terpakai_kg), 0) as total FROM laporan_harian");
$total_pakan_terpakai = $total_pakan_terpakai_result->fetch_assoc()['total'];
$stok_pakan_tersedia = $total_pakan_dibeli - $total_pakan_terpakai;


// === SIAPKAN DATA UNTUK DIKIRIM KEMBALI ===
$response = [
    'total_ayam' => number_format($total_ayam_sekarang),
    'total_telur' => number_format($total_telur_keseluruhan, 2), // DIUBAH
    'total_pakan_tersedia' => number_format($stok_pakan_tersedia, 2)
];

// Cetak data sebagai JSON
echo json_encode($response);
exit();
?>