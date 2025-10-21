<?php
include '../templates/header.php';
// Ambil variabel role global dari header.php
global $current_user_role, $current_assigned_kandang_id;
global $folder_base; // Ambil folder base

// Cek notifikasi
$pesan = '';
if (isset($_GET['status'])) {
    if ($_GET['status'] == 'sukses_update') {
         $pesan = "<div class='alert alert-success mt-3'>Data laporan berhasil diperbarui!</div>";
    } elseif ($_GET['status'] == 'sukses_hapus') {
         $pesan = "<div class='alert alert-success mt-3'>Data laporan berhasil dihapus!</div>";
    }
}

// --- MODIFIKASI QUERY KANDANG ---
$kandang_query = "SELECT id_kandang, nama_kandang FROM kandang"; // Pimpinan bisa lihat semua
if ($current_user_role === 'Karyawan' && $current_assigned_kandang_id) {
    $kandang_query .= " WHERE id_kandang = " . (int)$current_assigned_kandang_id;
}
$kandang_query .= " ORDER BY nama_kandang";
$kandang_list = $koneksi->query($kandang_query);
// --- AKHIR MODIFIKASI ---


// Tentukan filter yang dipilih
$id_kandang_terpilih = $_GET['id_kandang'] ?? '';
$tgl_awal = isset($_GET['tgl_awal']) ? $_GET['tgl_awal'] : date('Y-m-01');
$tgl_akhir = isset($_GET['tgl_akhir']) ? $_GET['tgl_akhir'] : date('Y-m-t');

// --- PAKSA ID KANDANG UNTUK KARYAWAN ---
if ($current_user_role === 'Karyawan') {
    $id_kandang_terpilih = $current_assigned_kandang_id;
}
// --- AKHIR PEMAKSAAN ---


$laporan_lengkap_final = []; // Array baru untuk data yang sudah dihitung
$pengeluaran_final = []; 
$harga_pakan_terkini = []; 
$nama_header = "Pilih Kandang";
$semua_kandang_data = []; 
$ada_data = false; 

if (!empty($id_kandang_terpilih)) {

    // --- 1. Ambil Data Master Kandang ---
    $id_kandang_int = null; 
    $daftar_id_kandang_proses = []; // Daftar ID kandang yg akan diproses
    
    if ($id_kandang_terpilih === 'semua') {
        $nama_header = "Semua Kandang";
        $result_kandang = $koneksi->query("SELECT * FROM kandang"); 
        if($result_kandang) {
            while($row = $result_kandang->fetch_assoc()) {
                $semua_kandang_data[$row['id_kandang']] = $row;
                $daftar_id_kandang_proses[] = $row['id_kandang']; // Tambahkan ke daftar
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
                $daftar_id_kandang_proses[] = $id_kandang_int; // Tambahkan ke daftar
            }
            $stmt_kandang->close();
        }
    }
    
    $ids_string_proses = !empty($daftar_id_kandang_proses) ? implode(',', $daftar_id_kandang_proses) : '0';

    // --- 2. HITUNG STOK AYAM AWAL (SEBELUM tgl_awal) ---
    $sisa_ayam_awal_per_kandang = [];
    $query_stok_awal = "
        SELECT 
            k.id_kandang, 
            k.populasi_awal, 
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
    
    // Inisialisasi stok berjalan
    $stok_ayam_berjalan_per_kandang = $sisa_ayam_awal_per_kandang;

    // --- 3. Ambil Data Laporan Harian (ORDER BY ASC untuk kalkulasi kumulatif) ---
    $query_laporan = "
        SELECT lh.*, k.nama_kandang 
        FROM laporan_harian lh
        JOIN kandang k ON lh.id_kandang = k.id_kandang
        WHERE lh.tanggal BETWEEN ? AND ?
    ";
    $params_laporan = [$tgl_awal, $tgl_akhir];
    $types_laporan = "ss";

    if ($id_kandang_terpilih !== 'semua' && $id_kandang_int !== null) {
        $query_laporan .= " AND lh.id_kandang = ?";
        $params_laporan[] = $id_kandang_int;
        $types_laporan .= "i";
    }
     $query_laporan .= " ORDER BY lh.tanggal ASC, k.nama_kandang ASC"; // HARUS ASC

    $stmt_laporan = $koneksi->prepare($query_laporan);
    if ($stmt_laporan && !empty($params_laporan)) {
        $stmt_laporan->bind_param($types_laporan, ...$params_laporan);
        $stmt_laporan->execute();
        $result_laporan = $stmt_laporan->get_result();
        
        while ($row = $result_laporan->fetch_assoc()) {
            $ada_data = true; // Set flag
            $id_k = $row['id_kandang'];
            
            // --- KALKULASI SISA AYAM KUMULATIF ---
            // Pastikan stok berjalan sudah diinisialisasi
            if (!isset($stok_ayam_berjalan_per_kandang[$id_k])) {
                 // Jika kandang baru muncul di tengah periode (jarang terjadi), hitung ulang stok awalnya
                 // (Lebih baik dihandle oleh query stok awal, tapi sebagai fallback)
                 $stok_ayam_berjalan_per_kandang[$id_k] = $semua_kandang_data[$id_k]['populasi_awal'] ?? 0;
            }
            
            $ayam_masuk_hari_ini = $row['ayam_masuk'] ?? 0;
            $ayam_mati_hari_ini = $row['ayam_mati'] ?? 0;
            $ayam_afkir_hari_ini = $row['ayam_afkir'] ?? 0;
            
            $sisa_ayam_hari_ini = $stok_ayam_berjalan_per_kandang[$id_k] + $ayam_masuk_hari_ini - $ayam_mati_hari_ini - $ayam_afkir_hari_ini;
            
            $row['sisa_ayam_kumulatif'] = $sisa_ayam_hari_ini; // Tambahkan data baru ke array
            
            // Update stok berjalan untuk iterasi berikutnya (kandang yg sama, tanggal berikutnya)
            $stok_ayam_berjalan_per_kandang[$id_k] = $sisa_ayam_hari_ini;
            // --- AKHIR KALKULASI ---
            
            $laporan_lengkap_final[] = $row; // Masukkan ke array final
        }
        $stmt_laporan->close();
    }

    // Lanjutkan hanya jika ada data laporan
    if($ada_data){
        // --- 4. Ambil Data Pengeluaran ---
        // (Query ini tidak perlu diubah, tapi kita akan mengambil datanya berdasarkan tanggal dari $laporan_lengkap_final)
        $query_pengeluaran = "
            SELECT p.tanggal_pengeluaran, p.id_kandang, p.jumlah, p.keterangan, kat.nama_kategori
            FROM pengeluaran p
            LEFT JOIN kategori_pengeluaran kat ON p.id_kategori = kat.id_kategori
            WHERE p.tanggal_pengeluaran BETWEEN ? AND ?
        ";
        $params_pengeluaran = [$tgl_awal, $tgl_akhir];
        $types_pengeluaran = "ss";

        if ($id_kandang_terpilih !== 'semua' && $id_kandang_int !== null) {
            $query_pengeluaran .= " AND p.id_kandang = ?";
            $params_pengeluaran[] = $id_kandang_int;
            $types_pengeluaran .= "i";
        }
         $query_pengeluaran .= " ORDER BY p.tanggal_pengeluaran ASC, p.id_kandang ASC";

        $stmt_pengeluaran = $koneksi->prepare($query_pengeluaran);
         if ($stmt_pengeluaran && !empty($params_pengeluaran)) {
            $stmt_pengeluaran->bind_param($types_pengeluaran, ...$params_pengeluaran);
            $stmt_pengeluaran->execute();
            $result_pengeluaran = $stmt_pengeluaran->get_result();
            while ($row = $result_pengeluaran->fetch_assoc()) {
                $tanggal = $row['tanggal_pengeluaran'];
                $id_k = $row['id_kandang'];
                if (!isset($pengeluaran_final[$tanggal][$id_k])) {
                    $pengeluaran_final[$tanggal][$id_k] = ['total' => 0, 'detail_array' => []];
                }
                $pengeluaran_final[$tanggal][$id_k]['total'] += $row['jumlah'];
                $pengeluaran_final[$tanggal][$id_k]['detail_array'][] = [
                    'kategori' => $row['nama_kategori'] ?? 'Lain-lain',
                    'keterangan' => $row['keterangan'], 
                    'jumlah' => $row['jumlah']
                ];
            }
            $stmt_pengeluaran->close();
         }

        // --- 5. Ambil Harga Pakan Terkini ---
        // (Query ini tidak berubah)
        $query_harga_pakan = "
            SELECT id_kandang, tanggal_beli, harga_per_kg 
            FROM stok_pakan 
            WHERE tanggal_beli <= ? "; 
        $params_harga = [$tgl_akhir];
        $types_harga = "s";
        if ($id_kandang_terpilih !== 'semua' && $id_kandang_int !== null) {
            $query_harga_pakan .= " AND id_kandang = ?";
            $params_harga[] = $id_kandang_int; $types_harga .= "i";
        }
         $query_harga_pakan .= " ORDER BY id_kandang ASC, tanggal_beli DESC, id_stok DESC"; 
        $stmt_harga = $koneksi->prepare($query_harga_pakan);
        if ($stmt_harga && !empty($params_harga)) {
            $stmt_harga->bind_param($types_harga, ...$params_harga);
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
        } // end if $stmt_harga
        
        // Reverse array $laporan_lengkap_final agar data terbaru di atas untuk tampilan
        $laporan_lengkap_final = array_reverse($laporan_lengkap_final, false); // false agar key tidak di-preserve
    } // End if($ada_data)
}
?>

<div class="container-fluid">
    <div class="page-header">
        <h1 class="page-title">Riwayat Laporan</h1>
        <p class="page-subtitle">Lihat riwayat laporan harian yang detail untuk setiap kandang.</p>
    </div>
    
    <?php echo $pesan; ?>

    <div class="card mb-4">
        <div class="card-body">
            <form class="row g-3 needs-validation" method="GET" novalidate>
                 <div class="col-md-5">
                    <label for="id_kandang" class="form-label">Pilih Kandang</label>
                    <select class="form-select" id="id_kandang" name="id_kandang" required <?php echo ($current_user_role === 'Karyawan') ? 'disabled' : ''; ?>>
                        <?php if ($current_user_role === 'Pimpinan'): ?>
                            <option value="">-- Pilih Opsi --</option>
                            <option value="semua" <?php echo ($id_kandang_terpilih == 'semua') ? 'selected' : ''; ?>>Semua Kandang</option>
                        <?php endif; ?>
                        <?php if ($kandang_list && $kandang_list->num_rows > 0): ?>
                            <?php mysqli_data_seek($kandang_list, 0); ?>
                            <?php while($k = $kandang_list->fetch_assoc()): ?>
                                <option value="<?php echo $k['id_kandang']; ?>" 
                                    <?php 
                                    if ($current_user_role === 'Karyawan' || ($k['id_kandang'] == $id_kandang_terpilih && $id_kandang_terpilih != 'semua')) {
                                        echo 'selected';
                                    }
                                    ?>
                                >
                                    <?php echo htmlspecialchars($k['nama_kandang']); ?>
                                </option>
                            <?php endwhile; ?>
                         <?php elseif ($current_user_role === 'Pimpinan'): ?>
                             <option value="" disabled>Belum ada data kandang</option>
                        <?php endif; ?>
                    </select>
                     <div class="invalid-feedback">Silakan pilih kandang.</div>
                </div>
                <?php if ($current_user_role === 'Karyawan' && $current_assigned_kandang_id): ?>
                    <input type="hidden" name="id_kandang" value="<?php echo $current_assigned_kandang_id; ?>" />
                <?php endif; ?>
                <div class="col-md-3">
                    <label for="tgl_awal" class="form-label">Dari Tanggal</label>
                    <input type="date" class="form-control" id="tgl_awal" name="tgl_awal" value="<?php echo $tgl_awal; ?>" required>
                    <div class="invalid-feedback">Tanggal awal wajib diisi.</div>
                </div>
                <div class="col-md-3">
                    <label for="tgl_akhir" class="form-label">Sampai Tanggal</label>
                    <input type="date" class="form-control" id="tgl_akhir" name="tgl_akhir" value="<?php echo $tgl_akhir; ?>" required>
                     <div class="invalid-feedback">Tanggal akhir wajib diisi.</div>
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Cari</button>
                </div>
            </form>
            
            <?php if ($id_kandang_terpilih && $ada_data): 
                $export_params = http_build_query([
                    'id_kandang' => $id_kandang_terpilih, 'tgl_awal' => $tgl_awal, 'tgl_akhir' => $tgl_akhir
                ]);
            ?>
            <div class="mt-3">
                 <span class="me-2">Ekspor Laporan:</span>
                 <div class="btn-group btn-group-sm" role="group">
                    <a href="export_excel_server.php?<?php echo $export_params; ?>" class="btn btn-success" target="_blank" data-bs-toggle="tooltip" title="Ekspor ke Excel">
                        <i class="fas fa-file-excel"></i> Excel
                    </a>
                    <a href="export_pdf_server.php?<?php echo $export_params; ?>" class="btn btn-danger" target="_blank" data-bs-toggle="tooltip" title="Ekspor ke PDF">
                        <i class="fas fa-file-pdf"></i> PDF
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($id_kandang_terpilih && $ada_data): ?>
    <div class="card">
        <div class="card-header">Riwayat untuk: <?php echo htmlspecialchars($nama_header); ?> (<?php echo date('d M Y', strtotime($tgl_awal)) . ' - ' . date('d M Y', strtotime($tgl_akhir)); ?>)</div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover" id="tabelRiwayat" style="width:100%; font-size: 0.85rem;">
                    <thead class="text-center table-light" style="vertical-align: middle;">
                        <tr>
                            <th rowspan="2">Tgl</th>
                            <?php if ($current_user_role === 'Pimpinan' && $id_kandang_terpilih === 'semua') echo '<th rowspan="2">Kandang</th>'; ?>
                            <th colspan="5">Ayam (Ekor)</th> <th colspan="3">Pakan</th> 
                            <th colspan="4">Produksi Telur (Kg)</th>
                            <th colspan="3">Penjualan Telur</th>
                            <th rowspan="2">Pengeluaran (Rp)</th> 
                            <th rowspan="2">Aksi</th>
                        </tr>
                        <tr>
                            <th>Masuk</th><th>Mati</th><th>Afkir</th><th class="table-info">Perubahan</th><th class="table-primary">Sisa Stok</th> <th>Harga/Kg</th><th>Terpakai (Kg)</th><th class="table-warning">Total Biaya</th> 
                            <th>Baik</th><th>Tipis</th><th>Pecah</th><th class="table-success">Total</th>
                            <th>Kg</th><th>Harga/Kg</th><th class="table-info">Total Rp</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($laporan_lengkap_final as $laporan): // Loop menggunakan array final ?>
                            <?php
                                $tanggal = $laporan['tanggal']; // Ambil dari data
                                $id_kandang_laporan = $laporan['id_kandang'];
                                $total_ayam_hari = ($laporan['ayam_masuk'] ?? 0) - ($laporan['ayam_mati'] ?? 0) - ($laporan['ayam_afkir'] ?? 0);
                                $harga_pakan = $harga_pakan_terkini[$id_kandang_laporan][$tanggal] ?? 0;
                                $biaya_pakan = ($laporan['pakan_terpakai_kg'] ?? 0) * $harga_pakan;
                                $produksi_total = ($laporan['telur_baik_kg'] ?? 0) + ($laporan['telur_tipis_kg'] ?? 0) + ($laporan['telur_pecah_kg'] ?? 0);
                                $pengeluaran = $pengeluaran_final[$tanggal][$id_kandang_laporan] ?? ['total' => 0, 'detail_array' => []];
                                $sisa_ayam_kumulatif = $laporan['sisa_ayam_kumulatif'] ?? 0; // Ambil data baru
                            ?>
                            <tr>
                                <td class="text-center"><?php echo date('d/m/Y', strtotime($tanggal)); ?></td>
                                <?php if ($current_user_role === 'Pimpinan' && $id_kandang_terpilih === 'semua') echo '<td>'.htmlspecialchars($laporan['nama_kandang']).'</td>'; ?>
                                
                                <td class="text-center"><?php echo number_format($laporan['ayam_masuk'] ?? 0); ?></td>
                                <td class="text-center text-danger"><?php echo number_format($laporan['ayam_mati'] ?? 0); ?></td>
                                <td class="text-center text-warning"><?php echo number_format($laporan['ayam_afkir'] ?? 0); ?></td>
                                <td class="text-center fw-bold table-info <?php echo ($total_ayam_hari >= 0) ? 'text-success' : 'text-danger'; ?>">
                                    <?php echo ($total_ayam_hari >= 0 ? '+' : '') . number_format($total_ayam_hari); ?>
                                </td> 
                                <td class="text-center fw-bold table-primary"><?php echo number_format($sisa_ayam_kumulatif); ?></td> <td class="text-end">Rp <?php echo number_format($harga_pakan); ?></td> 
                                <td class="text-end"><?php echo number_format($laporan['pakan_terpakai_kg'] ?? 0, 2, ',', '.'); ?></td>
                                <td class="text-end fw-bold table-warning">Rp <?php echo number_format($biaya_pakan); ?></td> 

                                <td class="text-end"><?php echo number_format($laporan['telur_baik_kg'] ?? 0, 2, ',', '.'); ?></td>
                                <td class="text-end"><?php echo number_format($laporan['telur_tipis_kg'] ?? 0, 2, ',', '.'); ?></td>
                                <td class="text-end"><?php echo number_format($laporan['telur_pecah_kg'] ?? 0, 2, ',', '.'); ?></td>
                                <td class="text-end fw-bold table-success"><?php echo number_format($produksi_total, 2, ',', '.'); ?></td>

                                <td class="text-end"><?php echo number_format($laporan['telur_terjual_kg'] ?? 0, 2, ',', '.'); ?></td>
                                <td class="text-end">Rp <?php echo number_format($laporan['harga_jual_rata2'] ?? 0); ?></td>
                                <td class="text-end fw-bold table-info">Rp <?php echo number_format($laporan['pemasukan_telur'] ?? 0); ?></td>

                                <td class="text-end">
                                    Rp <?php echo number_format($pengeluaran['total']); ?>
                                    <?php if ($pengeluaran['total'] > 0): ?>
                                    <button type="button" class="btn btn-xs btn-outline-info btn-detail-pengeluaran ms-1 py-0 px-1" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#detailPengeluaranModal"
                                            data-tanggal="<?php echo date('d M Y', strtotime($tanggal)); ?>"
                                            data-kandang="<?php echo htmlspecialchars($laporan['nama_kandang']); ?>"
                                            data-detail='<?php echo json_encode($pengeluaran['detail_array'], JSON_HEX_APOS | JSON_HEX_QUOT); ?>'
                                            data-bs-toggle="tooltip" data-bs-placement="top" title="Lihat Detail Pengeluaran"> 
                                        <i class="fas fa-eye fa-xs"></i>
                                    </button>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                     <div class="btn-group btn-group-sm" role="group">
                                        <button type="button" class="btn btn-warning btn-edit" data-bs-toggle="modal" data-bs-target="#editModal"
                                            data-id-laporan="<?php echo $laporan['id_laporan']; ?>"
                                            data-tanggal="<?php echo $tanggal; ?>"
                                            data-nama-kandang="<?php echo htmlspecialchars($laporan['nama_kandang']); ?>"
                                            data-ayam-masuk="<?php echo $laporan['ayam_masuk'] ?? 0; ?>"
                                            data-ayam-mati="<?php echo $laporan['ayam_mati'] ?? 0; ?>"
                                            data-ayam-afkir="<?php echo $laporan['ayam_afkir'] ?? 0; ?>"
                                            data-pakan-terpakai="<?php echo $laporan['pakan_terpakai_kg'] ?? 0; ?>"
                                            data-telur-baik="<?php echo $laporan['telur_baik_kg'] ?? 0; ?>"
                                            data-telur-tipis="<?php echo $laporan['telur_tipis_kg'] ?? 0; ?>"
                                            data-telur-pecah="<?php echo $laporan['telur_pecah_kg'] ?? 0; ?>"
                                            data-telur-terjual="<?php echo $laporan['telur_terjual_kg'] ?? 0; ?>"
                                            data-harga-jual="<?php echo $laporan['harga_jual_rata2'] ?? 0; ?>"
                                            data-bs-toggle="tooltip" title="Edit Laporan">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="hapus.php?id=<?php echo $laporan['id_laporan']; ?>" class="btn btn-sm btn-danger btn-hapus" data-bs-toggle="tooltip" title="Hapus Laporan">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                     </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php elseif ($id_kandang_terpilih): ?>
        <div class="alert alert-warning">Tidak ada riwayat laporan yang ditemukan untuk filter yang dipilih.</div>
    <?php else: ?>
         <?php if ($current_user_role === 'Pimpinan'): ?>
            <div class="alert alert-info">Silakan pilih kandang dan tentukan rentang tanggal untuk menampilkan riwayat laporan.</div>
        <?php else: ?>
             <div class="alert alert-info">Belum ada data laporan untuk kandang Anda. Silakan mulai input di menu "Input Harian".</div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<div class="modal fade" id="detailPengeluaranModal" ...>
    </div>

<div class="modal fade" id="editModal" ...>
     </div>

<?php include '../templates/footer.php'; ?>

<script>
$(document).ready(function() {
    // Gunakan variabel PHP untuk menentukan role di JS
    const IS_KARYAWAN = <?php echo ($current_user_role === 'Karyawan') ? 'true' : 'false'; ?>;

    // Inisialisasi DataTables (TANPA tombol ekspor client-side)
    var table = $('#tabelRiwayat').DataTable({ 
        "language": { "url": "https://cdn.datatables.net/plug-ins/1.13.7/i18n/id.json" },
        "scrollX": true, 
        "order": [[ 0, "desc" ]], // Urutkan tanggal terbaru di atas
        // Nonaktifkan sorting/paging/search untuk Karyawan agar seperti tabel statis
        "paging": !IS_KARYAWAN, 
        "searching": !IS_KARYAWAN,
        "info": !IS_KARYAWAN,
        "lengthChange": !IS_KARYAWAN
    });

    // Inisialisasi Tooltip & Re-inisialisasi saat draw
    var tooltipList = []; 
    function initTooltips() {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
         tooltipList.forEach(function(tooltip) { if (tooltip) tooltip.dispose(); });
        tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
          return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
    table.on('draw', initTooltips); 
    initTooltips(); // Panggil saat awal


    // Fungsi format angka ribuan (dengan clear on focus)
    function formatNumberWithDots(input) {
        let value = $(input).val().replace(/[^0-9]/g, '');
        if (value === '' && $(input).is(':focus')) { $(input).val(''); return; }
        if (value === '' || value === null) { $(input).val('0'); return; } 
        $(input).val(new Intl.NumberFormat('id-ID').format(value));
    }
     $('.format-number')
        .on('keyup input', function() { formatNumberWithDots(this); })
        .on('focus', function() { if ($(this).val() == '0') $(this).val(''); })
        .on('blur', function() { if ($(this).val() === '') $(this).val('0'); formatNumberWithDots(this); });


    // Logika untuk Modal Edit Laporan
    const editModal = document.getElementById('editModal');
    if (editModal) { 
        editModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            $('#edit_id_laporan').val(button.dataset.idLaporan);
            $('#edit_tanggal').val(button.dataset.tanggal);
            $('#edit_nama_kandang').val(button.dataset.namaKandang);
            
            $('#edit_ayam_masuk').val(button.dataset.ayamMasuk);
            $('#edit_ayam_mati').val(button.dataset.ayamMati);
            $('#edit_ayam_afkir').val(button.dataset.ayamAfkir);
            $('#edit_harga_jual').val(button.dataset.hargaJual);
            $('.format-number', editModal).trigger('blur'); // Format awal
            
            $('#edit_pakan_terpakai').val(parseFloat(button.dataset.pakanTerpakai).toFixed(2));
            $('#edit_telur_baik').val(parseFloat(button.dataset.telurBaik).toFixed(2));
            $('#edit_telur_tipis').val(parseFloat(button.dataset.telurTipis).toFixed(2));
            $('#edit_telur_pecah').val(parseFloat(button.dataset.telurPecah).toFixed(2));
            $('#edit_telur_terjual').val(parseFloat(button.dataset.telurTerjual).toFixed(2));

             $('#editModal form').removeClass('was-validated');
        });
    }

     // Logika untuk Modal Detail Pengeluaran
    const detailModal = document.getElementById('detailPengeluaranModal');
     if (detailModal) { 
        detailModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const tanggal = button.dataset.tanggal;
            const kandang = button.dataset.kandang;
            const detailJson = button.dataset.detail;
            
            let details = [];
            try {
                const decodedJson = $('<textarea/>').html(detailJson).text(); 
                details = JSON.parse(decodedJson);
            } catch (e) { console.error("Gagal parse JSON:", detailJson, e); details = []; }

            $('#detailTanggal').text(tanggal);
            $('#detailKandang').text(kandang);
            const rincianList = $('#detailRincian');
            rincianList.empty(); 

            if (details && details.length > 0) {
                details.forEach(item => {
                    rincianList.append(
                        `<li class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <strong>${item.kategori || 'Lain-lain'}:</strong> 
                                ${item.keterangan || 'Tanpa Keterangan'}
                            </div>
                            <span class="badge bg-primary rounded-pill ms-2">Rp ${new Intl.NumberFormat('id-ID').format(item.jumlah)}</span>
                        </li>`
                    );
                });
            } else {
                 rincianList.append('<li class="list-group-item text-muted">Tidak ada detail pengeluaran untuk hari ini.</li>');
            }
        });
     }


    // Logika Hapus dengan SweetAlert (delegasi event)
    $('#tabelRiwayat tbody').on('click', '.btn-hapus', function(e) { 
        e.preventDefault(); 
        const linkElement = this; 
        const href = $(linkElement).attr('href');
        
        // Buat URL redirect dengan parameter filter saat ini
        const urlParams = new URLSearchParams(window.location.search);
        urlParams.delete('status'); // Hapus status lama jika ada
        const redirectHref = href + '&' + urlParams.toString(); 

        Swal.fire({
            title: 'Anda yakin?',
            text: "Data laporan harian ini akan dihapus permanen!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) { window.location.href = redirectHref; }
        });
    });

    // Validasi Form Edit sebelum submit + hapus format
     $('#editModal form').on('submit', function(e) {
        $(this).find('.format-number').each(function() {
           let unformattedValue = $(this).val().replace(/\./g, '');
           $(this).val(unformattedValue); 
        });
        
        if (!this.checkValidity()) {
          e.preventDefault();
          e.stopPropagation();
           setTimeout(() => {
                $(this).find('.format-number').each(function() {
                     formatNumberWithDots(this); 
                });
           }, 100); 
        }
        $(this).addClass('was-validated'); 
    });

    // Validasi form filter
    const filterForm = document.querySelector('.card-body form.needs-validation'); 
    if (filterForm) {
        filterForm.addEventListener('submit', event => {
            if (!filterForm.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            filterForm.classList.add('was-validated');
        }, false);
    }
    
    // --- MODIFIKASI: OTOMATIS SUBMIT FORM FILTER UNTUK KARYAWAN ---
    <?php if ($current_user_role === 'Karyawan' && empty($_GET['id_kandang'])): ?>
        // Jika Karyawan & belum ada filter (halaman baru dimuat),
        // submit form filter secara otomatis untuk memuat data kandangnya.
        $('.card-body form.needs-validation').submit();
    <?php endif; ?>

});
</script>

</body>
</html>