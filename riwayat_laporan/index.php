<?php
include '../templates/header.php';
global $current_user_id, $current_user_role, $current_assigned_kandang_id;
global $folder_base;
$pesan = '';
if (isset($_GET['status'])) {
    if ($_GET['status'] == 'sukses_update') {
          $pesan = "<div class='alert alert-success mt-3'>Data laporan berhasil diperbarui!</div>";
    } elseif ($_GET['status'] == 'sukses_hapus') {
          $pesan = "<div class='alert alert-success mt-3'>Data laporan berhasil dihapus!</div>";
    } elseif ($_GET['status'] == 'req_sukses') { 
          $pesan = "<div class='alert alert-success mt-3'>Permintaan approval berhasil diajukan!</div>";
    } elseif ($_GET['status'] == 'req_gagal') { 
          $error_detail = $_SESSION['error_message_detail'] ?? 'Silakan coba lagi.';
          $pesan = "<div class='alert alert-danger mt-3'>Gagal mengajukan approval: " . htmlspecialchars($error_detail) . "</div>";
          unset($_SESSION['error_message_detail']);
    }
}
if (isset($_SESSION['success_message'])) {
    $pesan = "<div class='alert alert-success mt-3'>" . htmlspecialchars($_SESSION['success_message']) . "</div>";
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message']) && !isset($_GET['status'])) { // Hindari duplikat jika sudah ada status req_gagal
    $pesan = "<div class='alert alert-danger mt-3'>" . htmlspecialchars($_SESSION['error_message']) . "</div>";
    unset($_SESSION['error_message']);
}

$kandang_query = "SELECT id_kandang, nama_kandang FROM kandang";
if ($current_user_role === 'Karyawan' && $current_assigned_kandang_id) {
    $kandang_query .= " WHERE id_kandang = " . (int)$current_assigned_kandang_id;
}
$kandang_query .= " ORDER BY nama_kandang";
$kandang_list = $koneksi->query($kandang_query);

$id_kandang_terpilih = $_GET['id_kandang'] ?? '';
$tgl_awal = isset($_GET['tgl_awal']) ? $_GET['tgl_awal'] : date('Y-m-01');
$tgl_akhir = isset($_GET['tgl_akhir']) ? $_GET['tgl_akhir'] : date('Y-m-t');
if ($current_user_role === 'Karyawan') {
    $id_kandang_terpilih = $current_assigned_kandang_id;
}

$laporan_lengkap_final = [];
$pengeluaran_final = [];
$harga_pakan_terkini = [];
$nama_header = "Pilih Kandang";
$semua_kandang_data = [];
$ada_data = false;

if (!empty($id_kandang_terpilih)) {
    $id_kandang_int = null;
    $daftar_id_kandang_proses = [];
    if ($id_kandang_terpilih === 'semua' && $current_user_role === 'Pimpinan') {
        $nama_header = "Semua Kandang";
        $result_kandang = $koneksi->query("SELECT * FROM kandang ORDER BY nama_kandang");
        if($result_kandang) {
            while($row = $result_kandang->fetch_assoc()) {
                $semua_kandang_data[$row['id_kandang']] = $row;
                $daftar_id_kandang_proses[] = $row['id_kandang'];
            }
        }
    } elseif ($id_kandang_terpilih !== 'semua') {
        $id_kandang_int = (int)$id_kandang_terpilih;
        if ($current_user_role === 'Karyawan' && $id_kandang_int !== (int)$current_assigned_kandang_id) {
             $_SESSION['error_message'] = "Akses tidak sah ke kandang lain.";
             header('Location: ' . $folder_base . '/index.php'); exit();
        }
        $stmt_kandang = $koneksi->prepare("SELECT * FROM kandang WHERE id_kandang = ?");
        if($stmt_kandang){
            $stmt_kandang->bind_param("i", $id_kandang_int);
            $stmt_kandang->execute();
            $master_kandang = $stmt_kandang->get_result()->fetch_assoc();
            if ($master_kandang) {
                $nama_header = $master_kandang['nama_kandang'];
                $semua_kandang_data[$id_kandang_int] = $master_kandang;
                $daftar_id_kandang_proses[] = $id_kandang_int;
            } else {
                $nama_header = "Kandang Tidak Valid"; $id_kandang_terpilih = '';
            }
            $stmt_kandang->close();
        } else { error_log("Prepare failed (kandang): " . $koneksi->error); $nama_header = "Error"; $id_kandang_terpilih = ''; }
    }
    $ids_string_proses = !empty($daftar_id_kandang_proses) ? implode(',', array_map('intval', $daftar_id_kandang_proses)) : '0';
    $sisa_ayam_awal_per_kandang = [];
    if (!empty($ids_string_proses) && $ids_string_proses !== '0') {
        $query_stok_awal = "
            SELECT k.id_kandang, k.populasi_awal,
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
                $sisa_ayam_awal_per_kandang[$row['id_kandang']] = ($row['populasi_awal'] ?? 0) + $row['total_masuk_sebelum'] - $row['total_mati_sebelum'] - $row['total_afkir_sebelum'];
            }
            $stmt_stok_awal->close();
        } else { error_log("Prepare failed (stok awal): " . $koneksi->error); }
    }
    $stok_ayam_berjalan_per_kandang = $sisa_ayam_awal_per_kandang;
    if (!empty($ids_string_proses) && $ids_string_proses !== '0') {
        $query_laporan = "
            SELECT lh.*, k.nama_kandang,
                   lh.edit_requested_by, lh.edit_requested_at,
                   lh.edit_approved_by, lh.edit_approved_at
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
        } elseif ($id_kandang_terpilih === 'semua') { // Tambah filter IN jika 'semua'
             $query_laporan .= " AND lh.id_kandang IN ($ids_string_proses)";
        }
        $query_laporan .= " ORDER BY lh.tanggal ASC, k.nama_kandang ASC";

        $stmt_laporan = $koneksi->prepare($query_laporan);
        if ($stmt_laporan && count($params_laporan) >= 2) {
            $stmt_laporan->bind_param($types_laporan, ...$params_laporan);
            if($stmt_laporan->execute()){
                $result_laporan = $stmt_laporan->get_result();
                while ($row = $result_laporan->fetch_assoc()) {
                    $ada_data = true;
                    $id_k = $row['id_kandang'];
                    if (!isset($stok_ayam_berjalan_per_kandang[$id_k])) {
                        $stok_ayam_berjalan_per_kandang[$id_k] = $semua_kandang_data[$id_k]['populasi_awal'] ?? 0;
                    }
                    $ayam_masuk_hari_ini = $row['ayam_masuk'] ?? 0;
                    $ayam_mati_hari_ini = $row['ayam_mati'] ?? 0;
                    $ayam_afkir_hari_ini = $row['ayam_afkir'] ?? 0;
                    $sisa_ayam_hari_ini = $stok_ayam_berjalan_per_kandang[$id_k] + $ayam_masuk_hari_ini - $ayam_mati_hari_ini - $ayam_afkir_hari_ini;
                    $row['sisa_ayam_kumulatif'] = $sisa_ayam_hari_ini;
                    $stok_ayam_berjalan_per_kandang[$id_k] = $sisa_ayam_hari_ini;
                    $laporan_lengkap_final[] = $row;
                }
            } else { error_log("Execute failed (laporan): " . $stmt_laporan->error); }
            $stmt_laporan->close();
        } else { error_log("Prepare failed (laporan) or insufficient params: " . $koneksi->error); }
    }
    if($ada_data){
        if (!empty($ids_string_proses) && $ids_string_proses !== '0') {
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
            } else {
                 $query_pengeluaran .= " AND p.id_kandang IN ($ids_string_proses)";
            }
            $query_pengeluaran .= " ORDER BY p.tanggal_pengeluaran ASC, p.id_kandang ASC";
            $stmt_pengeluaran = $koneksi->prepare($query_pengeluaran);
             if ($stmt_pengeluaran && count($params_pengeluaran) >= 2) {
                 $stmt_pengeluaran->bind_param($types_pengeluaran, ...$params_pengeluaran);
                 if ($stmt_pengeluaran->execute()) {
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
                            'keterangan' => htmlspecialchars($row['keterangan'] ?? ''),
                            'jumlah' => $row['jumlah']
                        ];
                    }
                 } else { error_log("Execute failed (pengeluaran): " . $stmt_pengeluaran->error); }
                 $stmt_pengeluaran->close();
             } else { error_log("Prepare failed (pengeluaran) or insufficient params: " . $koneksi->error); }
        }
        if (!empty($ids_string_proses) && $ids_string_proses !== '0') {
            $query_harga_pakan = "
                SELECT id_kandang, tanggal_beli, harga_per_kg
                FROM stok_pakan
                WHERE tanggal_beli <= ? AND id_kandang IN ($ids_string_proses) ";
            $params_harga = [$tgl_akhir];
            $types_harga = "s";
            $query_harga_pakan .= " ORDER BY id_kandang ASC, tanggal_beli DESC, id_stok DESC";
            $stmt_harga = $koneksi->prepare($query_harga_pakan);
            if ($stmt_harga && count($params_harga) >= 1) {
                $stmt_harga->bind_param($types_harga, ...$params_harga);
                if($stmt_harga->execute()){
                    $result_harga = $stmt_harga->get_result();
                    $harga_pakan_per_kandang_tanggal = [];
                    while($row = $result_harga->fetch_assoc()){
                        if(!isset($harga_pakan_per_kandang_tanggal[$row['id_kandang']][$row['tanggal_beli']])){
                              $harga_pakan_per_kandang_tanggal[$row['id_kandang']][$row['tanggal_beli']] = $row['harga_per_kg'];
                        }
                    }
                    try {
                        $tanggal_iterator = new DatePeriod(new DateTime($tgl_awal), new DateInterval('P1D'), (new DateTime($tgl_akhir))->modify('+1 day'));
                        foreach ($daftar_id_kandang_proses as $id_k) {
                            $harga_terakhir_lookup = 0;
                            if (isset($harga_pakan_per_kandang_tanggal[$id_k])) {
                                $harga_kandang_ini = $harga_pakan_per_kandang_tanggal[$id_k];
                                ksort($harga_kandang_ini);
                                foreach ($harga_kandang_ini as $tgl_beli => $harga) {
                                    if ($tgl_beli < $tgl_awal) { $harga_terakhir_lookup = $harga; }
                                    else { break; }
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
                    } catch (Exception $e) { error_log("Error creating date period for harga pakan: " . $e->getMessage()); }
                } else { error_log("Execute failed (harga pakan): " . $stmt_harga->error); }
                $stmt_harga->close();
            } else { error_log("Prepare failed (harga pakan) or insufficient params: " . $koneksi->error); }
        }
        $laporan_lengkap_final = array_reverse($laporan_lengkap_final);
    } 
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
                                    <?php echo ($k['id_kandang'] == $id_kandang_terpilih) ? 'selected' : ''; ?>
                                >
                                    <?php echo htmlspecialchars($k['nama_kandang']); ?>
                                </option>
                            <?php endwhile; ?>
                         <?php elseif ($current_user_role === 'Pimpinan'): ?>
                              <option value="" disabled>Belum ada data kandang</option>
                        <?php elseif ($current_user_role === 'Karyawan' && !$current_assigned_kandang_id): ?>
                              <option value="" disabled>Anda belum ditugaskan kandang</option>
                        <?php endif; ?>
                    </select>
                     <div class="invalid-feedback">Silakan pilih kandang.</div>
                </div>
                <?php if ($current_user_role === 'Karyawan' && $current_assigned_kandang_id): ?>
                    <input type="hidden" name="id_kandang" value="<?php echo $current_assigned_kandang_id; ?>" />
                <?php endif; ?>
                <div class="col-md-3">
                    <label for="tgl_awal" class="form-label">Dari Tanggal</label>
                    <input type="date" class="form-control" id="tgl_awal" name="tgl_awal" value="<?php echo htmlspecialchars($tgl_awal); ?>" required>
                    <div class="invalid-feedback">Tanggal awal wajib diisi.</div>
                </div>
                <div class="col-md-3">
                    <label for="tgl_akhir" class="form-label">Sampai Tanggal</label>
                    <input type="date" class="form-control" id="tgl_akhir" name="tgl_akhir" value="<?php echo htmlspecialchars($tgl_akhir); ?>" required>
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
                    <a href="export_excel_server.php?<?php echo $export_params; ?>" class="btn btn-success" target="_blank" data-bs-toggle-tooltip="tooltip" title="Ekspor ke Excel">
                        <i class="fas fa-file-excel"></i> Excel
                    </a>
                    <a href="export_pdf_server.php?<?php echo $export_params; ?>" class="btn btn-danger" target="_blank" data-bs-toggle-tooltip="tooltip" title="Ekspor ke PDF">
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
                        <?php foreach ($laporan_lengkap_final as $laporan): ?>
                            <?php
                                $tanggal = $laporan['tanggal'];
                                $id_kandang_laporan = $laporan['id_kandang'];
                                $total_ayam_hari = ($laporan['ayam_masuk'] ?? 0) - ($laporan['ayam_mati'] ?? 0) - ($laporan['ayam_afkir'] ?? 0);
                                $harga_pakan = $harga_pakan_terkini[$id_kandang_laporan][$tanggal] ?? 0;
                                $biaya_pakan = ($laporan['pakan_terpakai_kg'] ?? 0) * $harga_pakan;
                                $produksi_total = ($laporan['telur_baik_kg'] ?? 0) + ($laporan['telur_tipis_kg'] ?? 0) + ($laporan['telur_pecah_kg'] ?? 0);
                                $pengeluaran = $pengeluaran_final[$tanggal][$id_kandang_laporan] ?? ['total' => 0, 'detail_array' => []];
                                $sisa_ayam_kumulatif = $laporan['sisa_ayam_kumulatif'] ?? 0;
                                $edit_requested = !empty($laporan['edit_requested_at']);
                                $edit_approved = !empty($laporan['edit_approved_at']);
                                $json_pengeluaran = json_encode($pengeluaran['detail_array'] ?? [], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
                                if (json_last_error() !== JSON_ERROR_NONE) {
                                    error_log("JSON Encode Error for Pengeluaran: " . json_last_error_msg()); $json_pengeluaran = '[]';
                                }
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
                                <td class="text-center fw-bold table-primary"><?php echo number_format($sisa_ayam_kumulatif); ?></td>
                                <td class="text-end">Rp <?php echo number_format($harga_pakan); ?></td>
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
                                            data-bs-toggle="modal" data-bs-target="#detailPengeluaranModal"
                                            data-tanggal="<?php echo date('d M Y', strtotime($tanggal)); ?>"
                                            data-kandang="<?php echo htmlspecialchars($laporan['nama_kandang']); ?>"
                                            data-detail='<?php echo $json_pengeluaran; ?>'
                                            data-bs-toggle-tooltip="tooltip" data-bs-placement="top" title="Lihat Detail Pengeluaran">
                                        <i class="fas fa-eye fa-xs"></i>
                                    </button>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                     <div class="btn-group btn-group-sm" role="group">
                                        <button type="button" class="btn btn-warning btn-edit"
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
                                            data-edit-approved="<?php echo $edit_approved ? '1' : '0'; ?>"
                                            data-edit-requested="<?php echo $edit_requested ? '1' : '0'; ?>"
                                            data-bs-toggle-tooltip="tooltip"
                                            title="<?php echo ($current_user_role === 'Karyawan' && !$edit_approved) ? ($edit_requested ? 'Menunggu Approval' : 'Perlu Approval') : 'Edit Laporan'; ?>">
                                            <i class="fas fa-edit"></i>
                                            <?php
                                                // --- MODIFIKASI: Hapus cek tanggal == today ---
                                                if ($current_user_role === 'Karyawan') {
                                                    if (!$edit_approved && $edit_requested) {
                                                        echo ' <i class="fas fa-hourglass-half fa-xs text-muted"></i>';
                                                    } elseif (!$edit_approved && !$edit_requested) {
                                                        echo ' <i class="fas fa-lock fa-xs text-danger"></i>';
                                                    }
                                                }
                                                // --- AKHIR MODIFIKASI ---
                                            ?>
                                        </button>
                                         <a href="hapus.php?id=<?php echo $laporan['id_laporan']; ?>" class="btn btn-sm btn-danger btn-hapus" data-bs-toggle-tooltip="tooltip" title="Hapus Laporan">
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
              <div class="alert alert-info">Silakan mulai dengan memilih kandang dan rentang tanggal, atau input data harian jika belum ada.</div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<div class="modal fade" id="detailPengeluaranModal" tabindex="-1" aria-labelledby="detailPengeluaranModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="detailPengeluaranModalLabel">Detail Pengeluaran</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <p><strong>Tanggal:</strong> <span id="detailTanggal"></span></p>
                <p><strong>Kandang:</strong> <span id="detailKandang"></span></p>
                <h6>Rincian:</h6>
                <ul class="list-group" id="detailRincian"></ul>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button></div>
        </div>
    </div>
</div>

<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title" id="editModalLabel">Edit Laporan Harian</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <form action="proses_update.php" method="POST" class="needs-validation" novalidate>
         <div class="modal-body">
           <input type="hidden" id="edit_id_laporan" name="id_laporan">
           <input type="hidden" id="edit_current_page" name="current_page">
           <input type="hidden" name="id_kandang_current" value="<?php echo htmlspecialchars($id_kandang_terpilih); ?>">
           <input type="hidden" name="tgl_awal_current" value="<?php echo htmlspecialchars($tgl_awal); ?>">
           <input type="hidden" name="tgl_akhir_current" value="<?php echo htmlspecialchars($tgl_akhir); ?>">
           <div class="row mb-3">
               <div class="col-md-6"><label for="edit_nama_kandang" class="form-label">Kandang</label><input type="text" class="form-control" id="edit_nama_kandang" readonly disabled></div>
               <div class="col-md-6"><label for="edit_tanggal" class="form-label">Tanggal</label><input type="date" class="form-control" id="edit_tanggal" name="tanggal" readonly disabled></div>
           </div>
           <hr><h6><i class="fas fa-drumstick-bite me-2"></i> Data Ayam</h6>
           <div class="row mb-3">
               <div class="col-md-4"><label for="edit_ayam_masuk" class="form-label">Ayam Masuk (Ekor)</label><input type="text" class="form-control format-number" id="edit_ayam_masuk" name="ayam_masuk" required><div class="invalid-feedback">Jumlah wajib diisi.</div></div>
               <div class="col-md-4"><label for="edit_ayam_mati" class="form-label">Ayam Mati (Ekor)</label><input type="text" class="form-control format-number" id="edit_ayam_mati" name="ayam_mati" required><div class="invalid-feedback">Jumlah wajib diisi.</div></div>
               <div class="col-md-4"><label for="edit_ayam_afkir" class="form-label">Ayam Afkir (Ekor)</label><input type="text" class="form-control format-number" id="edit_ayam_afkir" name="ayam_afkir" required><div class="invalid-feedback">Jumlah wajib diisi.</div></div>
           </div>
            <hr><h6><i class="fas fa-wheat-awn me-2"></i> Data Pakan</h6>
            <div class="row mb-3"><div class="col-md-6"><label for="edit_pakan_terpakai" class="form-label">Pakan Terpakai (Kg)</label><input type="number" step="0.01" class="form-control" id="edit_pakan_terpakai" name="pakan_terpakai_kg" required><div class="invalid-feedback">Jumlah pakan wajib diisi (bisa 0).</div></div></div>
            <hr><h6><i class="fas fa-egg me-2"></i> Data Produksi & Penjualan Telur</h6>
           <div class="row mb-3">
               <div class="col-md-4"><label for="edit_telur_baik" class="form-label">Telur Baik (Kg)</label><input type="number" step="0.01" class="form-control" id="edit_telur_baik" name="telur_baik_kg" required><div class="invalid-feedback">Jumlah wajib diisi (bisa 0).</div></div>
               <div class="col-md-4"><label for="edit_telur_tipis" class="form-label">Telur Tipis (Kg)</label><input type="number" step="0.01" class="form-control" id="edit_telur_tipis" name="telur_tipis_kg" required><div class="invalid-feedback">Jumlah wajib diisi (bisa 0).</div></div>
               <div class="col-md-4"><label for="edit_telur_pecah" class="form-label">Telur Pecah (Kg)</label><input type="number" step="0.01" class="form-control" id="edit_telur_pecah" name="telur_pecah_kg" required><div class="invalid-feedback">Jumlah wajib diisi (bisa 0).</div></div>
           </div>
            <div class="row mb-3">
                <div class="col-md-6"><label for="edit_telur_terjual" class="form-label">Telur Terjual (Kg)</label><input type="number" step="0.01" class="form-control" id="edit_telur_terjual" name="telur_terjual_kg" required><div class="invalid-feedback">Jumlah wajib diisi (bisa 0).</div></div>
                <div class="col-md-6"><label for="edit_harga_jual" class="form-label">Harga Jual Rata-rata (Rp/Kg)</label><input type="text" class="form-control format-number" id="edit_harga_jual" name="harga_jual_rata2" required><div class="invalid-feedback">Harga wajib diisi (bisa 0).</div></div>
           </div>
         </div>
         <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary">Simpan Perubahan</button></div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="requestApprovalModal" tabindex="-1" aria-labelledby="requestApprovalModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title" id="requestApprovalModalLabel"><i class="fas fa-lock text-warning me-2"></i> Perlu Approval</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <div class="modal-body">
        <p>Anda perlu approval Pimpinan untuk mengedit data laporan pada tanggal <strong id="requestTanggal"></strong> untuk kandang <strong id="requestKandang"></strong>.</p>
        <p>Apakah Anda ingin mengajukan permintaan approval sekarang?</p>
        <span id="requestInfo" class="text-muted small" style="display: none;">Permintaan approval sudah diajukan sebelumnya.</span>
        <input type="hidden" id="request_id_laporan">
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button><button type="button" class="btn btn-primary" id="btnAjukanApproval"><span class="spinner-border spinner-border-sm" role="status" aria-hidden="true" style="display: none;"></span> Ajukan Approval</button></div>
    </div>
  </div>
</div>


<?php include '../templates/footer.php'; ?>

<script>
$(document).ready(function() {
    const USER_ROLE = "<?php echo $current_user_role; ?>";
    const FOLDER_BASE = "<?php echo $folder_base; ?>";
    "<?php echo date('Y-m-d'); ?>";

    const requestApprovalModalElement = document.getElementById('requestApprovalModal');
    const editModalElement = document.getElementById('editModal');
    const detailModalElement = document.getElementById('detailPengeluaranModal');

    var table = $('#tabelRiwayat').DataTable({
        "language": { "url": "https://cdn.datatables.net/plug-ins/1.13.7/i18n/id.json" },
        "scrollX": true,
        "order": [[ 0, "desc" ]],
        "paging": !(USER_ROLE === 'Karyawan'),
        "searching": !(USER_ROLE === 'Karyawan'),
        "info": !(USER_ROLE === 'Karyawan'),
        "lengthChange": !(USER_ROLE === 'Karyawan')
    });

    // --- Atur halaman setelah load ---
    const urlParams = new URLSearchParams(window.location.search);
    const pageParam = urlParams.get('page');
    if (pageParam !== null && !isNaN(parseInt(pageParam))) {
        const pageNum = parseInt(pageParam);
        table.one('preDraw.dt', function() {
            const pageInfo = table.page.info();
            if (pageNum >= 0 && pageNum < pageInfo.pages) {
                table.page(pageNum).draw(false);
            }
        });
    }

    // --- Init Tooltips ---
    var tooltipList = [];
    function initTooltips() {
        tooltipList.forEach(function(tooltip) { if (tooltip) tooltip.dispose(); });
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle-tooltip="tooltip"]'));
        tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
          return tooltipTriggerEl ? new bootstrap.Tooltip(tooltipTriggerEl) : null;
        });
    }
    table.on('draw', initTooltips);
    initTooltips();
    function formatNumberWithDots(input) {
        let value = $(input).val().replace(/[^0-9]/g, '');
        if (value === '' && $(input).is(':focus')) { return; }
        if (value === '' || value === null) { $(input).val('0'); return; }
        $(input).val(new Intl.NumberFormat('id-ID').format(value));
    }
    $(document).on('keyup input', '#editModal .format-number', function() { formatNumberWithDots(this); });
    $(document).on('focus', '#editModal .format-number', function() { if ($(this).val() == '0') $(this).val(''); });
    $(document).on('blur', '#editModal .format-number', function() { if ($(this).val() === '') $(this).val('0'); formatNumberWithDots(this); });
    $('#tabelRiwayat tbody').on('click', '.btn-edit', function(event) {
        const button = this;
        const dataset = button.dataset;
        const idLaporan = dataset.idLaporan;
        const tanggalLaporan = dataset.tanggal;
        const isApproved = (dataset.editApproved === '1');
        const isRequested = (dataset.editRequested === '1');

        console.log('--- Edit button clicked ---', {idLaporan, tanggalLaporan, isApproved, isRequested, USER_ROLE});
        if (USER_ROLE === 'Karyawan' && !isApproved) {
            console.log('-> KONDISI TERPENUHI: Menampilkan modal request approval.');
            event.preventDefault(); event.stopPropagation();

            if (!requestApprovalModalElement) { console.error('#requestApprovalModal not found!'); return; }
            const requestApprovalModal = bootstrap.Modal.getOrCreateInstance(requestApprovalModalElement);

            $('#requestTanggal').text(new Date(tanggalLaporan).toLocaleDateString('id-ID', { day:'numeric', month:'long', year:'numeric'}));
            $('#requestKandang').text(dataset.namaKandang);
            $('#request_id_laporan').val(idLaporan);

            if (isRequested) {
                $('#btnAjukanApproval').prop('disabled', true).hide();
                $('#requestInfo').show().text('Permintaan approval sudah diajukan, menunggu Pimpinan.');
            } else {
                $('#btnAjukanApproval').prop('disabled', false).show();
                $('#requestInfo').hide().text('');
            }
            $('#btnAjukanApproval .spinner-border').hide();
            requestApprovalModal.show();
        } else { // Jika Pimpinan, atau Karyawan tapi SUDAH diapprove
            console.log('-> KONDISI TIDAK TERPENUHI: Menyiapkan dan menampilkan modal edit.');
            event.preventDefault();

            if (!editModalElement) { console.error('#editModal not found!'); return; }
            const editModal = bootstrap.Modal.getOrCreateInstance(editModalElement);

            try {
                console.log('Mengisi form modal edit...');
                $('#edit_id_laporan').val(dataset.idLaporan);
                $('#edit_tanggal').val(dataset.tanggal);
                $('#edit_nama_kandang').val(dataset.namaKandang);
                $('#edit_ayam_masuk').val(dataset.ayamMasuk);
                $('#edit_ayam_mati').val(dataset.ayamMati);
                $('#edit_ayam_afkir').val(dataset.ayamAfkir);
                $('#edit_harga_jual').val(dataset.hargaJual);
                $('#editModal .format-number').each(function() { formatNumberWithDots(this); }); // Format angka setelah isi
                $('#edit_pakan_terpakai').val(parseFloat(dataset.pakanTerpakai).toFixed(2));
                $('#edit_telur_baik').val(parseFloat(dataset.telurBaik).toFixed(2));
                $('#edit_telur_tipis').val(parseFloat(dataset.telurTipis).toFixed(2));
                $('#edit_telur_pecah').val(parseFloat(dataset.telurPecah).toFixed(2));
                $('#edit_telur_terjual').val(parseFloat(dataset.telurTerjual).toFixed(2));
                const pageInfo = table.page.info();
                $('#edit_current_page').val(pageInfo.page);
                $('#editModal form').removeClass('was-validated');
                console.log('Form modal edit berhasil diisi.');

                editModal.show();
                 console.log('editModal.show() called.');

            } catch (error) {
                console.error('Error saat mengisi form modal edit:', error);
                Swal.fire({ icon: 'error', title: 'Oops...', text: 'Terjadi kesalahan saat menyiapkan form edit.' });
            }
        }
    });
      if (detailModalElement) {
         detailModalElement.addEventListener('show.bs.modal', function(event) {
             console.log('--- show.bs.modal event triggered for #detailPengeluaranModal ---');
             const button = event.relatedTarget;
             if (!button || !button.classList.contains('btn-detail-pengeluaran')) {
                 console.warn('Detail modal triggered by unexpected element:', button); return;
             }
             const dataset = button.dataset;
             const tanggal = dataset.tanggal; const kandang = dataset.kandang; const detailJson = dataset.detail;
             console.log('Detail data:', {tanggal, kandang, detailJson});

             $('#detailTanggal').text(tanggal || 'N/A');
             $('#detailKandang').text(kandang || 'N/A');
             const rincianList = $('#detailRincian'); rincianList.empty();

             let details = [];
             if (detailJson) {
                 try {
                     const decodedJson = $('<textarea/>').html(detailJson).text();
                     console.log('Decoded JSON:', decodedJson);
                     details = JSON.parse(decodedJson);
                 } catch (e) {
                     console.error("Gagal parse JSON detail pengeluaran:", e, "Original JSON:", detailJson);
                     rincianList.append('<li class="list-group-item text-danger">Gagal memuat detail (Format Data Error).</li>');
                     return;
                 }
             }

             if (details && details.length > 0) {
                 details.forEach(item => {
                     const kategori = item.kategori || 'Lain-lain';
                     const keterangan = item.keterangan || 'Tanpa Keterangan';
                     const jumlah = new Intl.NumberFormat('id-ID').format(item.jumlah || 0);
                     rincianList.append(
                         `<li class="list-group-item d-flex justify-content-between align-items-center">
                             <div><strong>${kategori}:</strong> ${keterangan}</div>
                             <span class="badge bg-primary rounded-pill ms-2">Rp ${jumlah}</span>
                         </li>`
                     );
                 });
                 console.log('Detail pengeluaran berhasil ditampilkan.');
             } else {
                  rincianList.append('<li class="list-group-item text-muted">Tidak ada rincian pengeluaran untuk ditampilkan.</li>');
                  console.log('Tidak ada detail pengeluaran.');
             }
         });
      } else { console.error('Elemen modal #detailPengeluaranModal tidak ditemukan!'); }
    $('#btnAjukanApproval').on('click', function() {
        console.log('Tombol #btnAjukanApproval diklik.');
        const btn = $(this);
        const idLaporan = $('#request_id_laporan').val();
        const spinner = btn.find('.spinner-border');
        if (!idLaporan) {
             console.error('ID Laporan tidak ditemukan di modal request.');
             Swal.fire({ icon: 'error', title: 'Error', text: 'ID Laporan tidak valid.' }); return;
        }
        btn.prop('disabled', true); spinner.show();
        console.log('Mengirim AJAX request approval untuk ID:', idLaporan);

        $.ajax({
            url: FOLDER_BASE + '/riwayat_laporan/ajax_request_approval.php',
            method: 'POST', data: { id_laporan: idLaporan }, dataType: 'json',
            success: function(response) {
                console.log('AJAX Success:', response);
                if (response.success) {
                    const requestModal = bootstrap.Modal.getInstance(requestApprovalModalElement);
                    if (requestModal) requestModal.hide();
                    Swal.fire({ icon: 'success', title: 'Berhasil', text: response.message || 'Permintaan approval berhasil diajukan.', timer: 2500, showConfirmButton: false })
                    .then(() => { location.reload(); });
                } else {
                    Swal.fire({ icon: 'error', title: 'Gagal', text: response.message || 'Terjadi kesalahan.' });
                    btn.prop('disabled', false);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                 console.error("AJAX Error:", textStatus, errorThrown, jqXHR.responseText);
                 Swal.fire({ icon: 'error', title: 'Error Koneksi', text: 'Tidak dapat menghubungi server.' });
                 btn.prop('disabled', false);
            },
            complete: function() { spinner.hide(); }
        });
    });
    $('#tabelRiwayat tbody').on('click', '.btn-hapus', function(e) {
        e.preventDefault();
        const linkElement = this;
        const href = $(linkElement).attr('href');
        const currentUrlParams = new URLSearchParams(window.location.search);
        currentUrlParams.delete('status');
        const redirectHref = href + '&' + currentUrlParams.toString();
        Swal.fire({
            title: 'Anda yakin?', text: "Data laporan harian ini akan dihapus permanen!", icon: 'warning',
            showCancelButton: true, confirmButtonColor: '#d33', cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, hapus!', cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) { window.location.href = redirectHref; }
        });
    });

    // --- Validasi Form Edit ---
    $('#editModal form').on('submit', function(e) { /* ... kode validasi form edit ... */
         $(this).find('.format-number').each(function() {
             let unformattedValue = $(this).val().replace(/\./g, ''); // Hapus titik ribuan saja
             $(this).val(unformattedValue);
         });
         if (!this.checkValidity()) {
           e.preventDefault(); e.stopPropagation();
            setTimeout(() => { $(this).find('.format-number').each(function() { formatNumberWithDots(this); }); }, 100);
         }
         $(this).addClass('was-validated');
    });

    // --- Validasi Form Filter ---
    const filterForm = document.querySelector('.card-body form.needs-validation');
    if (filterForm) { /* ... kode validasi form filter ... */
        filterForm.addEventListener('submit', event => {
            if (!filterForm.checkValidity()) { event.preventDefault(); event.stopPropagation(); }
            filterForm.classList.add('was-validated');
        }, false);
     }

    // --- Auto submit filter Karyawan ---
    <?php if ($current_user_role === 'Karyawan' && empty($_GET['id_kandang']) && $current_assigned_kandang_id): ?>
        $('.card-body form.needs-validation').submit();
    <?php endif; ?>

});
</script>

</body>
</html>