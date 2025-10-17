<?php
include '../templates/header.php';
global $folder_base;

// ... (Seluruh logika PHP di bagian atas untuk mengambil dan menghitung data laporan tetap sama persis) ...
$kandang_list = $koneksi->query("SELECT id_kandang, nama_kandang FROM kandang");
$id_kandang_terpilih = isset($_GET['id_kandang']) ? $_GET['id_kandang'] : '';
$tgl_awal = isset($_GET['tgl_awal']) ? $_GET['tgl_awal'] : date('Y-m-01');
$tgl_akhir = isset($_GET['tgl_akhir']) ? $_GET['tgl_akhir'] : date('Y-m-t');
$laporan_lengkap = [];
$master_kandang = null;
if ($id_kandang_terpilih) {
    // KODE LENGKAP UNTUK KALKULASI DATA
    $stmt_kandang = $koneksi->prepare("SELECT * FROM kandang WHERE id_kandang = ?");
    $stmt_kandang->bind_param("i", $id_kandang_terpilih);
    $stmt_kandang->execute();
    $master_kandang = $stmt_kandang->get_result()->fetch_assoc();
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
    foreach($pakan_dibeli_pada_periode as $pakan) { $pakan_masuk_harian[$pakan['tanggal_beli']] = ($pakan_masuk_harian[$pakan['tanggal_beli']] ?? 0) + $pakan['jumlah_kg']; }
    $stmt_laporan = $koneksi->prepare("SELECT * FROM laporan_harian WHERE id_kandang = ? AND tanggal BETWEEN ? AND ? ORDER BY tanggal ASC");
    $stmt_laporan->bind_param("iss", $id_kandang_terpilih, $tgl_awal, $tgl_akhir);
    $stmt_laporan->execute();
    $result_laporan = $stmt_laporan->get_result();
    $sisa_pakan_sebelumnya_harian = [];
    while($row = $result_laporan->fetch_assoc()) {
        $item = $row; $tanggal_sekarang = $row['tanggal'];
        $tgl_masuk = new DateTime($master_kandang['tgl_masuk_awal']); $tgl_laporan = new DateTime($row['tanggal']);
        $selisih_hari = $tgl_laporan->diff($tgl_masuk)->days;
        $item['umur_ayam_minggu'] = ($master_kandang['umur_ayam_awal'] + $selisih_hari) / 7;
        $sisa_ayam_hari_ini = $sisa_ayam_sebelumnya + $row['ayam_masuk'] - $row['ayam_mati'] - $row['ayam_afkir'];
        $item['sisa_ayam'] = $sisa_ayam_hari_ini;
        $total_produksi_harian = $row['telur_baik_kg'] + $row['telur_tipis_kg'] + $row['telur_pecah_kg'];
        $sisa_telur_hari_ini = $sisa_telur_sebelumnya + $total_produksi_harian - $row['telur_terjual_kg'];
        $item['total_produksi_harian'] = $total_produksi_harian; $item['sisa_telur'] = $sisa_telur_hari_ini;
        $pakan_dibeli_hari_ini = $pakan_masuk_harian[$tanggal_sekarang] ?? 0;
        $pakan_terpakai_hari_ini_global_q = $koneksi->query("SELECT COALESCE(SUM(pakan_terpakai_kg), 0) as total FROM laporan_harian WHERE tanggal = '$tanggal_sekarang'");
        $pakan_terpakai_hari_ini_global = $pakan_terpakai_hari_ini_global_q->fetch_assoc()['total'];
        if (!isset($sisa_pakan_sebelumnya_harian[$tanggal_sekarang])) { $sisa_pakan_sebelumnya_harian[$tanggal_sekarang] = $sisa_pakan_sebelumnya; }
        $sisa_pakan_hari_ini = $sisa_pakan_sebelumnya_harian[$tanggal_sekarang] + $pakan_dibeli_hari_ini - $pakan_terpakai_hari_ini_global;
        $item['sisa_pakan_global'] = $sisa_pakan_hari_ini;
        $laporan_lengkap[] = $item;
        $sisa_ayam_sebelumnya = $sisa_ayam_hari_ini; $sisa_telur_sebelumnya = $sisa_telur_hari_ini;
        $sisa_pakan_sebelumnya_harian[date('Y-m-d', strtotime($tanggal_sekarang . ' +1 day'))] = $sisa_pakan_hari_ini;
    }
}
?>

<div class="container-fluid">
    <div class="page-header">
        <h1 class="page-title">Riwayat Laporan</h1>
        <p class="page-subtitle">Lihat riwayat laporan harian yang detail untuk setiap kandang.</p>
    </div>
    
    <div class="card mb-4">
        <div class="card-body">
            <form class="row g-3" method="GET">
                <div class="col-md-5">
                    <label for="id_kandang" class="form-label">Pilih Kandang</label>
                    <select class="form-select" id="id_kandang" name="id_kandang" required>
                        <option value="">-- Pilih Kandang --</option>
                        <?php if ($kandang_list) mysqli_data_seek($kandang_list, 0); ?>
                        <?php while($k = $kandang_list->fetch_assoc()): ?>
                            <option value="<?php echo $k['id_kandang']; ?>" <?php echo ($k['id_kandang'] == $id_kandang_terpilih) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($k['nama_kandang']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="tgl_awal" class="form-label">Dari Tanggal</label>
                    <input type="date" class="form-control" id="tgl_awal" name="tgl_awal" value="<?php echo $tgl_awal; ?>">
                </div>
                <div class="col-md-3">
                    <label for="tgl_akhir" class="form-label">Sampai Tanggal</label>
                    <input type="date" class="form-control" id="tgl_akhir" name="tgl_akhir" value="<?php echo $tgl_akhir; ?>">
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Cari</button>
                </div>
            </form>
            <?php if ($id_kandang_terpilih && !empty($laporan_lengkap)): ?>
            <div class="mt-3">
                <span>Ekspor Laporan: </span>
                <a href="export_excel.php?id_kandang=<?php echo $id_kandang_terpilih; ?>&tgl_awal=<?php echo $tgl_awal; ?>&tgl_akhir=<?php echo $tgl_akhir; ?>" class="btn btn-sm btn-success">
                    <i class="fas fa-file-excel"></i> Excel
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($id_kandang_terpilih && !empty($laporan_lengkap)): ?>
    <div class="card">
        <div class="card-header">
            Riwayat untuk Kandang: <?php echo htmlspecialchars($master_kandang['nama_kandang']); ?>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover" id="tabelRiwayat" style="width:100%; font-size: 0.85rem;">
                    <thead class="text-center" style="vertical-align: middle;">
                        <tr>
                            <th rowspan="2">Tgl</th>
                            <th rowspan="2">Umur (Minggu)</th>
                            <th colspan="4">Ayam (Ekor)</th>
                            <th colspan="2">Pakan (Kg)</th>
                            <th colspan="4">Produksi Telur (Kg)</th>
                            <th colspan="2">Penjualan Telur</th>
                            <th rowspan="2">Sisa Stok Telur (Kg)</th>
                            <th colspan="4">Pengeluaran Lain (Rp)</th>
                            <th rowspan="2">Keterangan</th>
                            <th rowspan="2">Aksi</th>
                        </tr>
                        <tr>
                            <th>Masuk</th><th>Mati</th><th>Afkir</th><th class="table-info">Sisa</th>
                            <th>Terpakai</th><th class="table-info">Sisa (Global)</th>
                            <th>Baik</th><th>Tipis</th><th>Pecah</th><th class="table-success">Total</th>
                            <th>Kg</th><th>Rp</th>
                            <th>Gaji Harian</th><th>Gaji Bulanan</th><th>Obat/Vit</th><th>Lain-Lain</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($laporan_lengkap as $laporan): ?>
                        <tr>
                            <td class="text-center"><?php echo date('d/m', strtotime($laporan['tanggal'])); ?></td>
                            <td class="text-center"><?php echo number_format($laporan['umur_ayam_minggu'], 1); ?></td>
                            <td class="text-center"><?php echo number_format($laporan['ayam_masuk']); ?></td>
                            <td class="text-center text-danger"><?php echo number_format($laporan['ayam_mati']); ?></td>
                            <td class="text-center text-warning"><?php echo number_format($laporan['ayam_afkir']); ?></td>
                            <td class="text-center fw-bold table-info"><?php echo number_format($laporan['sisa_ayam']); ?></td>
                            <td class="text-end"><?php echo number_format($laporan['pakan_terpakai_kg'], 2); ?></td>
                            <td class="text-end fw-bold table-info"><?php echo number_format($laporan['sisa_pakan_global'], 2); ?></td>
                            <td class="text-end"><?php echo number_format($laporan['telur_baik_kg'], 2); ?></td>
                            <td class="text-end"><?php echo number_format($laporan['telur_tipis_kg'], 2); ?></td>
                            <td class="text-end"><?php echo number_format($laporan['telur_pecah_kg'], 2); ?></td>
                            <td class="text-end fw-bold table-success"><?php echo number_format($laporan['total_produksi_harian'], 2); ?></td>
                            <td class="text-end"><?php echo number_format($laporan['telur_terjual_kg'], 2); ?></td>
                            <td class="text-end">Rp <?php echo number_format($laporan['pemasukan_telur']); ?></td>
                            <td class="text-end fw-bold table-info"><?php echo number_format($laporan['sisa_telur'], 2); ?></td>
                            <td class="text-end">Rp <?php echo number_format($laporan['gaji_harian']); ?></td>
                            <td class="text-end">Rp <?php echo number_format($laporan['gaji_bulanan']); ?></td>
                            <td class="text-end">Rp <?php echo number_format($laporan['obat_vitamin']); ?></td>
                            <td class="text-end">Rp <?php echo number_format($laporan['lain_lain_operasional']); ?></td>
                            <td><?php echo htmlspecialchars($laporan['keterangan_pengeluaran']); ?></td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-warning btn-edit" 
                                    data-bs-toggle="modal" data-bs-target="#editModal"
                                    data-id-laporan="<?php echo $laporan['id_laporan']; ?>"
                                    data-tanggal="<?php echo $laporan['tanggal']; ?>"
                                    data-ayam-masuk="<?php echo $laporan['ayam_masuk']; ?>"
                                    data-ayam-mati="<?php echo $laporan['ayam_mati']; ?>"
                                    data-ayam-afkir="<?php echo $laporan['ayam_afkir']; ?>"
                                    data-pakan-terpakai="<?php echo $laporan['pakan_terpakai_kg']; ?>"
                                    data-telur-baik="<?php echo $laporan['telur_baik_kg']; ?>"
                                    data-telur-tipis="<?php echo $laporan['telur_tipis_kg']; ?>"
                                    data-telur-pecah="<?php echo $laporan['telur_pecah_kg']; ?>"
                                    data-telur-terjual="<?php echo $laporan['telur_terjual_kg']; ?>"
                                    data-harga-jual="<?php echo $laporan['harga_jual_rata2']; ?>"
                                    data-gaji-harian="<?php echo $laporan['gaji_harian']; ?>"
                                    data-gaji-bulanan="<?php echo $laporan['gaji_bulanan']; ?>"
                                    data-obat-vitamin="<?php echo $laporan['obat_vitamin']; ?>"
                                    data-lain-lain="<?php echo $laporan['lain_lain_operasional']; ?>"
                                    data-keterangan="<?php echo htmlspecialchars($laporan['keterangan_pengeluaran']); ?>">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <a href="hapus.php?id=<?php echo $laporan['id_laporan']; ?>" class="btn btn-sm btn-danger btn-hapus"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php elseif ($id_kandang_terpilih): ?>
        <div class="alert alert-warning">Tidak ada riwayat laporan yang ditemukan untuk kandang dan periode tanggal yang dipilih.</div>
    <?php else: ?>
        <div class="alert alert-info">Silakan pilih kandang dan tentukan rentang tanggal untuk menampilkan riwayat laporan.</div>
    <?php endif; ?>
</div>

<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editModalLabel">Edit Laporan Harian</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="proses_edit.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" id="edit_id_laporan" name="id_laporan">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="edit_tanggal" class="form-label">Tanggal</label>
                            <input type="date" class="form-control" id="edit_tanggal" name="tanggal" readonly>
                        </div>
                         <div class="col-md-4">
                            <label for="edit_pakan_terpakai" class="form-label">Pakan Terpakai (Kg)</label>
                            <input type="number" step="0.01" class="form-control" id="edit_pakan_terpakai" name="pakan_terpakai_kg" required>
                        </div>
                        <div class="col-md-4">
                            </div>

                        <div class="col-12">
                            <h6><i class="fas fa-drumstick-bite"></i> Data Ayam</h6> <hr class="mt-1">
                        </div>
                        <div class="col-md-4">
                            <label for="edit_ayam_masuk" class="form-label">Ayam Masuk (Ekor)</label>
                            <input type="number" class="form-control" id="edit_ayam_masuk" name="ayam_masuk" required>
                        </div>
                        <div class="col-md-4">
                            <label for="edit_ayam_mati" class="form-label">Ayam Mati (Ekor)</label>
                            <input type="number" class="form-control" id="edit_ayam_mati" name="ayam_mati" required>
                        </div>
                        <div class="col-md-4">
                            <label for="edit_ayam_afkir" class="form-label">Ayam Afkir (Ekor)</label>
                            <input type="number" class="form-control" id="edit_ayam_afkir" name="ayam_afkir" required>
                        </div>

                        <div class="col-12">
                           <h6><i class="fas fa-egg"></i> Data Produksi & Penjualan Telur</h6> <hr class="mt-1">
                        </div>
                        <div class="col-md-3">
                            <label for="edit_telur_baik" class="form-label">Telur Baik (Kg)</label>
                            <input type="number" step="0.01" class="form-control" id="edit_telur_baik" name="telur_baik_kg" required>
                        </div>
                        <div class="col-md-3">
                            <label for="edit_telur_tipis" class="form-label">Telur Tipis (Kg)</label>
                            <input type="number" step="0.01" class="form-control" id="edit_telur_tipis" name="telur_tipis_kg" required>
                        </div>
                        <div class="col-md-3">
                            <label for="edit_telur_pecah" class="form-label">Telur Pecah (Kg)</label>
                            <input type="number" step="0.01" class="form-control" id="edit_telur_pecah" name="telur_pecah_kg" required>
                        </div>
                         <div class="col-md-3">
                            </div>
                        <div class="col-md-3">
                            <label for="edit_telur_terjual" class="form-label">Telur Terjual (Kg)</label>
                            <input type="number" step="0.01" class="form-control" id="edit_telur_terjual" name="telur_terjual_kg" required>
                        </div>
                        <div class="col-md-3">
                            <label for="edit_harga_jual" class="form-label">Harga Jual Rata-rata /Kg</label>
                            <input type="number" class="form-control" id="edit_harga_jual" name="harga_jual_rata2" required>
                        </div>

                        <div class="col-12">
                           <h6><i class="fas fa-money-bill-wave"></i> Data Pengeluaran Operasional</h6> <hr class="mt-1">
                        </div>
                        <div class="col-md-3">
                            <label for="edit_gaji_harian" class="form-label">Gaji Harian (Rp)</label>
                            <input type="number" class="form-control" id="edit_gaji_harian" name="gaji_harian" required>
                        </div>
                        <div class="col-md-3">
                            <label for="edit_gaji_bulanan" class="form-label">Gaji Bulanan (Rp)</label>
                            <input type="number" class="form-control" id="edit_gaji_bulanan" name="gaji_bulanan" required>
                        </div>
                        <div class="col-md-3">
                            <label for="edit_obat_vitamin" class="form-label">Obat/Vitamin (Rp)</label>
                            <input type="number" class="form-control" id="edit_obat_vitamin" name="obat_vitamin" required>
                        </div>
                        <div class="col-md-3">
                            <label for="edit_lain_lain" class="form-label">Lain-lain (Rp)</label>
                            <input type="number" class="form-control" id="edit_lain_lain" name="lain_lain_operasional" required>
                        </div>
                        <div class="col-md-12">
                            <label for="edit_keterangan" class="form-label">Keterangan Pengeluaran</label>
                            <textarea class="form-control" id="edit_keterangan" name="keterangan_pengeluaran" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                    <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php include '../templates/footer.php'; ?>

<script>
$(document).ready(function() {
    $('#tabelRiwayat').DataTable({
        "language": { "url": "https://cdn.datatables.net/plug-ins/1.13.7/i18n/id.json" },
        "scrollX": true, 
        "order": [[ 0, "asc" ]]
    });

    // 1. FUNGSI HAPUS DENGAN KONFIRMASI SWEETALERT (Diaktifkan)
    $('.btn-hapus').on('click', function(e) {
        e.preventDefault(); // Mencegah link langsung dieksekusi
        const href = $(this).attr('href');

        Swal.fire({
            title: 'Anda yakin?',
            text: "Data laporan harian ini akan dihapus secara permanen!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Ya, hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                document.location.href = href; // Lanjutkan ke link hapus jika dikonfirmasi
            }
        });
    });
    
    // 2. FUNGSI MODAL EDIT (Diaktifkan dan Dilengkapi)
    const editModal = document.getElementById('editModal');
    editModal.addEventListener('show.bs.modal', function (event) {
        // Tombol yang memicu modal
        const button = event.relatedTarget;

        // Ekstrak data dari atribut data-*
        const idLaporan = button.dataset.idLaporan;
        const tanggal = button.dataset.tanggal;
        const ayamMasuk = button.dataset.ayamMasuk;
        const ayamMati = button.dataset.ayamMati;
        const ayamAfkir = button.dataset.ayamAfkir;
        const pakanTerpakai = button.dataset.pakanTerpakai;
        const telurBaik = button.dataset.telurBaik;
        const telurTipis = button.dataset.telurTipis;
        const telurPecah = button.dataset.telurPecah;
        const telurTerjual = button.dataset.telurTerjual;
        const hargaJual = button.dataset.hargaJual;
        const gajiHarian = button.dataset.gajiHarian;
        const gajiBulanan = button.dataset.gajiBulanan;
        const obatVitamin = button.dataset.obatVitamin;
        const lainLain = button.dataset.lainLain;
        const keterangan = button.dataset.keterangan;

        // Dapatkan elemen form di dalam modal
        const modal = this;
        modal.querySelector('#edit_id_laporan').value = idLaporan;
        modal.querySelector('#edit_tanggal').value = tanggal;
        modal.querySelector('#edit_ayam_masuk').value = ayamMasuk;
        modal.querySelector('#edit_ayam_mati').value = ayamMati;
        modal.querySelector('#edit_ayam_afkir').value = ayamAfkir;
        modal.querySelector('#edit_pakan_terpakai').value = pakanTerpakai;
        modal.querySelector('#edit_telur_baik').value = telurBaik;
        modal.querySelector('#edit_telur_tipis').value = telurTipis;
        modal.querySelector('#edit_telur_pecah').value = telurPecah;
        modal.querySelector('#edit_telur_terjual').value = telurTerjual;
        modal.querySelector('#edit_harga_jual').value = hargaJual;
        modal.querySelector('#edit_gaji_harian').value = gajiHarian;
        modal.querySelector('#edit_gaji_bulanan').value = gajiBulanan;
        modal.querySelector('#edit_obat_vitamin').value = obatVitamin;
        modal.querySelector('#edit_lain_lain').value = lainLain;
        modal.querySelector('#edit_keterangan').value = keterangan;
    });
});
</script>

</body>
</html>