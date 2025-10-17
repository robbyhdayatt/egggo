<?php
// Memanggil autoloader dari Composer
require '../vendor/autoload.php';
include '../config/database.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

// Ambil data filter dari URL
$id_kandang_terpilih = $_GET['id_kandang'] ?? null;
$tgl_awal = $_GET['tgl_awal'] ?? null;
$tgl_akhir = $_GET['tgl_akhir'] ?? null;

if (!$id_kandang_terpilih || !$tgl_awal || !$tgl_akhir) {
    die("Filter laporan tidak lengkap. Silakan kembali dan pilih kandang serta tanggal.");
}

// =================================================================================
// LOGIKA PENGAMBILAN & PERHITUNGAN DATA (VERSI LENGKAP DAN BENAR)
// =================================================================================
$laporan_lengkap = [];
$master_kandang = null;

// 1. Ambil data master kandang
$stmt_kandang = $koneksi->prepare("SELECT * FROM kandang WHERE id_kandang = ?");
$stmt_kandang->bind_param("i", $id_kandang_terpilih);
$stmt_kandang->execute();
$master_kandang = $stmt_kandang->get_result()->fetch_assoc();

// 2. Hitung total kumulatif SEBELUM tanggal awal filter
$stmt_sebelum_kandang = $koneksi->prepare("SELECT COALESCE(SUM(ayam_masuk), 0) as total_ayam_masuk_sebelum, COALESCE(SUM(ayam_mati), 0) as total_ayam_mati_sebelum, COALESCE(SUM(ayam_afkir), 0) as total_ayam_afkir_sebelum, COALESCE(SUM(telur_baik_kg + telur_tipis_kg + telur_pecah_kg), 0) as total_produksi_sebelum, COALESCE(SUM(telur_terjual_kg), 0) as total_terjual_sebelum FROM laporan_harian WHERE id_kandang = ? AND tanggal < ?");
$stmt_sebelum_kandang->bind_param("is", $id_kandang_terpilih, $tgl_awal);
$stmt_sebelum_kandang->execute();
$data_sebelum_kandang = $stmt_sebelum_kandang->get_result()->fetch_assoc();

$pakan_dibeli_sebelum = $koneksi->query("SELECT COALESCE(SUM(jumlah_kg), 0) as total FROM stok_pakan WHERE tanggal_beli < '$tgl_awal'")->fetch_assoc()['total'];
$pakan_terpakai_sebelum = $koneksi->query("SELECT COALESCE(SUM(pakan_terpakai_kg), 0) as total FROM laporan_harian WHERE tanggal < '$tgl_awal'")->fetch_assoc()['total'];

$sisa_ayam_sebelumnya = ($master_kandang['populasi_awal'] + $data_sebelum_kandang['total_ayam_masuk_sebelum']) - ($data_sebelum_kandang['total_ayam_mati_sebelum'] + $data_sebelum_kandang['total_ayam_afkir_sebelum']);
$sisa_telur_sebelumnya = ($master_kandang['stok_telur_awal_kg'] + $data_sebelum_kandang['total_produksi_sebelum']) - $data_sebelum_kandang['total_terjual_sebelum'];
$sisa_pakan_sebelumnya = $pakan_dibeli_sebelum - $pakan_terpakai_sebelum;

$pakan_dibeli_pada_periode = $koneksi->query("SELECT tanggal_beli, jumlah_kg FROM stok_pakan WHERE tanggal_beli BETWEEN '$tgl_awal' AND '$tgl_akhir'")->fetch_all(MYSQLI_ASSOC);
$pakan_masuk_harian = [];
foreach($pakan_dibeli_pada_periode as $pakan) {
    $pakan_masuk_harian[$pakan['tanggal_beli']] = ($pakan_masuk_harian[$pakan['tanggal_beli']] ?? 0) + $pakan['jumlah_kg'];
}

$stmt_laporan = $koneksi->prepare("SELECT * FROM laporan_harian WHERE id_kandang = ? AND tanggal BETWEEN ? AND ? ORDER BY tanggal ASC");
$stmt_laporan->bind_param("iss", $id_kandang_terpilih, $tgl_awal, $tgl_akhir);
$stmt_laporan->execute();
$result_laporan = $stmt_laporan->get_result();
$sisa_pakan_sebelumnya_harian = [];
while($row = $result_laporan->fetch_assoc()) {
    $item = $row;
    $tanggal_sekarang = $row['tanggal'];

    $tgl_masuk = new DateTime($master_kandang['tgl_masuk_awal']);
    $tgl_laporan = new DateTime($row['tanggal']);
    $selisih_hari = $tgl_laporan->diff($tgl_masuk)->days;
    $item['umur_ayam_minggu'] = ($master_kandang['umur_ayam_awal'] + $selisih_hari) / 7;

    $sisa_ayam_hari_ini = $sisa_ayam_sebelumnya + $row['ayam_masuk'] - $row['ayam_mati'] - $row['ayam_afkir'];
    $item['sisa_ayam'] = $sisa_ayam_hari_ini;
    
    $total_produksi_harian = $row['telur_baik_kg'] + $row['telur_tipis_kg'] + $row['telur_pecah_kg'];
    $sisa_telur_hari_ini = $sisa_telur_sebelumnya + $total_produksi_harian - $row['telur_terjual_kg'];
    $item['total_produksi_harian'] = $total_produksi_harian;
    $item['sisa_telur'] = $sisa_telur_hari_ini;

    $pakan_dibeli_hari_ini = $pakan_masuk_harian[$tanggal_sekarang] ?? 0;
    $pakan_terpakai_hari_ini_global_q = $koneksi->query("SELECT COALESCE(SUM(pakan_terpakai_kg), 0) as total FROM laporan_harian WHERE tanggal = '$tanggal_sekarang'");
    $pakan_terpakai_hari_ini_global = $pakan_terpakai_hari_ini_global_q->fetch_assoc()['total'];
    if (!isset($sisa_pakan_sebelumnya_harian[$tanggal_sekarang])) { $sisa_pakan_sebelumnya_harian[$tanggal_sekarang] = $sisa_pakan_sebelumnya; }
    $sisa_pakan_hari_ini = $sisa_pakan_sebelumnya_harian[$tanggal_sekarang] + $pakan_dibeli_hari_ini - $pakan_terpakai_hari_ini_global;
    $item['sisa_pakan_global'] = $sisa_pakan_hari_ini;

    $laporan_lengkap[] = $item;

    $sisa_ayam_sebelumnya = $sisa_ayam_hari_ini;
    $sisa_telur_sebelumnya = $sisa_telur_hari_ini;
    $sisa_pakan_sebelumnya_harian[date('Y-m-d', strtotime($tanggal_sekarang . ' +1 day'))] = $sisa_pakan_hari_ini;
}
// =================================================================================
// AKHIR DARI LOGIKA PENGAMBILAN & PERHITUNGAN DATA
// =================================================================================

// Membuat objek Spreadsheet baru
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Riwayat Laporan');

// === STYLING ===
$centerStyle = ['alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]];
$rightStyle = ['alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT]];
$headerStyle = ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2C3E50']], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true]];
$allBordersStyle = ['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'BDC3C7']]]];
$subTotalStyle = ['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'ECF0F1']], 'font' => ['bold' => true]];

// === MENULIS JUDUL ===
$sheet->setCellValue('A1', 'RIWAYAT LAPORAN HARIAN');
$sheet->mergeCells('A1:T1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
$sheet->getStyle('A1')->applyFromArray($centerStyle);
$sheet->setCellValue('A2', 'Kandang: ' . ($master_kandang['nama_kandang'] ?? 'N/A'));
$sheet->mergeCells('A2:T2');
$sheet->setCellValue('A3', 'Periode: ' . date('d M Y', strtotime($tgl_awal)) . ' - ' . date('d M Y', strtotime($tgl_akhir)));
$sheet->mergeCells('A3:T3');

// === MENULIS HEADER TABEL ===
$sheet->mergeCells('A5:A6')->setCellValue('A5', 'Tgl');
$sheet->mergeCells('B5:B6')->setCellValue('B5', 'Umur (Minggu)');
$sheet->mergeCells('C5:F5')->setCellValue('C5', 'Ayam (Ekor)');
$sheet->fromArray(['Masuk', 'Mati', 'Afkir', 'Sisa'], NULL, 'C6');
$sheet->mergeCells('G5:H5')->setCellValue('G5', 'Pakan (Kg)');
$sheet->fromArray(['Terpakai', 'Sisa (Global)'], NULL, 'G6');
$sheet->mergeCells('I5:L5')->setCellValue('I5', 'Produksi Telur (Kg)');
$sheet->fromArray(['Baik', 'Tipis', 'Pecah', 'Total'], NULL, 'I6');
$sheet->mergeCells('M5:N5')->setCellValue('M5', 'Penjualan Telur');
$sheet->fromArray(['Kg', 'Rp'], NULL, 'M6');
$sheet->mergeCells('O5:O6')->setCellValue('O5', 'Sisa Stok Telur (Kg)');
$sheet->mergeCells('P5:S5')->setCellValue('P5', 'Pengeluaran Lain (Rp)');
$sheet->fromArray(['Gaji Harian', 'Gaji Bulanan', 'Obat/Vit', 'Lain-Lain'], NULL, 'P6');
$sheet->mergeCells('T5:T6')->setCellValue('T5', 'Keterangan');
$sheet->getStyle('A5:T6')->applyFromArray($headerStyle);

// === MENULIS DATA ===
$row = 7;
foreach ($laporan_lengkap as $laporan) {
    $sheet->fromArray([
        \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel(strtotime($laporan['tanggal'])),
        $laporan['umur_ayam_minggu'],
        $laporan['ayam_masuk'], $laporan['ayam_mati'], $laporan['ayam_afkir'], $laporan['sisa_ayam'],
        $laporan['pakan_terpakai_kg'], $laporan['sisa_pakan_global'],
        $laporan['telur_baik_kg'], $laporan['telur_tipis_kg'], $laporan['telur_pecah_kg'], $laporan['total_produksi_harian'],
        $laporan['telur_terjual_kg'], $laporan['pemasukan_telur'],
        $laporan['sisa_telur'],
        $laporan['gaji_harian'], $laporan['gaji_bulanan'], $laporan['obat_vitamin'], $laporan['lain_lain_operasional'],
        $laporan['keterangan_pengeluaran']
    ], NULL, 'A' . $row);
    $row++;
}

// === FORMATTING AKHIR ===
$lastRow = $row - 1;
if ($lastRow >= 7) {
    // Format Angka & Tanggal
    $sheet->getStyle('A7:A' . $lastRow)->getNumberFormat()->setFormatCode('DD/MM/YYYY');
    $sheet->getStyle('B7:B' . $lastRow)->getNumberFormat()->setFormatCode('#,##0.0');
    $sheet->getStyle('C7:F' . $lastRow)->getNumberFormat()->setFormatCode('#,##0');
    $sheet->getStyle('G7:M' . $lastRow)->getNumberFormat()->setFormatCode('#,##0.00');
    $sheet->getStyle('O7:O' . $lastRow)->getNumberFormat()->setFormatCode('#,##0.00');
    $sheet->getStyle('N7:N' . $lastRow)->getNumberFormat()->setFormatCode('"Rp "#,##0');
    $sheet->getStyle('P7:S' . $lastRow)->getNumberFormat()->setFormatCode('"Rp "#,##0');

    // Perataan Kolom
    $sheet->getStyle('A7:F' . $lastRow)->applyFromArray($centerStyle);
    $sheet->getStyle('G7:S' . $lastRow)->applyFromArray($rightStyle);
    $sheet->getStyle('T7:T' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

    // Style untuk kolom subtotal/sisa
    $sheet->getStyle('F7:F' . $lastRow)->applyFromArray($subTotalStyle);
    $sheet->getStyle('H7:H' . $lastRow)->applyFromArray($subTotalStyle);
    $sheet->getStyle('L7:L' . $lastRow)->applyFromArray($subTotalStyle);
    $sheet->getStyle('O7:O' . $lastRow)->applyFromArray($subTotalStyle);

    // Terapkan border ke seluruh tabel
    $sheet->getStyle('A5:T' . $lastRow)->applyFromArray($allBordersStyle);
}
// Atur lebar kolom otomatis
foreach (range('A', 'T') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}
// Bekukan baris header
$sheet->freezePane('A7');

// === MENGIRIM FILE KE BROWSER ===
$filename = 'Riwayat_Laporan_' . str_replace(' ', '_', $master_kandang['nama_kandang']) . '_' . date('Ymd') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit();
?>