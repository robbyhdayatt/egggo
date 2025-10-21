<?php
// Include Composer autoload and database connection
require '../vendor/autoload.php';
include '../config/database.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

// Ambil parameter filter dari URL
$id_kandang_terpilih = $_GET['id_kandang'] ?? '';
$tgl_awal = $_GET['tgl_awal'] ?? date('Y-m-01');
$tgl_akhir = $_GET['tgl_akhir'] ?? date('Y-m-t');

$semua_kandang_mode = ($id_kandang_terpilih === 'semua');
$nama_header = "Semua Kandang Aktif";
$id_kandang_int = null;
$semua_kandang_data = [];
$ada_data = false; // Flag
$daftar_id_kandang_proses = [];
if ($semua_kandang_mode) {
    $nama_header = "Semua Kandang"; 
    $result_kandang = $koneksi->query("SELECT * FROM kandang"); 
    if($result_kandang) {
        while($row = $result_kandang->fetch_assoc()) {
            $semua_kandang_data[$row['id_kandang']] = $row;
            $daftar_id_kandang_proses[] = $row['id_kandang'];
        }
    }
} else {
    $id_kandang_int = (int)$id_kandang_terpilih;
    $stmt_kandang = $koneksi->prepare("SELECT * FROM kandang WHERE id_kandang = ?");
    if($stmt_kandang){
        $stmt_kandang->bind_param("i", $id_kandang_int);
        $stmt_kandang->execute();
        $master_kandang = $stmt_kandang->get_result()->fetch_assoc();
        if ($master_kandang) {
            $nama_header = $master_kandang['nama_kandang'];
            $semua_kandang_data[$id_kandang_int] = $master_kandang; 
            $daftar_id_kandang_proses[] = $id_kandang_int;
        }
        $stmt_kandang->close();
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
$params_laporan = [$tgl_awal, $tgl_akhir]; $types_laporan = "ss";
if (!$semua_kandang_mode && $id_kandang_int !== null) {
    $query_laporan .= " AND lh.id_kandang = ?";
    $params_laporan[] = $id_kandang_int; $types_laporan .= "i";
}
 $query_laporan .= " ORDER BY lh.tanggal ASC, k.nama_kandang ASC"; // HARUS ASC

$stmt_laporan = $koneksi->prepare($query_laporan);
if ($stmt_laporan && !empty($params_laporan)) {
    $stmt_laporan->bind_param($types_laporan, ...$params_laporan);
    $stmt_laporan->execute();
    $result_laporan = $stmt_laporan->get_result();
    if($result_laporan->num_rows > 0) $ada_data = true;
    while ($row = $result_laporan->fetch_assoc()) {
        $laporan_lengkap_asc[] = $row;
    }
    $stmt_laporan->close();
}

$laporan_final_untuk_excel = []; 

if($ada_data){

    foreach ($laporan_lengkap_asc as $laporan) {
        $id_k = $laporan['id_kandang'];
        if (!isset($stok_ayam_berjalan_per_kandang[$id_k])) {
             $stok_ayam_berjalan_per_kandang[$id_k] = $semua_kandang_data[$id_k]['populasi_awal'] ?? 0;
        }
        $sisa_ayam_hari_ini = $stok_ayam_berjalan_per_kandang[$id_k] + ($laporan['ayam_masuk'] ?? 0) - ($laporan['ayam_mati'] ?? 0) - ($laporan['ayam_afkir'] ?? 0);
        $laporan['sisa_ayam_kumulatif'] = $sisa_ayam_hari_ini;
        $stok_ayam_berjalan_per_kandang[$id_k] = $sisa_ayam_hari_ini;
        
        $laporan_final_untuk_excel[] = $laporan; 
    }
    $laporan_final_untuk_excel = array_reverse($laporan_final_untuk_excel);
    $pengeluaran_harian = [];
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
    $query_harga_pakan = "
        SELECT id_kandang, tanggal_beli, harga_per_kg 
        FROM stok_pakan 
        WHERE tanggal_beli <= ? "; 
    $params_harga = [$tgl_akhir];
    $types_harga = "s";

    if ($id_kandang_terpilih !== 'semua' && $id_kandang_int !== null) {
        $query_harga_pakan .= " AND id_kandang = ?";
        $params_harga[] = $id_kandang_int;
        $types_harga .= "i";
    }
     $query_harga_pakan .= " ORDER BY id_kandang ASC, tanggal_beli DESC, id_stok DESC"; 

    $stmt_harga = $koneksi->prepare($query_harga_pakan);
    if ($stmt_harga && !empty($params_harga)) {
        $stmt_harga->bind_param($types_harga, ...$params_harga);
        $stmt_harga->execute();
        $result_harga = $stmt_harga->get_result();
        
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
    }

}

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Tentukan jumlah kolom berdasarkan mode
$lastColIndexNumeric = 18;
if ($semua_kandang_mode) {
    $lastColIndexNumeric = 19;
}
$lastColLetter = Coordinate::stringFromColumnIndex($lastColIndexNumeric);

$sheet->mergeCells('A1:'.$lastColLetter.'1'); 
$sheet->setCellValue('A1', "Riwayat Laporan - " . htmlspecialchars($nama_header));
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->mergeCells('A2:'.$lastColLetter.'2');
$sheet->setCellValue('A2', "Periode: " . date('d M Y', strtotime($tgl_awal)) . ' - ' . date('d M Y', strtotime($tgl_akhir)));
$sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(10);
$sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getRowDimension('1')->setRowHeight(20);
$sheet->getRowDimension('2')->setRowHeight(15);

$headerRow1 = ['Tgl'];
$headerRow2 = ['']; 
$colIndexNumeric = 1; 

if ($semua_kandang_mode) {
    $headerRow1[] = 'Kandang'; $headerRow2[] = ''; $colIndexNumeric++;
}
$startColAyam = Coordinate::stringFromColumnIndex($colIndexNumeric);
$headerRow1[] = 'Ayam (Ekor)'; $headerRow1[] = ''; $headerRow1[] = ''; $headerRow1[] = ''; $headerRow1[] = '';
$headerRow2[] = 'Masuk'; $headerRow2[] = 'Mati'; $headerRow2[] = 'Afkir'; $headerRow2[] = 'Perubahan'; $headerRow2[] = 'Sisa Stok'; // 5 sub-kolom
$colIndexNumeric += 5; 
$endColAyam = Coordinate::stringFromColumnIndex($colIndexNumeric - 1); 
$sheet->mergeCells($startColAyam.'4:'.$endColAyam.'4');

$startColPakan = Coordinate::stringFromColumnIndex($colIndexNumeric);
$headerRow1[] = 'Pakan'; $headerRow1[] = ''; $headerRow1[] = '';
$headerRow2[] = 'Harga/Kg'; $headerRow2[] = 'Terpakai (Kg)'; $headerRow2[] = 'Total Biaya';
$colIndexNumeric += 3;
$endColPakan = Coordinate::stringFromColumnIndex($colIndexNumeric - 1);
$sheet->mergeCells($startColPakan.'4:'.$endColPakan.'4');

$startColProd = Coordinate::stringFromColumnIndex($colIndexNumeric);
$headerRow1[] = 'Produksi Telur (Kg)'; $headerRow1[] = ''; $headerRow1[] = ''; $headerRow1[] = '';
$headerRow2[] = 'Baik'; $headerRow2[] = 'Tipis'; $headerRow2[] = 'Pecah'; $headerRow2[] = 'Total';
$colIndexNumeric += 4;
$endColProd = Coordinate::stringFromColumnIndex($colIndexNumeric - 1);
$sheet->mergeCells($startColProd.'4:'.$endColProd.'4');

$startColJual = Coordinate::stringFromColumnIndex($colIndexNumeric);
$headerRow1[] = 'Penjualan Telur'; $headerRow1[] = ''; $headerRow1[] = '';
$headerRow2[] = 'Kg'; $headerRow2[] = 'Harga/Kg'; $headerRow2[] = 'Total Rp';
$colIndexNumeric += 3;
$endColJual = Coordinate::stringFromColumnIndex($colIndexNumeric - 1);
$sheet->mergeCells($startColJual.'4:'.$endColJual.'4');

$colPengeluaran = Coordinate::stringFromColumnIndex($colIndexNumeric);
$headerRow1[] = 'Pengeluaran (Rp)'; $headerRow2[] = '';
$sheet->mergeCells($colPengeluaran.'4:'.$colPengeluaran.'5'); 
$colIndexNumeric++;

$colKetPengeluaran = Coordinate::stringFromColumnIndex($colIndexNumeric);
$headerRow1[] = 'Ket. Pengeluaran'; $headerRow2[] = '';
$sheet->mergeCells($colKetPengeluaran.'4:'.$colKetPengeluaran.'5'); 

$sheet->fromArray($headerRow1, NULL, 'A4');
$sheet->fromArray($headerRow2, NULL, 'A5');

$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F81BD']],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]]
];
$sheet->getStyle('A4:'.$lastColLetter.'5')->applyFromArray($headerStyle);
$sheet->getRowDimension('4')->setRowHeight(20);
$sheet->getRowDimension('5')->setRowHeight(30);
$sheet->mergeCells('A4:A5');
if ($semua_kandang_mode) { $sheet->mergeCells('B4:B5'); }

$rowNum = 6;
foreach ($laporan_final_untuk_excel as $laporan) {
    $rowData = [];
    $tanggal = $laporan['tanggal'];
    $id_kandang_laporan = $laporan['id_kandang'];

    $rowData[] = \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($tanggal); 
    if ($semua_kandang_mode) {
        $rowData[] = $laporan['nama_kandang'];
    }

    $ayam_masuk = $laporan['ayam_masuk'] ?? 0;
    $ayam_mati = $laporan['ayam_mati'] ?? 0;
    $ayam_afkir = $laporan['ayam_afkir'] ?? 0;
    $total_ayam_hari = $ayam_masuk - $ayam_mati - $ayam_afkir;
    $sisa_ayam_kumulatif = $laporan['sisa_ayam_kumulatif'] ?? 0;
    $rowData[] = $ayam_masuk;
    $rowData[] = $ayam_mati;
    $rowData[] = $ayam_afkir;
    $rowData[] = $total_ayam_hari;
    $rowData[] = $sisa_ayam_kumulatif;
    $harga_pakan = $harga_pakan_terkini[$id_kandang_laporan][$tanggal] ?? 0;
    $pakan_terpakai = $laporan['pakan_terpakai_kg'] ?? 0;
    $biaya_pakan = $pakan_terpakai * $harga_pakan;
    $rowData[] = $harga_pakan;
    $rowData[] = $pakan_terpakai;
    $rowData[] = $biaya_pakan;

    $telur_baik = $laporan['telur_baik_kg'] ?? 0;
    $telur_tipis = $laporan['telur_tipis_kg'] ?? 0;
    $telur_pecah = $laporan['telur_pecah_kg'] ?? 0;
    $produksi_total = $telur_baik + $telur_tipis + $telur_pecah;
    $rowData[] = $telur_baik;
    $rowData[] = $telur_tipis;
    $rowData[] = $telur_pecah;
    $rowData[] = $produksi_total;

    $telur_terjual = $laporan['telur_terjual_kg'] ?? 0;
    $harga_jual = $laporan['harga_jual_rata2'] ?? 0;
    $pemasukan_telur = $laporan['pemasukan_telur'] ?? 0;
    $rowData[] = $telur_terjual;
    $rowData[] = $harga_jual;
    $rowData[] = $pemasukan_telur;

    $pengeluaran_total = $pengeluaran_harian[$tanggal][$id_kandang_laporan]['total'] ?? 0;
    $pengeluaran_detail = $pengeluaran_harian[$tanggal][$id_kandang_laporan]['detail'] ?? '';
    $rowData[] = $pengeluaran_total;
    $rowData[] = $pengeluaran_detail; 

    $sheet->fromArray($rowData, NULL, 'A'.$rowNum);
    $rowNum++;
}

$lastRow = $rowNum - 1;
if ($lastRow >= 6) { 
    $sheet->getStyle('A6:A'.$lastRow)->getNumberFormat()->setFormatCode('DD/MM/YYYY');
    $sheet->getColumnDimension('A')->setWidth(12);

    $startNumColIdx = 2; 
    if ($semua_kandang_mode) {
        $sheet->getColumnDimension('B')->setWidth(20);
        $startNumColIdx = 3; 
    }

    $colIndices = range($startNumColIdx, $lastColIndexNumeric); 
    $intFormatCols = [];
    $decFormatCols = [];

    $ayamStartIdx = $startNumColIdx;
    $intFormatCols = array_merge($intFormatCols, range($ayamStartIdx, $ayamStartIdx + 4));

    $pakanStartIdx = $ayamStartIdx + 5;
    $intFormatCols[] = $pakanStartIdx;
    $decFormatCols[] = $pakanStartIdx + 1;
    $intFormatCols[] = $pakanStartIdx + 2;

    $prodStartIdx = $pakanStartIdx + 3;
    $decFormatCols = array_merge($decFormatCols, range($prodStartIdx, $prodStartIdx + 3)); 

    $jualStartIdx = $prodStartIdx + 4;
    $decFormatCols[] = $jualStartIdx;
    $intFormatCols[] = $jualStartIdx + 1;
    $intFormatCols[] = $jualStartIdx + 2;

    $pengeluaranColIdx = $jualStartIdx + 3;
    $intFormatCols[] = $pengeluaranColIdx;

    $ketPengeluaranColIdx = $pengeluaranColIdx + 1;

    foreach ($intFormatCols as $colIdx) {
        $colLetter = Coordinate::stringFromColumnIndex($colIdx);
        $sheet->getStyle($colLetter.'6:'.$colLetter.$lastRow)->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        $sheet->getStyle($colLetter.'6:'.$colLetter.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    }
    foreach ($decFormatCols as $colIdx) {
        $colLetter = Coordinate::stringFromColumnIndex($colIdx);
        $sheet->getStyle($colLetter.'6:'.$colLetter.$lastRow)->getNumberFormat()->setFormatCode('#,##0.00');
         $sheet->getColumnDimension($colLetter)->setAutoSize(true);
         $sheet->getStyle($colLetter.'6:'.$colLetter.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    }

    $colKetPengeluaranLetter = Coordinate::stringFromColumnIndex($ketPengeluaranColIdx);
    $sheet->getColumnDimension($colKetPengeluaranLetter)->setWidth(35);
    $sheet->getStyle($colKetPengeluaranLetter.'6:'.$colKetPengeluaranLetter.$lastRow)->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP)->setHorizontal(Alignment::HORIZONTAL_LEFT);

    $sheet->getStyle('A6:A'.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $colTotalAyam = Coordinate::stringFromColumnIndex($ayamStartIdx + 3);
    $colSisaAyam = Coordinate::stringFromColumnIndex($ayamStartIdx + 4);
    $sheet->getStyle($colTotalAyam.'6:'.$colTotalAyam.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle($colSisaAyam.'6:'.$colSisaAyam.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT); 
    
    $sheet->getStyle('A6:'.$lastColLetter.$lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

    $sheet->freezePane('A6');
    $sheet->setAutoFilter('A5:'.$lastColLetter.'5');
} else {
     $sheet->setCellValue('A6', 'Tidak ada data laporan untuk periode dan filter yang dipilih.');
     $sheet->mergeCells('A6:'.$lastColLetter.'6');
     $sheet->getStyle('A6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
}

$safeFilename = "Riwayat_Laporan_" . preg_replace('/[^A-Za-z0-9_-]/', '_', $nama_header) . "_" . $tgl_awal . "_sd_" . $tgl_akhir . ".xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $safeFilename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
try {
    $writer->save('php://output');
} catch (\PhpOffice\PhpSpreadsheet\Writer\Exception $e) {
    echo 'Error writing file: ', $e->getMessage();
}
exit;
?>