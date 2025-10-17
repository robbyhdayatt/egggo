<?php
include '../templates/header.php';
global $folder_base;

// Cek notifikasi
$pesan = '';
if (isset($_GET['status']) && $_GET['status'] == 'sukses_update') {
    $pesan = "<div class='alert alert-success'>Data laporan berhasil diperbarui!</div>";
}

$kandang_list = $koneksi->query("SELECT id_kandang, nama_kandang FROM kandang ORDER BY nama_kandang");
$id_kandang_terpilih = isset($_GET['id_kandang']) ? $_GET['id_kandang'] : '';
$tgl_awal = isset($_GET['tgl_awal']) ? $_GET['tgl_awal'] : date('Y-m-01');
$tgl_akhir = isset($_GET['tgl_akhir']) ? $_GET['tgl_akhir'] : date('Y-m-t');

$laporan_lengkap = [];
$master_kandang = null;
$nama_header = "Semua Kandang";

if (!empty($id_kandang_terpilih)) {
    
    $where_kandang = "";
    if ($id_kandang_terpilih !== 'semua') {
        $where_kandang = "id_kandang = " . (int)$id_kandang_terpilih;
        $stmt_kandang = $koneksi->prepare("SELECT * FROM kandang WHERE id_kandang = ?");
        $stmt_kandang->bind_param("i", $id_kandang_terpilih);
        $stmt_kandang->execute();
        $master_kandang = $stmt_kandang->get_result()->fetch_assoc();
        $nama_header = $master_kandang['nama_kandang'];
    }

    // Query untuk mengambil semua data laporan harian dalam rentang tanggal
    $query_laporan = "
        SELECT lh.*, k.nama_kandang 
        FROM laporan_harian lh
        JOIN kandang k ON lh.id_kandang = k.id_kandang
        WHERE lh.tanggal BETWEEN ? AND ?
    ";
    if ($id_kandang_terpilih !== 'semua') {
        $query_laporan .= " AND lh.id_kandang = ?";
    }
    $query_laporan .= " ORDER BY lh.tanggal DESC, lh.id_kandang ASC";

    $stmt_laporan = $koneksi->prepare($query_laporan);
    if ($id_kandang_terpilih !== 'semua') {
        $stmt_laporan->bind_param("ssi", $tgl_awal, $tgl_akhir, $id_kandang_terpilih);
    } else {
        $stmt_laporan->bind_param("ss", $tgl_awal, $tgl_akhir);
    }
    $stmt_laporan->execute();
    $laporan_lengkap = $stmt_laporan->get_result()->fetch_all(MYSQLI_ASSOC);

    // Ambil semua data pengeluaran dalam rentang tanggal
    $pengeluaran_harian = [];
    $query_pengeluaran = "
        SELECT tanggal_pengeluaran, SUM(jumlah) as total, GROUP_CONCAT(keterangan SEPARATOR '; ') as detail
        FROM pengeluaran
        WHERE tanggal_pengeluaran BETWEEN ? AND ?
    ";
    if ($id_kandang_terpilih !== 'semua') {
        $query_pengeluaran .= " AND id_kandang = ?";
    }
    $query_pengeluaran .= " GROUP BY tanggal_pengeluaran";
    
    $stmt_pengeluaran = $koneksi->prepare($query_pengeluaran);
    if ($id_kandang_terpilih !== 'semua') {
        $stmt_pengeluaran->bind_param("ssi", $tgl_awal, $tgl_akhir, $id_kandang_terpilih);
    } else {
        $stmt_pengeluaran->bind_param("ss", $tgl_awal, $tgl_akhir);
    }
    $stmt_pengeluaran->execute();
    $result_pengeluaran = $stmt_pengeluaran->get_result();
    while ($row = $result_pengeluaran->fetch_assoc()) {
        $pengeluaran_harian[$row['tanggal_pengeluaran']] = $row;
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
            <form class="row g-3" method="GET">
                <div class="col-md-5">
                    <label for="id_kandang" class="form-label">Pilih Kandang</label>
                    <select class="form-select" id="id_kandang" name="id_kandang" required>
                        <option value="">-- Pilih Opsi --</option>
                        <option value="semua" <?php echo ($id_kandang_terpilih == 'semua') ? 'selected' : ''; ?>>Semua Kandang</option>
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
        </div>
    </div>

    <?php if ($id_kandang_terpilih && !empty($laporan_lengkap)): ?>
    <div class="card">
        <div class="card-header">Riwayat untuk: <?php echo htmlspecialchars($nama_header); ?></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover" id="tabelRiwayat" style="width:100%; font-size: 0.85rem;">
                    <thead class="text-center table-light" style="vertical-align: middle;">
                        <tr>
                            <th rowspan="2">Tgl</th>
                            <?php if ($id_kandang_terpilih === 'semua') echo '<th rowspan="2">Kandang</th>'; ?>
                            <th colspan="3">Ayam (Ekor)</th>
                            <th rowspan="2">Pakan Terpakai (Kg)</th>
                            <th colspan="4">Produksi Telur (Kg)</th>
                            <th colspan="3">Penjualan Telur</th>
                            <th rowspan="2">Total Pengeluaran (Rp)</th>
                            <th rowspan="2">Aksi</th>
                        </tr>
                        <tr>
                            <th>Masuk</th><th>Mati</th><th>Afkir</th>
                            <th>Baik</th><th>Tipis</th><th>Pecah</th><th class="table-success">Total</th>
                            <th>Kg</th><th>Harga/Kg</th><th class="table-info">Total Rp</th>
                        </tr>
                    </thead>
                        <tbody>
                            <?php foreach ($laporan_lengkap as $laporan): ?>
                            <tr>
                                <td class="text-center"><?php echo date('d/m/Y', strtotime($laporan['tanggal'])); ?></td>
                                <?php if ($id_kandang_terpilih === 'semua') echo '<td>'.htmlspecialchars($laporan['nama_kandang']).'</td>'; ?>
                                <td class="text-center"><?php echo number_format($laporan['ayam_masuk']); ?></td>
                                <td class="text-center text-danger"><?php echo number_format($laporan['ayam_mati']); ?></td>
                                <td class="text-center text-warning"><?php echo number_format($laporan['ayam_afkir']); ?></td>
                                <td class="text-end"><?php echo number_format($laporan['pakan_terpakai_kg'], 2); ?></td>
                                <td class="text-end"><?php echo number_format($laporan['telur_baik_kg'], 2); ?></td>
                                <td class="text-end"><?php echo number_format($laporan['telur_tipis_kg'], 2); ?></td>
                                <td class="text-end"><?php echo number_format($laporan['telur_pecah_kg'], 2); ?></td>
                                <td class="text-end fw-bold table-success"><?php echo number_format($laporan['telur_baik_kg'] + $laporan['telur_tipis_kg'] + $laporan['telur_pecah_kg'], 2); ?></td>
                                <td class="text-end"><?php echo number_format($laporan['telur_terjual_kg'], 2); ?></td>
                                <td class="text-end">Rp <?php echo number_format($laporan['harga_jual_rata2']); ?></td>
                                <td class="text-end fw-bold table-info">Rp <?php echo number_format($laporan['pemasukan_telur']); ?></td>
                                <td class="text-end">Rp <?php echo number_format($pengeluaran_harian[$laporan['tanggal']]['total'] ?? 0); ?></td>
                                <td class="text-center">
                                    <div class="btn-group" role="group">
                                        <button type="button" class="btn btn-sm btn-warning btn-edit" data-bs-toggle="modal" data-bs-target="#editModal"
                                            data-id-laporan="<?php echo $laporan['id_laporan']; ?>"
                                            data-tanggal="<?php echo $laporan['tanggal']; ?>"
                                            data-nama-kandang="<?php echo htmlspecialchars($laporan['nama_kandang']); ?>"
                                            data-ayam-masuk="<?php echo $laporan['ayam_masuk']; ?>"
                                            data-ayam-mati="<?php echo $laporan['ayam_mati']; ?>"
                                            data-ayam-afkir="<?php echo $laporan['ayam_afkir']; ?>"
                                            data-pakan-terpakai="<?php echo $laporan['pakan_terpakai_kg']; ?>"
                                            data-telur-baik="<?php echo $laporan['telur_baik_kg']; ?>"
                                            data-telur-tipis="<?php echo $laporan['telur_tipis_kg']; ?>"
                                            data-telur-pecah="<?php echo $laporan['telur_pecah_kg']; ?>"
                                            data-telur-terjual="<?php echo $laporan['telur_terjual_kg']; ?>"
                                            data-harga-jual="<?php echo $laporan['harga_jual_rata2']; ?>"
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
            <form action="proses_update.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" id="edit_id_laporan" name="id_laporan">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Kandang</label>
                            <input type="text" class="form-control" id="edit_nama_kandang" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tanggal</label>
                            <input type="date" class="form-control" id="edit_tanggal" name="tanggal" readonly>
                        </div>

                        <div class="col-12 mt-4"><h6><i class="fas fa-crow"></i> Data Ayam</h6><hr class="mt-1"></div>
                        <div class="col-md-4">
                            <label for="edit_ayam_masuk" class="form-label">Ayam Masuk (Ekor)</label>
                            <input type="tel" class="form-control" id="edit_ayam_masuk" name="ayam_masuk" required>
                        </div>
                        <div class="col-md-4">
                            <label for="edit_ayam_mati" class="form-label">Ayam Mati (Ekor)</label>
                            <input type="tel" class="form-control" id="edit_ayam_mati" name="ayam_mati" required>
                        </div>
                        <div class="col-md-4">
                            <label for="edit_ayam_afkir" class="form-label">Ayam Afkir (Ekor)</label>
                            <input type="tel" class="form-control" id="edit_ayam_afkir" name="ayam_afkir" required>
                        </div>

                        <div class="col-12 mt-4"><h6><i class="fas fa-egg"></i> Pakan & Produksi</h6><hr class="mt-1"></div>
                        <div class="col-md-3">
                            <label for="edit_pakan_terpakai" class="form-label">Pakan Terpakai (Kg)</label>
                            <input type="number" step="0.01" class="form-control" id="edit_pakan_terpakai" name="pakan_terpakai_kg" required>
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
                        
                        <div class="col-12 mt-4"><h6><i class="fas fa-dollar-sign"></i> Penjualan Telur</h6><hr class="mt-1"></div>
                        <div class="col-md-6">
                            <label for="edit_telur_terjual" class="form-label">Telur Terjual (Kg)</label>
                            <input type="number" step="0.01" class="form-control" id="edit_telur_terjual" name="telur_terjual_kg" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_harga_jual" class="form-label">Harga Jual Rata-rata /Kg</label>
                            <input type="tel" class="form-control" id="edit_harga_jual" name="harga_jual_rata2" required>
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
        "order": [[ 0, "desc" ]] // Sortir berdasarkan tanggal (kolom pertama) terbaru
    });

    // Inisialisasi Tooltip
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl)
    })

    // Fungsi format angka
    function formatNumberWithDots(input) {
        let value = $(input).val().replace(/[^0-9]/g, '');
        if (value === '' || value === null) { $(input).val(''); return; }
        $(input).val(new Intl.NumberFormat('id-ID').format(value));
    }

    // Terapkan format ke input modal
    $('#edit_ayam_masuk, #edit_ayam_mati, #edit_ayam_afkir, #edit_harga_jual').on('keyup input', function() {
        formatNumberWithDots(this);
    });

    // Logika untuk Modal Edit
    const editModal = document.getElementById('editModal');
    editModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        
        $('#edit_id_laporan').val(button.dataset.idLaporan);
        $('#edit_tanggal').val(button.dataset.tanggal);
        $('#edit_nama_kandang').val(button.dataset.namaKandang);
        $('#edit_ayam_masuk').val(button.dataset.ayamMasuk).trigger('keyup');
        $('#edit_ayam_mati').val(button.dataset.ayamMati).trigger('keyup');
        $('#edit_ayam_afkir').val(button.dataset.ayamAfkir).trigger('keyup');
        $('#edit_pakan_terpakai').val(button.dataset.pakanTerpakai);
        $('#edit_telur_baik').val(button.dataset.telurBaik);
        $('#edit_telur_tipis').val(button.dataset.telurTipis);
        $('#edit_telur_pecah').val(button.dataset.telurPecah);
        $('#edit_telur_terjual').val(button.dataset.telurTerjual);
        $('#edit_harga_jual').val(button.dataset.hargaJual).trigger('keyup');
    });

    // --- LOGIKA HAPUS DENGAN SWEETALERT DITAMBAHKAN KEMBALI ---
    $('#tabelRiwayat tbody').on('click', '.btn-hapus', function(e) {
        e.preventDefault();
        const href = $(this).attr('href');
        const urlParams = new URLSearchParams(window.location.search);
        const redirectUrl = href + '&' + urlParams.toString();

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
            if (result.isConfirmed) {
                // Redirect ke URL hapus dengan menyertakan parameter filter
                window.location.href = redirectUrl;
            }
        });
    });
});
</script>

</body>
</html>