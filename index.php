<?php
include 'templates/header.php'; // Header sudah include koneksi & variabel role
// Ambil variabel global dari header.php
global $current_user_role, $current_assigned_kandang_id;

// --- 1. PENGATURAN TANGGAL & FILTER KANDANG ---
$today = date('Y-m-d');
$current_month_start = date('Y-m-01');
$current_month_end = date('Y-m-t');
$last_month_start = date('Y-m-01', strtotime('first day of last month'));
$last_month_end = date('Y-m-t', strtotime('last day of last month'));
$yesterday = date('Y-m-d', strtotime('-1 day')); // Untuk FCR dan Ringkasan Kemarin
$yesterday_formatted = date('d F Y', strtotime('-1 day')); // Format tanggal kemarin untuk ditampilkan

$kandang_aktif_list = []; // Menyimpan data master kandang yg relevan
$ids_to_query = []; // ID kandang yang akan di-query
$nama_header = "Peternakan"; // Default

// Tentukan kandang mana yang akan diambil datanya berdasarkan role
if ($current_user_role === 'Pimpinan') {
    $nama_header = "Global (Semua Kandang Aktif)";
    $result_kandang = $koneksi->query("SELECT id_kandang, nama_kandang, populasi_awal, tgl_masuk_awal, umur_ayam_awal FROM kandang WHERE status = 'Aktif' ORDER BY nama_kandang");
    if ($result_kandang) {
        while ($row = $result_kandang->fetch_assoc()) {
            $kandang_aktif_list[$row['id_kandang']] = $row;
            $ids_to_query[] = $row['id_kandang'];
        }
    }
} elseif ($current_assigned_kandang_id !== null) {
    // Karyawan: Ambil hanya kandang yang ditugaskan
    $stmt_kandang = $koneksi->prepare("SELECT id_kandang, nama_kandang, populasi_awal, tgl_masuk_awal, umur_ayam_awal FROM kandang WHERE id_kandang = ? AND status = 'Aktif'");
    if($stmt_kandang){
        $stmt_kandang->bind_param("i", $current_assigned_kandang_id);
        $stmt_kandang->execute();
        $result_kandang = $stmt_kandang->get_result();
        if ($row = $result_kandang->fetch_assoc()) {
             $kandang_aktif_list[$row['id_kandang']] = $row;
             $ids_to_query[] = $row['id_kandang'];
             $nama_header = "Kandang " . htmlspecialchars($row['nama_kandang']); // Nama kandang karyawan
        }
        $stmt_kandang->close();
    }
} else {
    // Karyawan tapi belum ada assignment ID (seharusnya sudah dihandle header, tapi antisipasi)
    $ids_to_query = [];
}

$jumlah_kandang_tampil = count($ids_to_query);
$ids_string = !empty($ids_to_query) ? implode(',', array_map('intval', $ids_to_query)) : '0'; // Pastikan integer
$where_clause_kandang = "WHERE id_kandang IN ($ids_string)";


// --- 2. PERSIAPAN VARIABEL ---
$total_populasi = 0;
$total_sisa_pakan = 0;
$bulan_ini_produksi = 0;
$bulan_ini_mati_afkir = 0;
$bulan_ini_pakan = 0;
$bulan_lalu_produksi = 0;
$bulan_lalu_mati_afkir = 0;
$bulan_lalu_pakan = 0;
$total_produksi_today = 0;
$total_kematian_today = 0;
$total_pakan_pakai_kemarin = 0;
$total_telur_prod_kemarin = 0;
$total_fcr_kemarin = '0.00'; // Default FCR

$data_per_kandang = []; // Menyimpan semua data teragregasi per kandang
$chart_labels = [];
$chart_data_produksi = [];

// --- 3. LAKUKAN QUERY HANYA JIKA ADA KANDANG RELEVAN ---
if ($jumlah_kandang_tampil > 0) {

    // --- A. Agregat Populasi & Sisa Pakan (Saat Ini) ---
     $populasi_per_kandang = [];
     $beli_pakan_per_kandang = [];
     $pakai_pakan_per_kandang = [];

     // Populasi
     $result_populasi_agg = $koneksi->query("
        SELECT id_kandang, COALESCE(SUM(ayam_masuk), 0) as total_masuk, COALESCE(SUM(ayam_mati), 0) as total_mati, COALESCE(SUM(ayam_afkir), 0) as total_afkir
        FROM laporan_harian $where_clause_kandang GROUP BY id_kandang
     ");
     $agregat_populasi = [];
     if($result_populasi_agg){ while($row = $result_populasi_agg->fetch_assoc()){ $agregat_populasi[$row['id_kandang']] = $row; } }

     // Stok Pakan
     $result_beli = $koneksi->query("SELECT id_kandang, COALESCE(SUM(jumlah_kg), 0) as total_beli FROM stok_pakan $where_clause_kandang GROUP BY id_kandang");
     if($result_beli) { while($row = $result_beli->fetch_assoc()) { $beli_pakan_per_kandang[$row['id_kandang']] = $row['total_beli']; } }
     $result_pakai = $koneksi->query("SELECT id_kandang, COALESCE(SUM(pakan_terpakai_kg), 0) as total_pakai FROM laporan_harian $where_clause_kandang GROUP BY id_kandang");
     if($result_pakai) { while($row = $result_pakai->fetch_assoc()) { $pakai_pakan_per_kandang[$row['id_kandang']] = $row['total_pakai']; } }

     // Inisialisasi $data_per_kandang dengan data master & data agregat "saat ini"
     foreach ($kandang_aktif_list as $id_k => $kandang_data) {
         $populasi_saat_ini = ($kandang_data['populasi_awal'] ?? 0) +
                            ($agregat_populasi[$id_k]['total_masuk'] ?? 0) -
                            ($agregat_populasi[$id_k]['total_mati'] ?? 0) -
                            ($agregat_populasi[$id_k]['total_afkir'] ?? 0);
         $sisa_pakan_kandang = ($beli_pakan_per_kandang[$id_k] ?? 0) - ($pakai_pakan_per_kandang[$id_k] ?? 0);

         // Hitung Umur
         $umur_minggu = 'N/A';
         if (!empty($kandang_data['tgl_masuk_awal']) && !empty($kandang_data['umur_ayam_awal'])) {
             try {
                 $tgl_masuk = new DateTime($kandang_data['tgl_masuk_awal']);
                 $today_dt = new DateTime();
                 // Pastikan tanggal masuk tidak di masa depan
                 if ($tgl_masuk <= $today_dt) {
                     $selisih_hari = $today_dt->diff($tgl_masuk)->days;
                     $umur_minggu = round(($kandang_data['umur_ayam_awal'] + $selisih_hari) / 7, 1);
                 }
             } catch (Exception $e) {
                 // Abaikan error tanggal jika format tidak valid
                 error_log("Error parsing date for kandang ID $id_k: " . $e->getMessage());
             }
         }

         $total_populasi += $populasi_saat_ini;
         $total_sisa_pakan += $sisa_pakan_kandang;

         $data_per_kandang[$id_k] = [
            'nama_kandang' => $kandang_data['nama_kandang'],
            'populasi' => $populasi_saat_ini, 'umur' => $umur_minggu, 'sisa_pakan' => $sisa_pakan_kandang,
            'produksi_kemarin' => 0, 'kematian_kemarin' => 0, 'pakan_pakai_kemarin' => 0, 'fcr_kemarin' => 'N/A',
            'prod_bulan_ini' => 0, 'mati_bulan_ini' => 0,
            'prod_bulan_lalu' => 0, 'mati_bulan_lalu' => 0
         ];
     }


    // --- B. Data Harian (Hari Ini & Kemarin) ---
    $result_harian = $koneksi->query("
        SELECT
            tanggal, id_kandang,
            COALESCE(SUM(telur_baik_kg + telur_tipis_kg + telur_pecah_kg), 0) as total_produksi,
            COALESCE(SUM(ayam_mati), 0) as total_mati,
            COALESCE(SUM(pakan_terpakai_kg), 0) as total_pakan
        FROM laporan_harian
        WHERE tanggal IN ('$today', '$yesterday') AND id_kandang IN ($ids_string)
        GROUP BY tanggal, id_kandang
    ");

    if($result_harian){
        while($row = $result_harian->fetch_assoc()){
            $id_k = $row['id_kandang'];
            if(isset($data_per_kandang[$id_k])){
                if($row['tanggal'] == $today){
                    $total_produksi_today += $row['total_produksi'];
                    $total_kematian_today += $row['total_mati'];
                } elseif ($row['tanggal'] == $yesterday) {
                    $data_per_kandang[$id_k]['produksi_kemarin'] = $row['total_produksi'];
                    $data_per_kandang[$id_k]['kematian_kemarin'] = $row['total_mati'];
                    $data_per_kandang[$id_k]['pakan_pakai_kemarin'] = $row['total_pakan'];
                    $total_pakan_pakai_kemarin += $row['total_pakan']; // Akumulasi global
                    $total_telur_prod_kemarin += $row['total_produksi']; // Akumulasi global

                     // Hitung FCR Per Kandang
                     if ($row['total_produksi'] > 0) { $data_per_kandang[$id_k]['fcr_kemarin'] = number_format($row['total_pakan'] / $row['total_produksi'], 2); }
                     elseif ($row['total_pakan'] > 0) { $data_per_kandang[$id_k]['fcr_kemarin'] = '∞'; } // Tak terhingga jika ada pakan tapi 0 telur
                     else { $data_per_kandang[$id_k]['fcr_kemarin'] = '0.00'; } // 0 jika tidak ada pakan & telur
                }
            }
        }
    }
    // Hitung FCR Total Kemarin
    if ($total_telur_prod_kemarin > 0) { $total_fcr_kemarin = number_format($total_pakan_pakai_kemarin / $total_telur_prod_kemarin, 2); }
    elseif ($total_pakan_pakai_kemarin > 0) { $total_fcr_kemarin = '∞'; }


    // --- C. Data Agregat Bulanan (Global & Per Kandang) ---
    $query_bulanan = "
        SELECT
            id_kandang,
            SUM(CASE WHEN tanggal BETWEEN '$current_month_start' AND '$current_month_end' THEN (telur_baik_kg + telur_tipis_kg + telur_pecah_kg) ELSE 0 END) as prod_bi,
            SUM(CASE WHEN tanggal BETWEEN '$current_month_start' AND '$current_month_end' THEN (ayam_mati + ayam_afkir) ELSE 0 END) as mati_bi,
            SUM(CASE WHEN tanggal BETWEEN '$current_month_start' AND '$current_month_end' THEN pakan_terpakai_kg ELSE 0 END) as pakan_bi,
            SUM(CASE WHEN tanggal BETWEEN '$last_month_start' AND '$last_month_end' THEN (telur_baik_kg + telur_tipis_kg + telur_pecah_kg) ELSE 0 END) as prod_bl,
            SUM(CASE WHEN tanggal BETWEEN '$last_month_start' AND '$last_month_end' THEN (ayam_mati + ayam_afkir) ELSE 0 END) as mati_bl,
            SUM(CASE WHEN tanggal BETWEEN '$last_month_start' AND '$last_month_end' THEN pakan_terpakai_kg ELSE 0 END) as pakan_bl
        FROM laporan_harian
        $where_clause_kandang AND (tanggal BETWEEN '$last_month_start' AND '$current_month_end')
        GROUP BY id_kandang
    ";

    $result_bulanan = $koneksi->query($query_bulanan);
    if($result_bulanan){
        while($row = $result_bulanan->fetch_assoc()){
            $id_k = $row['id_kandang'];
            if(isset($data_per_kandang[$id_k])){
                // Isi data per kandang
                $data_per_kandang[$id_k]['prod_bulan_ini'] = $row['prod_bi'];
                $data_per_kandang[$id_k]['mati_bulan_ini'] = $row['mati_bi'];
                $data_per_kandang[$id_k]['prod_bulan_lalu'] = $row['prod_bl'];
                $data_per_kandang[$id_k]['mati_bulan_lalu'] = $row['mati_bl'];

                // Akumulasi ke global
                $bulan_ini_produksi += $row['prod_bi'];
                $bulan_ini_mati_afkir += $row['mati_bi'];
                $bulan_ini_pakan += $row['pakan_bi'];
                $bulan_lalu_produksi += $row['prod_bl'];
                $bulan_lalu_mati_afkir += $row['mati_bl'];
                $bulan_lalu_pakan += $row['pakan_bl'];
            }
        }
    }

    // --- D. Data Grafik (Bulan Ini) ---
    $result_grafik = $koneksi->query("
        SELECT
            tanggal, SUM(telur_baik_kg + telur_tipis_kg + telur_pecah_kg) as total_produksi_harian
        FROM laporan_harian
        $where_clause_kandang AND tanggal BETWEEN '$current_month_start' AND '$current_month_end'
        GROUP BY tanggal ORDER BY tanggal ASC
    ");
    $produksi_bulanan = [];
    if($result_grafik){ while($row = $result_grafik->fetch_assoc()){ $produksi_bulanan[$row['tanggal']] = $row['total_produksi_harian']; } }

    try {
        $start = new DateTime($current_month_start);
        $end = new DateTime($current_month_end);
        $interval = new DateInterval('P1D');
        $period = new DatePeriod($start, $interval, $end->modify('+1 day'));

        foreach ($period as $date) {
            $tanggal_str = $date->format('Y-m-d');
            $hari = $date->format('d');
            $chart_labels[] = $hari;
            $chart_data_produksi[] = $produksi_bulanan[$tanggal_str] ?? 0;
        }
    } catch (Exception $e) { /* Abaikan error jika tanggal tidak valid */ error_log("Error creating date period for chart: " . $e->getMessage()); }

} // --- Akhir Query Block ---

// Konversi data chart ke JSON
$chart_labels_json = json_encode($chart_labels);
$chart_data_produksi_json = json_encode($chart_data_produksi);
$nama_bulan_ini = date('F Y');
$nama_bulan_lalu = date('F Y', strtotime('last month'));

?>

<div class="container-fluid">
    <div class="page-header">
        <h1 class="page-title"><i class="fas fa-tachometer-alt"></i> Dashboard</h1>
        <p class="page-subtitle">Ringkasan performa untuk: <?php echo $nama_header; ?></p>
    </div>

    <h4 class="mb-3"><i class="fas fa-clock"></i> Ringkasan Saat Ini</h4>
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body"><div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Populasi (Aktif)</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($total_populasi); ?> Ekor</div>
                    </div>
                    <div class="col-auto"><i class="fas fa-users fa-2x text-gray-300"></i></div>
                </div></div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-left-secondary shadow h-100 py-2">
                <div class="card-body"><div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">Sisa Pakan</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($total_sisa_pakan, 2, ',', '.'); ?> Kg</div>
                    </div>
                    <div class="col-auto"><i class="fas fa-boxes fa-2x text-gray-300"></i></div>
                </div></div>
            </div>
        </div>

        <?php // Kartu tambahan hanya untuk Pimpinan ?>
        <?php if ($current_user_role === 'Pimpinan'): ?>
             <div class="col-md-3">
                <div class="card border-left-success shadow h-100 py-2">
                    <div class="card-body"><div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Kandang Aktif</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($jumlah_kandang_tampil); ?></div>
                        </div>
                        <div class="col-auto"><i class="fas fa-home fa-2x text-gray-300"></i></div>
                    </div></div>
                </div>
            </div>
             <div class="col-md-3">
                 <div class="card border-left-info shadow h-100 py-2">
                    <div class="card-body"><div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Produksi Telur (Hari Ini)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($total_produksi_today, 2, ',', '.'); ?> Kg</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-egg fa-2x text-gray-300"></i></div>
                    </div></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-left-danger shadow h-100 py-2">
                     <div class="card-body"><div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Kematian (Hari Ini)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($total_kematian_today); ?> Ekor</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-skull-crossbones fa-2x text-gray-300"></i></div>
                    </div></div>
                </div>
            </div>
            <div class="col-md-3">
                 <div class="card border-left-warning shadow h-100 py-2">
                    <div class="card-body"><div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">FCR (Kemarin)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_fcr_kemarin; ?></div>
                        </div>
                        <div class="col-auto"><i class="fas fa-chart-line fa-2x text-gray-300"></i></div>
                    </div></div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <hr>

     <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card shadow h-100">
                <div class="card-header bg-primary text-white"><h6 class="m-0 font-weight-bold"><i class="fas fa-calendar-check"></i> Performa Bulan Ini (<?php echo $nama_bulan_ini; ?>)</h6></div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center"><span><i class="fas fa-egg me-2 text-info"></i> Total Produksi Telur</span><span class="badge bg-info rounded-pill fs-6"><?php echo number_format($bulan_ini_produksi, 2, ',', '.'); ?> Kg</span></li>
                        <li class="list-group-item d-flex justify-content-between align-items-center"><span><i class="fas fa-seedling me-2 text-success"></i> Total Penggunaan Pakan</span><span class="badge bg-success rounded-pill fs-6"><?php echo number_format($bulan_ini_pakan, 2, ',', '.'); ?> Kg</span></li>
                        <li class="list-group-item d-flex justify-content-between align-items-center"><span><i class="fas fa-skull-crossbones me-2 text-danger"></i> Total Kematian + Afkir</span><span class="badge bg-danger rounded-pill fs-6"><?php echo number_format($bulan_ini_mati_afkir); ?> Ekor</span></li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card shadow h-100">
                <div class="card-header bg-secondary text-white"><h6 class="m-0 font-weight-bold"><i class="fas fa-calendar-alt"></i> Performa Bulan Kemarin (<?php echo $nama_bulan_lalu; ?>)</h6></div>
                <div class="card-body p-0">
                     <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center"><span><i class="fas fa-egg me-2 text-muted"></i> Total Produksi Telur</span><span class="badge bg-light text-dark rounded-pill fs-6"><?php echo number_format($bulan_lalu_produksi, 2, ',', '.'); ?> Kg</span></li>
                        <li class="list-group-item d-flex justify-content-between align-items-center"><span><i class="fas fa-seedling me-2 text-muted"></i> Total Penggunaan Pakan</span><span class="badge bg-light text-dark rounded-pill fs-6"><?php echo number_format($bulan_lalu_pakan, 2, ',', '.'); ?> Kg</span></li>
                        <li class="list-group-item d-flex justify-content-between align-items-center"><span><i class="fas fa-skull-crossbones me-2 text-muted"></i> Total Kematian + Afkir</span><span class="badge bg-light text-dark rounded-pill fs-6"><?php echo number_format($bulan_lalu_mati_afkir); ?> Ekor</span></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-lg-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-chart-area me-1"></i> Grafik Produksi Telur Harian (<?php echo $nama_bulan_ini; ?>)</h6></div>
                <div class="card-body">
                    <?php if (!empty($chart_labels) && count(array_filter($chart_data_produksi)) > 0): ?>
                        <div class="chart-area" style="height: 300px;"><canvas id="produksiBulananChart"></canvas></div>
                    <?php else: ?>
                        <div class="alert alert-warning text-center">Data produksi bulanan belum tersedia untuk ditampilkan dalam grafik.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if ($current_user_role === 'Pimpinan' && !empty($data_per_kandang)): ?>
         <hr>
         <h4 class="mb-3"><i class="fas fa-home"></i> Detail Per Kandang Aktif</h4>
         <div class="row g-3">
             <?php foreach ($data_per_kandang as $id_k => $data): ?>
                <?php
                $k_prod_bi = $data['prod_bulan_ini'] ?? 0;
                $k_mati_bi = $data['mati_bulan_ini'] ?? 0;
                $k_prod_bl = $data['prod_bulan_lalu'] ?? 0;
                $k_mati_bl = $data['mati_bulan_lalu'] ?? 0;
                ?>
                <div class="col-lg-6">
                    <div class="card shadow mb-4">
                        <div class="card-header bg-light py-3"><h6 class="m-0 font-weight-bold text-secondary"><?php echo htmlspecialchars($data['nama_kandang']); ?> (<?php echo is_numeric($data['umur']) ? number_format($data['umur'], 1) . ' Mg' : 'N/A'; ?>)</h6></div>
                        <div class="card-body">
                            <div class="row mb-2">
                                <div class="col-6"><div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Populasi</div><div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($data['populasi']); ?></div></div>
                                <div class="col-6"><div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">Sisa Pakan</div><div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($data['sisa_pakan'], 2, ',', '.'); ?> Kg</div></div>
                            </div><hr class="my-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Bulan Ini (<?php echo $nama_bulan_ini; ?>)</div>
                            <ul class="list-group list-group-flush mb-2" style="font-size: 0.85rem;">
                                <li class="list-group-item d-flex justify-content-between align-items-center p-1 px-2"><span>Produksi Telur:</span><span class="font-weight-bold"><?php echo number_format($k_prod_bi, 2, ',', '.'); ?> Kg</span></li>
                                <li class="list-group-item d-flex justify-content-between align-items-center p-1 px-2"><span>Mati/Afkir:</span><span class="font-weight-bold"><?php echo number_format($k_mati_bi); ?> Ekor</span></li>
                            </ul>
                            <div class="text-xs font-weight-bold text-muted text-uppercase mb-1">Bulan Lalu (<?php echo $nama_bulan_lalu; ?>)</div>
                            <ul class="list-group list-group-flush" style="font-size: 0.85rem;">
                                <li class="list-group-item d-flex justify-content-between align-items-center p-1 px-2"><span>Produksi Telur:</span><span class="font-weight-bold"><?php echo number_format($k_prod_bl, 2, ',', '.'); ?> Kg</span></li>
                                <li class="list-group-item d-flex justify-content-between align-items-center p-1 px-2"><span>Mati/Afkir:</span><span class="font-weight-bold"><?php echo number_format($k_mati_bl); ?> Ekor</span></li>
                            </ul>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
         </div>
         <hr>
     <?php endif; ?>

     <?php if ($current_user_role === 'Pimpinan' && $jumlah_kandang_tampil > 1): ?>
         <h4 class="mb-3"><i class="fas fa-table"></i> Ringkasan Performa Kandang Kemarin (<?php echo $yesterday_formatted; ?>)</h4>
         <div class="row">
             <div class="col-lg-12">
                 <div class="card shadow mb-4">
                    <div class="card-body">
                        <div class="table-responsive">
                             <table class="table table-bordered table-striped table-hover" width="100%" cellspacing="0" style="font-size: 0.9rem;">
                            <thead class="table-light text-center">
                                    <tr>
                                        <th>Nama Kandang</th>
                                        <th>Populasi</th>
                                        <th>Umur (Mg)</th>
                                        <th>Produksi (Kg)</th>
                                        <th>Mati (Ekor)</th>
                                        <th>Pakan (Kg)</th>
                                        <th>FCR</th>
                                        <th>Sisa Pakan (Kg)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($data_per_kandang as $id_k => $data): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($data['nama_kandang']); ?></td>
                                        <td class="text-end"><?php echo number_format($data['populasi']); ?></td>
                                        <td class="text-end"><?php echo is_numeric($data['umur']) ? number_format($data['umur'], 1, ',', '.') : 'N/A'; ?></td>
                                        <td class="text-end"><?php echo number_format($data['produksi_kemarin'], 2, ',', '.'); ?></td>
                                        <td class="text-end text-danger"><?php echo number_format($data['kematian_kemarin']); ?></td>
                                        <td class="text-end"><?php echo number_format($data['pakan_pakai_kemarin'], 2, ',', '.'); ?></td>
                                        <td class="text-end fw-bold"><?php echo $data['fcr_kemarin']; ?></td>
                                        <td class="text-end"><?php echo number_format($data['sisa_pakan'], 2, ',', '.'); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
         </div>
     <?php endif; ?>


</div> <?php include 'templates/footer.php'; // Include footer ?>

<style>
    /* ... (CSS styles tidak berubah) ... */
    .border-left-primary { border-left: 0.25rem solid #4e73df !important; }
    .border-left-success { border-left: 0.25rem solid #1cc88a !important; }
    .border-left-info { border-left: 0.25rem solid #36b9cc !important; }
    .border-left-warning { border-left: 0.25rem solid #f6c23e !important; }
    .border-left-danger { border-left: 0.25rem solid #e74a3b !important; }
    .border-left-secondary { border-left: 0.25rem solid #858796 !important; }
    .text-gray-300 { color: #dddfeb !important; }
    .text-gray-800 { color: #5a5c69 !important; }
    .text-xs { font-size: .7rem; }
    .font-weight-bold { font-weight: 700 !important; }
    .chart-area { position: relative; width: 100%; }
    .card-header .m-0 { font-size: 0.9rem; }
    .list-group-item { font-size: 0.9rem; }
    .list-group-item .badge { font-size: 0.9rem; font-weight: 600; }
    .card-body .list-group-item { border-left: 0; border-right: 0; }
    .card-body .list-group-item:first-child { border-top: 0; }
    .card-body .list-group-item:last-child { border-bottom: 0; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Data dari PHP
    const chartLabels = <?php echo $chart_labels_json; ?>;
    const chartDataProduksi = <?php echo $chart_data_produksi_json; ?>;
    const namaBulan = '<?php echo $nama_bulan_ini; ?>';

    // Grafik Produksi Bulanan
    const ctx = document.getElementById('produksiBulananChart');
    const totalProduksiBulanIni = chartDataProduksi.reduce((a, b) => a + b, 0);

    if (ctx && chartLabels.length > 0 && totalProduksiBulanIni > 0) {
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'Produksi Telur (Kg)', data: chartDataProduksi,
                    borderColor: 'rgb(54, 162, 235)', backgroundColor: 'rgba(54, 162, 235, 0.1)',
                    tension: 0.1, fill: true, pointRadius: 2, pointHoverRadius: 4
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                scales: {
                    x: { title: { display: true, text: `Tanggal (${namaBulan})` }, grid: { display: false } },
                    y: { title: { display: true, text: 'Total Produksi (Kg)' }, beginAtZero: true }
                },
                 plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: "rgb(255,255,255)", bodyColor: "#858796", borderColor: '#dddfeb',
                        borderWidth: 1, padding: 10, displayColors: false, intersect: false,
                         callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) label += ': ';
                                if (context.parsed.y !== null) {
                                     label += new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(context.parsed.y) + ' Kg';
                                }
                                return label;
                            }
                        }
                    }
                },
                hover: { mode: 'index', intersect: false }
            }
        });
    }

    // --- MODIFIKASI: Hapus inisialisasi DataTables untuk #tabelRingkasanKandang ---
    /*
     if ($.fn.DataTable && $('#tabelRingkasanKandang').length) {
        $('#tabelRingkasanKandang').DataTable({
            // ... Konfigurasi lama ...
        });
     }
    */
    // --- AKHIR MODIFIKASI ---

     // Inisialisasi Tooltip (jika masih diperlukan di elemen lain)
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle-tooltip="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
      return tooltipTriggerEl ? new bootstrap.Tooltip(tooltipTriggerEl) : null;
    });

});
</script>

</body>
</html>