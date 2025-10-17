<?php
include '../config/database.php';

header('Content-Type: application/json');

$id_kandang = $_GET['id_kandang'] ?? null;

if (!$id_kandang) {
    echo json_encode(['error' => 'ID Kandang tidak valid.']);
    exit();
}

// 1. Ambil data master kandang
$stmt_kandang = $koneksi->prepare("SELECT populasi_awal, stok_telur_awal_kg FROM kandang WHERE id_kandang = ?");
$stmt_kandang->bind_param("i", $id_kandang);
$stmt_kandang->execute();
$master_kandang = $stmt_kandang->get_result()->fetch_assoc();

if (!$master_kandang) {
    echo json_encode(['error' => 'Data kandang tidak ditemukan.']);
    exit();
}

// 2. Hitung total ayam dan telur dari laporan harian
$stmt_laporan = $koneksi->prepare("
    SELECT 
        COALESCE(SUM(ayam_masuk), 0) as total_masuk,
        COALESCE(SUM(ayam_mati), 0) as total_mati,
        COALESCE(SUM(ayam_afkir), 0) as total_afkir,
        COALESCE(SUM(telur_baik_kg + telur_tipis_kg + telur_pecah_kg), 0) as total_produksi,
        COALESCE(SUM(telur_terjual_kg), 0) as total_terjual,
        COALESCE(SUM(pakan_terpakai_kg), 0) as total_pakan_terpakai
    FROM laporan_harian 
    WHERE id_kandang = ?
");
$stmt_laporan->bind_param("i", $id_kandang);
$stmt_laporan->execute();
$laporan = $stmt_laporan->get_result()->fetch_assoc();

// 3. Hitung total pakan yang dibeli UNTUK KANDANG INI
$stmt_pakan = $koneksi->prepare("SELECT COALESCE(SUM(jumlah_kg), 0) as total FROM stok_pakan WHERE id_kandang = ?");
$stmt_pakan->bind_param("i", $id_kandang);
$stmt_pakan->execute();
$pakan_dibeli = $stmt_pakan->get_result()->fetch_assoc()['total'];


// 4. Kalkulasi nilai akhir
$sisa_ayam = ($master_kandang['populasi_awal'] + $laporan['total_masuk']) - ($laporan['total_mati'] + $laporan['total_afkir']);
$sisa_telur = ($master_kandang['stok_telur_awal_kg'] + $laporan['total_produksi']) - $laporan['total_terjual'];
$sisa_pakan = $pakan_dibeli - $laporan['total_pakan_terpakai'];


echo json_encode([
    'total_ayam' => number_format($sisa_ayam, 0, ',', '.'),
    'total_telur' => number_format($sisa_telur, 2, ',', '.'),
    'total_pakan_tersedia' => number_format($sisa_pakan, 2, ',', '.')
]);

?>