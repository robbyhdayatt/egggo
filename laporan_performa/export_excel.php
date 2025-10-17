<?php
// Memanggil autoloader dari Composer
require '../vendor/autoload.php';
include '../config/database.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Ambil data filter dari URL
$id_kandang_terpilih = isset($_GET['id_kandang']) ? $_GET['id_kandang'] : die('Kandang tidak dipilih.');
$tgl_awal = isset($_GET['tgl_awal']) ? $_GET['tgl_awal'] : die('Tanggal awal tidak dipilih.');
$tgl_akhir = isset($_GET['tgl_akhir']) ? $_GET['tgl_akhir'] : die('Tanggal akhir tidak dipilih.');

// Lakukan query yang sama persis seperti di halaman laporan untuk mendapatkan data
// (Ini adalah contoh ringkas, Anda bisa menyalin query lengkap dari halaman laporan)
$stmt_kandang = $koneksi->prepare("SELECT nama_kandang FROM kandang WHERE id_kandang = ?");
$stmt_kandang->bind_param("i", $id_kandang_terpilih);
$stmt_kandang->execute();
$master_kandang = $stmt_kandang->get_result()->fetch_assoc();
$nama_kandang = $master_kandang['nama_kandang'];

// Ambil data laporan harian untuk detail
$stmt_detail = $koneksi->prepare("SELECT * FROM laporan_harian WHERE id_kandang = ? AND tanggal BETWEEN ? AND ? ORDER BY tanggal ASC");
$stmt_detail->bind_param("iss", $id_kandang_terpilih, $tgl_awal, $tgl_akhir);
$stmt_detail->execute();
$result_detail = $stmt_detail->get_result();

// Membuat objek Spreadsheet baru
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Menulis Judul
$sheet->setCellValue('A1', 'Laporan Performa Harian');
$sheet->mergeCells('A1:E1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);

$sheet->setCellValue('A2', 'Kandang: ' . $nama_kandang);
$sheet->setCellValue('A3', 'Periode: ' . date('d M Y', strtotime($tgl_awal)) . ' - ' . date('d M Y', strtotime($tgl_akhir)));

// Menulis Header Tabel
$sheet->setCellValue('A5', 'Tanggal');
$sheet->setCellValue('B5', 'Pakan Terpakai (kg)');
$sheet->setCellValue('C5', 'Produksi Telur (kg)');
$sheet->setCellValue('D5', 'Ayam Mati (ekor)');
$sheet->setCellValue('E5', 'Pemasukan (Rp)');
$sheet->getStyle('A5:E5')->getFont()->setBold(true);

// Menulis Data ke setiap baris
$row = 6;
while ($data = $result_detail->fetch_assoc()) {
    $produksi_telur_harian = $data['telur_baik_kg'] + $data['telur_tipis_kg'] + $data['telur_pecah_kg'];
    $sheet->setCellValue('A' . $row, date('d-m-Y', strtotime($data['tanggal'])));
    $sheet->setCellValue('B' . $row, $data['pakan_terpakai_kg']);
    $sheet->setCellValue('C' . $row, $produksi_telur_harian);
    $sheet->setCellValue('D' . $row, $data['ayam_mati']);
    $sheet->setCellValue('E' . $row, $data['pemasukan_telur']);
    $row++;
}

// Mengatur header untuk download file .xlsx
$filename = 'laporan_kandang_' . str_replace(' ', '_', $nama_kandang) . '_' . date('Y-m-d') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

// Menulis file ke output
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit();