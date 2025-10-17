<?php
include '../templates/header.php';

$kandang_list = $koneksi->query("SELECT id_kandang, nama_kandang FROM kandang WHERE status = 'Aktif'");
$pesan = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_kandang = $_POST['id_kandang'] ?? null;
    $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
    
    // Angka Integer (dibersihkan dari titik)
    $ayam_masuk = $_POST['ayam_masuk'] ? (int)str_replace('.', '', $_POST['ayam_masuk']) : 0;
    $ayam_mati = $_POST['ayam_mati'] ? (int)str_replace('.', '', $_POST['ayam_mati']) : 0;
    $ayam_afkir = $_POST['ayam_afkir'] ? (int)str_replace('.', '', $_POST['ayam_afkir']) : 0;
    $harga_jual_rata2 = $_POST['harga_jual_rata2'] ? (int)str_replace('.', '', $_POST['harga_jual_rata2']) : 0;
    $gaji_harian = $_POST['gaji_harian'] ? (int)str_replace('.', '', $_POST['gaji_harian']) : 0;
    $gaji_bulanan = $_POST['gaji_bulanan'] ? (int)str_replace('.', '', $_POST['gaji_bulanan']) : 0;
    $obat_vitamin = $_POST['obat_vitamin'] ? (int)str_replace('.', '', $_POST['obat_vitamin']) : 0;
    $lain_lain_operasional = $_POST['lain_lain_operasional'] ? (int)str_replace('.', '', $_POST['lain_lain_operasional']) : 0;

    // Angka Desimal (kg) - Mengganti koma dengan titik jika ada
    $pakan_terpakai_kg = $_POST['pakan_terpakai_kg'] ? (float)str_replace(',', '.', $_POST['pakan_terpakai_kg']) : 0.0;
    $telur_baik_kg = $_POST['telur_baik_kg'] ? (float)str_replace(',', '.', $_POST['telur_baik_kg']) : 0.0;
    $telur_tipis_kg = $_POST['telur_tipis_kg'] ? (float)str_replace(',', '.', $_POST['telur_tipis_kg']) : 0.0;
    $telur_pecah_kg = $_POST['telur_pecah_kg'] ? (float)str_replace(',', '.', $_POST['telur_pecah_kg']) : 0.0;
    $telur_terjual_kg = $_POST['telur_terjual_kg'] ? (float)str_replace(',', '.', $_POST['telur_terjual_kg']) : 0.0;
    
    // Kalkulasi dan Teks
    $pemasukan_telur = $telur_terjual_kg * $harga_jual_rata2;
    $keterangan_pengeluaran = $_POST['keterangan_pengeluaran'] ?? '';

    // Pengecekan data duplikat sebelum INSERT
    $stmt_check = $koneksi->prepare("SELECT id_laporan FROM laporan_harian WHERE id_kandang = ? AND tanggal = ?");
    $stmt_check->bind_param("is", $id_kandang, $tanggal);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    if ($result_check->num_rows > 0) {
        $pesan = "<div class='alert alert-danger'>Gagal menyimpan: Laporan untuk kandang dan tanggal ini sudah ada.</div>";
    } elseif (empty($id_kandang)) {
        $pesan = "<div class='alert alert-danger'>Gagal menyimpan laporan: Silakan pilih kandang terlebih dahulu.</div>";
    } else {
        $sql = "INSERT INTO laporan_harian (id_kandang, tanggal, ayam_masuk, ayam_mati, ayam_afkir, pakan_terpakai_kg, telur_baik_kg, telur_tipis_kg, telur_pecah_kg, telur_terjual_kg, harga_jual_rata2, pemasukan_telur, gaji_harian, gaji_bulanan, obat_vitamin, lain_lain_operasional, keterangan_pengeluaran) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $koneksi->prepare($sql);
        $stmt->bind_param("isiiidddddiiiiiis", $id_kandang, $tanggal, $ayam_masuk, $ayam_mati, $ayam_afkir, $pakan_terpakai_kg, $telur_baik_kg, $telur_tipis_kg, $telur_pecah_kg, $telur_terjual_kg, $harga_jual_rata2, $pemasukan_telur, $gaji_harian, $gaji_bulanan, $obat_vitamin, $lain_lain_operasional, $keterangan_pengeluaran);
        
        if ($stmt->execute()) {
            $pesan = "<div class='alert alert-success'>Laporan harian berhasil disimpan!</div>";
        } else {
            $pesan = "<div class='alert alert-danger'>Gagal menyimpan laporan: " . $stmt->error . "</div>";
        }
    }
}
?>

<div class="container-fluid">
    <div class="page-header">
        <h1 class="page-title">Input Laporan Harian</h1>
        <p class="page-subtitle">Catat data produksi dan operasional harian di sini.</p>
    </div>
    
    <?php echo $pesan; ?>

    <div class="card mb-4">
        <div class="card-header">
            Pilih Kandang & Tanggal
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="id_kandang_filter" class="form-label">Pilih Kandang <span class="text-danger">*</span></label>
                    <select class="form-select" id="id_kandang_filter" name="id_kandang_filter" required>
                        <option value="" disabled selected>-- Pilih Kandang --</option>
                        <?php if (isset($kandang_list) && $kandang_list->num_rows > 0) { mysqli_data_seek($kandang_list, 0); } ?>
                        <?php while($k = $kandang_list->fetch_assoc()): ?>
                            <option value="<?php echo $k['id_kandang']; ?>"><?php echo htmlspecialchars($k['nama_kandang']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="tanggal_filter" class="form-label">Tanggal Laporan <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="tanggal_filter" name="tanggal_filter" value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>" required>
                </div>
            </div>
        </div>
    </div>
    
    <div id="mainFormContainer" style="display: none;">
        <form method="POST" class="needs-validation" novalidate>
            <input type="hidden" id="id_kandang_hidden" name="id_kandang">
            <input type="hidden" id="tanggal_hidden" name="tanggal">

            <div id="summaryContainer" class="row g-3 mb-3 p-3 bg-light rounded" style="display: none;">
                <h5 class="col-12 mb-0 pb-0">Ringkasan Riwayat Kandang</h5>
                <div class="col-md-4">
                    <strong><i class="fas fa-crow"></i> Total Ayam Saat Ini:</strong>
                    <span id="summary_total_ayam">Memuat...</span> Ekor
                </div>
                <div class="col-md-4">
                    <strong><i class="fas fa-egg"></i> Total Telur Produksi:</strong>
                    <span id="summary_total_telur">Memuat...</span> Kg
                </div>
                <div class="col-md-4">
                    <strong><i class="fas fa-boxes"></i> Pakan Tersedia (Global):</strong>
                    <span id="summary_total_pakan_tersedia">Memuat...</span> Kg
                </div>
            </div>
            
            <div id="formDisabledMessage" class="alert alert-warning text-center" style="display: none;">
                <i class="fas fa-exclamation-triangle"></i> Anda sudah menginput laporan untuk kandang ini pada tanggal yang dipilih.
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <h5><i class="fas fa-crow"></i> Data Ayam</h5>
                            <div class="mb-3">
                                <label for="ayam_masuk" class="form-label">Ayam Masuk (ekor)</label>
                                <input type="tel" class="form-control" id="ayam_masuk" name="ayam_masuk" value="0" autocomplete="off">
                            </div>
                            <div class="mb-3">
                                <label for="ayam_mati" class="form-label">Ayam Mati (ekor)</label>
                                <input type="tel" class="form-control" id="ayam_mati" name="ayam_mati" value="0" autocomplete="off">
                            </div>
                            <div class="mb-3">
                                <label for="ayam_afkir" class="form-label">Ayam Afkir (ekor)</label>
                                <input type="tel" class="form-control" id="ayam_afkir" name="ayam_afkir" value="0" autocomplete="off">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <h5><i class="fas fa-egg"></i> Pakan & Produksi</h5>
                             <div class="mb-3">
                                <label for="pakan_terpakai_kg" class="form-label">Pakan Terpakai (kg)</label>
                                <input type="number" step="0.01" class="form-control" id="pakan_terpakai_kg" name="pakan_terpakai_kg" value="0.00">
                            </div>
                            <div class="mb-3">
                                <label for="telur_baik_kg" class="form-label">Telur Baik (kg)</label>
                                <input type="number" step="0.01" class="form-control" id="telur_baik_kg" name="telur_baik_kg" value="0.00">
                            </div>
                            <div class="mb-3">
                                <label for="telur_tipis_kg" class="form-label">Telur Tipis (kg)</label>
                                <input type="number" step="0.01" class="form-control" id="telur_tipis_kg" name="telur_tipis_kg" value="0.00">
                            </div>
                            <div class="mb-3">
                                <label for="telur_pecah_kg" class="form-label">Telur Pecah (kg)</label>
                                <input type="number" step="0.01" class="form-control" id="telur_pecah_kg" name="telur_pecah_kg" value="0.00">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <h5><i class="fas fa-dollar-sign"></i> Penjualan</h5>
                            <div class="mb-3">
                                <label for="telur_terjual_kg" class="form-label">Telur Terjual (kg)</label>
                                <input type="number" step="0.01" class="form-control" id="telur_terjual_kg" name="telur_terjual_kg" value="0.00">
                            </div>
                            <div class="mb-3">
                                <label for="harga_jual_rata2" class="form-label">Harga Jual Rata-rata (Rp/kg)</label>
                                <input type="tel" class="form-control" id="harga_jual_rata2" name="harga_jual_rata2" value="0" autocomplete="off">
                            </div>
                        </div>
                    </div>

                    <hr>
                    
                    <div class="row mt-3">
                        <div class="col-12">
                            <h5><i class="fas fa-money-bill-wave"></i> Pengeluaran (Opsional)</h5>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="gaji_harian" class="form-label">Gaji Harian (Rp)</label>
                            <input type="tel" class="form-control" id="gaji_harian" name="gaji_harian" value="0" autocomplete="off">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="gaji_bulanan" class="form-label">Gaji Bulanan (Rp)</label>
                            <input type="tel" class="form-control" id="gaji_bulanan" name="gaji_bulanan" value="0" autocomplete="off">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="obat_vitamin" class="form-label">Obat & Vitamin (Rp)</label>
                            <input type="tel" class="form-control" id="obat_vitamin" name="obat_vitamin" value="0" autocomplete="off">
                        </div>
                         <div class="col-md-3 mb-3">
                            <label for="lain_lain_operasional" class="form-label">Lain-Lain (Rp)</label>
                            <input type="tel" class="form-control" id="lain_lain_operasional" name="lain_lain_operasional" value="0" autocomplete="off">
                        </div>
                        <div class="col-12 mb-3">
                            <label for="keterangan_pengeluaran" class="form-label">Keterangan Pengeluaran</label>
                            <input type="text" class="form-control" id="keterangan_pengeluaran" name="keterangan_pengeluaran" placeholder="Contoh: Gaji Budi, Beli Vitachick, Perbaikan Pipa">
                        </div>
                    </div>
                    <button type="submit" id="submitButton" class="btn btn-primary w-100 mt-4">Simpan Laporan Harian</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php include '../templates/footer.php'; ?>

<script>
$(document).ready(function() {
    const kandangFilter = $('#id_kandang_filter');
    const tanggalFilter = $('#tanggal_filter');
    const mainFormContainer = $('#mainFormContainer');
    const summaryContainer = $('#summaryContainer');
    const formDisabledMessage = $('#formDisabledMessage');
    const submitButton = $('#submitButton');
    // Tambahkan selektor untuk semua input di dalam form utama
    const mainFormInputs = $('#mainFormContainer').find('input, select, button');

    function checkAndShowForm() {
        const kandangId = kandangFilter.val();
        const tanggal = tanggalFilter.val();

        $('#id_kandang_hidden').val(kandangId);
        $('#tanggal_hidden').val(tanggal);

        if (kandangId && tanggal) {
            mainFormContainer.slideDown(); 
            summaryContainer.css('display', 'flex'); 
            
            // Periksa ke server apakah sudah ada laporan di tanggal ini
            $.getJSON(`cek_laporan.php?id_kandang=${kandangId}&tanggal=${tanggal}`, function(response) {
                if (response.exists) {
                    formDisabledMessage.show();
                    submitButton.prop('disabled', true).text('Laporan Untuk Tanggal Ini Sudah Diinput');
                    mainFormInputs.not('#submitButton').prop('disabled', true); // Nonaktifkan semua input
                } else {
                    formDisabledMessage.hide();
                    submitButton.prop('disabled', false).text('Simpan Laporan Harian');
                    mainFormInputs.prop('disabled', false); // Aktifkan kembali semua input
                }
            });

            // Jalankan AJAX untuk summary
            $('#summary_total_ayam').text('Memuat...');
            $('#summary_total_telur').text('Memuat...');
            $('#summary_total_pakan_tersedia').text('Memuat...');
            $.getJSON(`get_kandang_summary.php?id_kandang=${kandangId}`, function(data) {
                if(data.error) {
                    $('#summary_total_ayam, #summary_total_telur, #summary_total_pakan_tersedia').text('Error');
                } else {
                    $('#summary_total_ayam').text(data.total_ayam);
                    $('#summary_total_telur').text(data.total_telur);
                    $('#summary_total_pakan_tersedia').text(data.total_pakan_tersedia);
                }
            });

        } else {
            mainFormContainer.slideUp(); 
        }
    }

    kandangFilter.on('change', checkAndShowForm);
    tanggalFilter.on('change', checkAndShowForm);

    // --- LOGIKA UNTUK FORMAT ANGKA OTOMATIS ---
    function formatNumberWithDots(input) {
        let value = $(input).val().replace(/[^0-9]/g, '');
        if (value === '' || value === null) { $(input).val(''); return; }
        $(input).val(new Intl.NumberFormat('id-ID').format(value));
    }
    const inputIdsToFormat = ['#ayam_masuk', '#ayam_mati', '#ayam_afkir', '#harga_jual_rata2', '#gaji_harian', '#gaji_bulanan', '#obat_vitamin', '#lain_lain_operasional'];
    $(inputIdsToFormat.join(', ')).on('keyup input', function() { formatNumberWithDots(this); });

    // --- LOGIKA UNTUK VALIDASI FORM BOOTSTRAP ---
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
});
</script>

</body>
</html>