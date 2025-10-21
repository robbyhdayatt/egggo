<?php

require '../vendor/autoload.php';
include '../config/database.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$id_kandang_terpilih = $_GET['id_kandang'] ?? '';
$tgl_awal = $_GET['tgl_awal'] ?? date('Y-m-01');
$tgl_akhir = $_GET['tgl_akhir'] ?? date('Y-m-t');

$semua_kandang_mode = ($id_kandang_terpilih === 'semua');
$nama_header = "Pilih Kandang";
$id_kandang_int = null;
$semua_kandang_data = [];
$ada_data_laporan = false;
$daftar_id_kandang_proses = [];

if ($semua_kandang_mode) {
    $nama_header = "Semua Kandang";
    $result_kandang = $koneksi->query("SELECT * FROM kandang"); 
    if ($result_kandang) {
        while($row = $result_kandang->fetch_assoc()) {
            $semua_kandang_data[$row['id_kandang']] = $row;
            $daftar_id_kandang_proses[] = $row['id_kandang'];
        }
    }
} else {
    if (!empty($id_kandang_terpilih) && ctype_digit($id_kandang_terpilih)) { 
        $id_kandang_int = (int)$id_kandang_terpilih;
        $stmt_kandang = $koneksi->prepare("SELECT * FROM kandang WHERE id_kandang = ?");
        if ($stmt_kandang) {
            $stmt_kandang->bind_param("i", $id_kandang_int);
            $stmt_kandang->execute();
            $master_kandang = $stmt_kandang->get_result()->fetch_assoc();
            if ($master_kandang) {
                $nama_header = $master_kandang['nama_kandang'];
                $semua_kandang_data[$id_kandang_int] = $master_kandang;
                $daftar_id_kandang_proses[] = $id_kandang_int;
            } else {
                 $nama_header = "Kandang Tidak Ditemukan";
            }
            $stmt_kandang->close();
        } else {
             die("Error preparing statement kandang: " . $koneksi->error);
        }
    } else {
         $nama_header = "ID Kandang Tidak Valid";
    }
}
$ids_string_proses = !empty($daftar_id_kandang_proses) ? implode(',', $daftar_id_kandang_proses) : '0';

$sisa_ayam_awal_per_kandang = [];
$query_stok_awal = "
    SELECT 
        k.id_kandang, k.populasi_awal, 
        COALESCE(SUM(lh.ayam_masuk), 0) as total_masuk_sebelum, 
        COALESCE(SUM(lh.ayam_mati), 0) as total_mati_sebelum, 
        COALESCE(SUM(lh.ayam_afkir), 0) as total_afkir_sebelum
    FROM kandang k
    LEFT JOIN laporan_harian lh ON k.id_kandang = lh.id_kandang AND lh.tanggal < ?
    WHERE k.id_kandang IN ($ids_string_proses)
    GROUP BY k.id_kandang, k.populasi_awal
";
$stmt_stok_awal = $koneksi->prepare($query_stok_awal);
if($stmt_stok_awal){
    $stmt_stok_awal->bind_param("s", $tgl_awal);
    $stmt_stok_awal->execute();
    $result_stok_awal = $stmt_stok_awal->get_result();
    while($row = $result_stok_awal->fetch_assoc()){
        $sisa_ayam_awal_per_kandang[$row['id_kandang']] = $row['populasi_awal'] + $row['total_masuk_sebelum'] - $row['total_mati_sebelum'] - $row['total_afkir_sebelum'];
    }
    $stmt_stok_awal->close();
}
$stok_ayam_berjalan_per_kandang = $sisa_ayam_awal_per_kandang;

$laporan_lengkap_asc = [];
$query_laporan = "
    SELECT lh.*, k.nama_kandang 
    FROM laporan_harian lh
    JOIN kandang k ON lh.id_kandang = k.id_kandang
    WHERE lh.tanggal BETWEEN ? AND ?
";
$params_laporan = [$tgl_awal, $tgl_akhir];
$types_laporan = "ss";

if (!$semua_kandang_mode && $id_kandang_int !== null) {
    $query_laporan .= " AND lh.id_kandang = ?";
    $params_laporan[] = $id_kandang_int;
    $types_laporan .= "i";
}
 $query_laporan .= " ORDER BY lh.tanggal ASC, k.nama_kandang ASC";

$stmt_laporan = $koneksi->prepare($query_laporan);
if ($stmt_laporan) {
    if (!empty($params_laporan)) {
        $stmt_laporan->bind_param($types_laporan, ...$params_laporan);
    }
    $stmt_laporan->execute();
    $result_laporan = $stmt_laporan->get_result();
    if ($result_laporan->num_rows > 0) {
        $ada_data_laporan = true; 
        while ($row = $result_laporan->fetch_assoc()) {
            $laporan_lengkap_asc[] = $row; 
        }
    }
    $stmt_laporan->close();
} else {
    die("Error preparing statement laporan: " . $koneksi->error);
}

$laporan_final_untuk_pdf = []; 

$pengeluaran_harian = [];
if ($ada_data_laporan) {
    foreach ($laporan_lengkap_asc as $laporan) {
        $id_k = $laporan['id_kandang'];
        if (!isset($stok_ayam_berjalan_per_kandang[$id_k])) {
             $stok_ayam_berjalan_per_kandang[$id_k] = $semua_kandang_data[$id_k]['populasi_awal'] ?? 0;
        }
        $sisa_ayam_hari_ini = $stok_ayam_berjalan_per_kandang[$id_k] + ($laporan['ayam_masuk'] ?? 0) - ($laporan['ayam_mati'] ?? 0) - ($laporan['ayam_afkir'] ?? 0);
        $laporan['sisa_ayam_kumulatif'] = $sisa_ayam_hari_ini;
        $stok_ayam_berjalan_per_kandang[$id_k] = $sisa_ayam_hari_ini;
        
        $laporan_final_untuk_pdf[] = $laporan; 
    }
    $laporan_final_untuk_pdf = array_reverse($laporan_final_untuk_pdf);
    $query_pengeluaran = "
        SELECT p.tanggal_pengeluaran, p.id_kandang, SUM(p.jumlah) as total, 
               GROUP_CONCAT(CONCAT(COALESCE(kat.nama_kategori, 'N/A'), ': ', p.keterangan, ' (Rp ', FORMAT(p.jumlah, 0, 'id_ID'), ')') SEPARATOR '; ') as detail
        FROM pengeluaran p
        LEFT JOIN kategori_pengeluaran kat ON p.id_kategori = kat.id_kategori
        WHERE p.tanggal_pengeluaran BETWEEN ? AND ?
    ";
    $params_pengeluaran = [$tgl_awal, $tgl_akhir]; $types_pengeluaran = "ss";
    if (!$semua_kandang_mode && $id_kandang_int !== null) {
        $query_pengeluaran .= " AND p.id_kandang = ?";
        $params_pengeluaran[] = $id_kandang_int; $types_pengeluaran .= "i";
    }
     $query_pengeluaran .= " GROUP BY p.tanggal_pengeluaran, p.id_kandang";
    $stmt_pengeluaran = $koneksi->prepare($query_pengeluaran);
     if ($stmt_pengeluaran){
        if (!empty($params_pengeluaran)) { $stmt_pengeluaran->bind_param($types_pengeluaran, ...$params_pengeluaran); }
        $stmt_pengeluaran->execute();
        $result_pengeluaran = $stmt_pengeluaran->get_result();
        while ($row = $result_pengeluaran->fetch_assoc()) {
            $pengeluaran_harian[$row['tanggal_pengeluaran']][$row['id_kandang']] = $row;
        }
        $stmt_pengeluaran->close();
     }
    $harga_pakan_terkini = [];
    if (!empty($semua_kandang_data)) {
        $query_harga_pakan = "SELECT id_kandang, tanggal_beli, harga_per_kg FROM stok_pakan WHERE tanggal_beli <= ? ";
        $params_harga = [$tgl_akhir]; $types_harga = "s";
        if (!$semua_kandang_mode && $id_kandang_int !== null) {
            $query_harga_pakan .= " AND id_kandang = ?";
            $params_harga[] = $id_kandang_int; $types_harga .= "i";
        }
        $query_harga_pakan .= " ORDER BY id_kandang ASC, tanggal_beli DESC, id_stok DESC";
        $stmt_harga = $koneksi->prepare($query_harga_pakan);
        if($stmt_harga){
            if (!empty($params_harga)) { $stmt_harga->bind_param($types_harga, ...$params_harga); }
            $stmt_harga->execute(); $result_harga = $stmt_harga->get_result();
            $harga_pakan_per_kandang_tanggal = [];
            while($row = $result_harga->fetch_assoc()){
                if(!isset($harga_pakan_per_kandang_tanggal[$row['id_kandang']][$row['tanggal_beli']])){
                     $harga_pakan_per_kandang_tanggal[$row['id_kandang']][$row['tanggal_beli']] = $row['harga_per_kg'];
                }
            }
            $stmt_harga->close();
            $tanggal_iterator = new DatePeriod(new DateTime($tgl_awal), new DateInterval('P1D'), (new DateTime($tgl_akhir))->modify('+1 day'));
            foreach (array_keys($semua_kandang_data) as $id_k) {
                $harga_terakhir_lookup = 0;
                 if (isset($harga_pakan_per_kandang_tanggal[$id_k])) {
                      ksort($harga_pakan_per_kandang_tanggal[$id_k]);
                      foreach($harga_pakan_per_kandang_tanggal[$id_k] as $tgl_beli => $harga){
                         if($tgl_beli < $tgl_awal){ $harga_terakhir_lookup = $harga; } else { break; }
                    }
                 }
                foreach ($tanggal_iterator as $tanggal_obj) {
                    $tanggal_str = $tanggal_obj->format('Y-m-d');
                    if (isset($harga_pakan_per_kandang_tanggal[$id_k][$tanggal_str])) {
                        $harga_terakhir_lookup = $harga_pakan_per_kandang_tanggal[$id_k][$tanggal_str];
                    }
                    $harga_pakan_terkini[$id_k][$tanggal_str] = $harga_terakhir_lookup;
                }
            }
        } else {
            die("Error preparing statement harga pakan: " . $koneksi->error);
        }
    }
}

$html = '<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Riwayat Laporan</title>
<style>
    body { font-family: sans-serif; font-size: 8pt; }
    .header { text-align: center; margin-bottom: 15px; }
    .header h1 { font-size: 14pt; margin: 0; }
    .header p { font-size: 9pt; margin: 5px 0; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    th, td { border: 1px solid #666; padding: 3px 4px; vertical-align: middle; }
    thead th { background-color: #E0E0E0; font-weight: bold; text-align: center; }
    tbody td { font-size: 7.5pt; } 
    .text-center { text-align: center; }
    .text-right { text-align: right; }
    .text-left { text-align: left; }
    .text-danger { color: #dc3545; }
    .text-warning { color: #ffc107; }
    .text-success { color: #198754; }
    .font-bold { font-weight: bold; }
    .bg-info { background-color: #cfe2ff !important; } 
    .bg-primary { background-color: #b9d3ff !important; } /* Warna baru untuk Sisa Stok */
    .bg-warning { background-color: #fff3cd !important; } 
    .bg-success { background-color: #d1e7dd !important; } 
    .wrap { word-wrap: break-word; word-break: break-all; } 
    .col-tgl { width: 5%; }
    '.($semua_kandang_mode ? '.col-kandang { width: 8%; }' : '').'
    .col-ayam { width: 4%; }
    .col-ayam-total { width: 5%; }
    .col-ayam-sisa { width: 6%; } /* Kolom Sisa Stok */
    .col-pakan-harga { width: 6%; }
    .col-pakan-kg { width: 5%; }
    .col-pakan-total { width: 7%; }
    .col-telur { width: 5%; }
    .col-telur-total { width: 6%; }
    .col-jual-kg { width: 5%; }
    .col-jual-harga { width: 6%; }
    .col-jual-total { width: 7%; }
    .col-pengeluaran { width: 7%; }
    .col-ket-pengeluaran { width: auto; }

     thead { display: table-header-group; }
     tbody { display: table-row-group; }
     tr { page-break-inside: avoid; }

     @page { margin: 30px 20px 40px 20px; } 
      #footer { position: fixed; bottom: -25px; left: 0px; right: 0px; height: 30px; font-size: 7pt; }
      #footer .page-number:before { content: "Hal. " counter(page); }
      #footer .page-total:before { content: counter(pages); }
      #footer .print-date { text-align: left; }
      #footer .page-info { text-align: right; }
</style>
</head>
<body>';

$html .= '<div class="header">';
$html .= '<h1>Riwayat Laporan - ' . htmlspecialchars($nama_header) . '</h1>';
$html .= '<p>Periode: ' . date('d M Y', strtotime($tgl_awal)) . ' s/d ' . date('d M Y', strtotime($tgl_akhir)) . '</p>';
$html .= '</div>';

$html .= '<div id="footer">
            <table>
                <tr>
                    <td class="print-date" style="border:none;">Dicetak: '.date('d M Y H:i').'</td>
                    <td class="page-info" style="border:none;"><span class="page-number"></span> / <span class="page-total"></span></td>
                </tr>
            </table>
          </div>';

$html .= '<table>';
$html .= '<thead>';
$html .= '<tr>';
$html .= '<th rowspan="2" class="col-tgl">Tgl</th>';
if ($semua_kandang_mode) { $html .= '<th rowspan="2" class="col-kandang">Kandang</th>'; }
$html .= '<th colspan="5">Ayam (Ekor)</th>';
$html .= '<th colspan="3">Pakan</th>';
$html .= '<th colspan="4">Produksi Telur (Kg)</th>';
$html .= '<th colspan="3">Penjualan Telur</th>';
$html .= '<th rowspan="2" class="col-pengeluaran">Pengeluaran (Rp)</th>';
$html .= '<th rowspan="2" class="col-ket-pengeluaran">Ket. Pengeluaran</th>';
$html .= '</tr>';
$html .= '<tr>';
$html .= '<th class="col-ayam">Masuk</th><th class="col-ayam">Mati</th><th class="col-ayam">Afkir</th><th class="col-ayam-total bg-info">Perubahan</th><th class="col-ayam-sisa bg-primary">Sisa Stok</th>';
$html .= '<th class="col-pakan-harga">Harga/Kg</th><th class="col-pakan-kg">Terpakai (Kg)</th><th class="col-pakan-total bg-warning">Total Biaya</th>';
$html .= '<th class="col-telur">Baik</th><th class="col-telur">Tipis</th><th class="col-telur">Pecah</th><th class="col-telur-total bg-success">Total</th>';
$html .= '<th class="col-jual-kg">Kg</th><th class="col-jual-harga">Harga/Kg</th><th class="col-jual-total bg-info">Total Rp</th>';
$html .= '</tr>';
$html .= '</thead>';
$html .= '<tbody>';

if (!$ada_data_laporan) { 
    $colspan = $semua_kandang_mode ? 19 : 18;
    $html .= '<tr><td colspan="'.$colspan.'" class="text-center">Tidak ada data laporan untuk periode dan filter yang dipilih.</td></tr>';
} else {
    foreach ($laporan_final_untuk_pdf as $laporan) { 
        $tanggal = $laporan['tanggal'];
        $id_kandang_laporan = $laporan['id_kandang'];

        $total_ayam_hari = ($laporan['ayam_masuk'] ?? 0) - ($laporan['ayam_mati'] ?? 0) - ($laporan['ayam_afkir'] ?? 0);
        $sisa_ayam_kumulatif = $laporan['sisa_ayam_kumulatif'] ?? 0; // Ambil data baru
        $harga_pakan = $harga_pakan_terkini[$id_kandang_laporan][$tanggal] ?? 0;
        $biaya_pakan = ($laporan['pakan_terpakai_kg'] ?? 0) * $harga_pakan;
        $produksi_total = ($laporan['telur_baik_kg'] ?? 0) + ($laporan['telur_tipis_kg'] ?? 0) + ($laporan['telur_pecah_kg'] ?? 0);
        
        $pengeluaran_total = $pengeluaran_harian[$tanggal][$id_kandang_laporan]['total'] ?? 0;
        $pengeluaran_detail = $pengeluaran_harian[$tanggal][$id_kandang_laporan]['detail'] ?? '';

        $html .= '<tr>';
        $html .= '<td class="text-center">' . date('d/m/y', strtotime($tanggal)) . '</td>';
        if ($semua_kandang_mode) { $html .= '<td class="text-left wrap">' . htmlspecialchars($laporan['nama_kandang']) . '</td>'; }
        
        $html .= '<td class="text-center">' . number_format($laporan['ayam_masuk'] ?? 0) . '</td>';
        $html .= '<td class="text-center text-danger">' . number_format($laporan['ayam_mati'] ?? 0) . '</td>';
        $html .= '<td class="text-center text-warning">' . number_format($laporan['ayam_afkir'] ?? 0) . '</td>';
        $html .= '<td class="text-center font-bold bg-info '.(($total_ayam_hari >= 0) ? 'text-success' : 'text-danger').'">' . ($total_ayam_hari >= 0 ? '+' : '') . number_format($total_ayam_hari) . '</td>';
        $html .= '<td class="text-right font-bold bg-primary">' . number_format($sisa_ayam_kumulatif) . '</td>'; // Kolom Sisa Stok baru
        
        $html .= '<td class="text-right">' . number_format($harga_pakan) . '</td>';
        $html .= '<td class="text-right">' . number_format($laporan['pakan_terpakai_kg'] ?? 0, 2, ',', '.') . '</td>';
        $html .= '<td class="text-right font-bold bg-warning">' . number_format($biaya_pakan) . '</td>';

        $html .= '<td class="text-right">' . number_format($laporan['telur_baik_kg'] ?? 0, 2, ',', '.') . '</td>';
        $html .= '<td class="text-right">' . number_format($laporan['telur_tipis_kg'] ?? 0, 2, ',', '.') . '</td>';
        $html .= '<td class="text-right">' . number_format($laporan['telur_pecah_kg'] ?? 0, 2, ',', '.') . '</td>';
        $html .= '<td class="text-right font-bold bg-success">' . number_format($produksi_total, 2, ',', '.') . '</td>';

        $html .= '<td class="text-right">' . number_format($laporan['telur_terjual_kg'] ?? 0, 2, ',', '.') . '</td>';
        $html .= '<td class="text-right">' . number_format($laporan['harga_jual_rata2'] ?? 0) . '</td>';
        $html .= '<td class="text-right font-bold bg-info">' . number_format($laporan['pemasukan_telur'] ?? 0) . '</td>';

        $html .= '<td class="text-right">' . number_format($pengeluaran_total) . '</td>';
        $html .= '<td class="text-left wrap">' . htmlspecialchars($pengeluaran_detail) . '</td>';

        $html .= '</tr>';
    }
}

$html .= '</tbody>';
$html .= '</table>';
$html .= '</body></html>';

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true); 
$options->set('defaultFont', 'sans-serif'); 

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('legal', 'landscape'); 
$dompdf->render();

$safeFilename = "Riwayat_Laporan_" . preg_replace('/[^A-Za-z0-9_-]/', '_', $nama_header) . "_" . $tgl_awal . "_sd_" . $tgl_akhir . ".pdf";
$dompdf->stream($safeFilename, ["Attachment" => true]); 

exit;
?>