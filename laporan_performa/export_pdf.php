<?php
// Memanggil autoloader dari Composer
require '../vendor/autoload.php';
include '../config/database.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Ambil data filter dari URL
$id_kandang_terpilih = isset($_GET['id_kandang']) ? $_GET['id_kandang'] : die('Kandang tidak dipilih.');
$tgl_awal = isset($_GET['tgl_awal']) ? $_GET['tgl_awal'] : die('Tanggal awal tidak dipilih.');
$tgl_akhir = isset($_GET['tgl_akhir']) ? $_GET['tgl_akhir'] : die('Tanggal akhir tidak dipilih.');

// Query data yang sama persis seperti di halaman laporan
// Di sini kita akan mengambil data agregat untuk ringkasan PDF
// (Anda bisa menyalin query lengkap dari laporan_performa/index.php)
$stmt_kandang = $koneksi->prepare("SELECT nama_kandang FROM kandang WHERE id_kandang = ?");
$stmt_kandang->bind_param("i", $id_kandang_terpilih);
$stmt_kandang->execute();
$master_kandang = $stmt_kandang->get_result()->fetch_assoc();

// ... (Lakukan semua query dan kalkulasi KPI seperti di halaman laporan_performa/index.php untuk mendapatkan array $data) ...
// Untuk mempersingkat, kita akan buat contoh data statis di sini.
// Anda HARUS mengganti bagian ini dengan query dan kalkulasi asli.
$data_ringkasan = [
    'nama_kandang' => $master_kandang['nama_kandang'],
    'total_pemasukan_telur' => 5000000,
    'total_pengeluaran' => 3500000,
    'laba_rugi' => 1500000,
    'fcr' => 2.1,
    'deplesi' => 5.5
];


// Membuat konten HTML yang akan diubah menjadi PDF
$html = "
<html>
<head>
<style>
    body { font-family: sans-serif; }
    h1, h2 { text-align: center; }
    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
    th, td { border: 1px solid #000; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
    .total { font-weight: bold; }
</style>
</head>
<body>
    <h1>Laporan Performa Kandang</h1>
    <h2>" . htmlspecialchars($data_ringkasan['nama_kandang']) . "</h2>
    <p>Periode: " . date('d M Y', strtotime($tgl_awal)) . " - " . date('d M Y', strtotime($tgl_akhir)) . "</p>

    <table>
        <tr>
            <th>Indikator</th>
            <th>Nilai</th>
        </tr>
        <tr>
            <td>Pemasukan dari Telur</td>
            <td>Rp " . number_format($data_ringkasan['total_pemasukan_telur']) . "</td>
        </tr>
        <tr>
            <td>Total Pengeluaran</td>
            <td>Rp " . number_format($data_ringkasan['total_pengeluaran']) . "</td>
        </tr>
        <tr class='total'>
            <td>Laba / Rugi Bersih</td>
            <td>Rp " . number_format($data_ringkasan['laba_rugi']) . "</td>
        </tr>
        <tr>
            <td>Feed Conversion Ratio (FCR)</td>
            <td>" . number_format($data_ringkasan['fcr'], 2) . "</td>
        </tr>
        <tr>
            <td>Deplesi</td>
            <td>" . number_format($data_ringkasan['deplesi'], 2) . "%</td>
        </tr>
    </table>
</body>
</html>
";

// Inisialisasi Dompdf
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$dompdf = new Dompdf($options);

// Load HTML ke Dompdf
$dompdf->loadHtml($html);

// (Opsional) Mengatur ukuran kertas dan orientasi
$dompdf->setPaper('A4', 'portrait');

// Render HTML sebagai PDF
$dompdf->render();

// Output file PDF yang dihasilkan ke Browser untuk diunduh
$filename = 'laporan_pdf_' . str_replace(' ', '_', $master_kandang['nama_kandang']) . '_' . date('Y-m-d') . '.pdf';
$dompdf->stream($filename, ["Attachment" => true]);