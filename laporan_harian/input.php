<?php
include '../templates/header.php';

global $current_user_role, $current_assigned_kandang_id;

$kandang_query = "SELECT id_kandang, nama_kandang FROM kandang WHERE status = 'Aktif'";
if ($current_user_role === 'Karyawan' && $current_assigned_kandang_id) {
    $kandang_query .= " AND id_kandang = " . (int)$current_assigned_kandang_id;
}
$kandang_query .= " ORDER BY nama_kandang";
$kandang_list = $koneksi->query($kandang_query);

$pesan = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_kandang = $_POST['id_kandang'] ?? null;
    $tanggal = $_POST['tanggal'] ?? date('Y-m-d');

    if ($current_user_role === 'Karyawan' && $id_kandang != $current_assigned_kandang_id) {
        $pesan = "<div class='alert alert-danger'>Error: Anda tidak berhak menginput data untuk kandang ini.</div>";
    } else {

        $ayam_masuk = $_POST['ayam_masuk'] ? (int)str_replace('.', '', $_POST['ayam_masuk']) : 0;
        $ayam_mati = $_POST['ayam_mati'] ? (int)str_replace('.', '', $_POST['ayam_mati']) : 0;
        $ayam_afkir = $_POST['ayam_afkir'] ? (int)str_replace('.', '', $_POST['ayam_afkir']) : 0;
        $harga_jual_rata2 = $_POST['harga_jual_rata2'] ? (int)str_replace('.', '', $_POST['harga_jual_rata2']) : 0;
        $pakan_terpakai_kg = $_POST['pakan_terpakai_kg'] ? (float)str_replace(',', '.', $_POST['pakan_terpakai_kg']) : 0.0;
        $telur_baik_kg = $_POST['telur_baik_kg'] ? (float)str_replace(',', '.', $_POST['telur_baik_kg']) : 0.0;
        $telur_tipis_kg = $_POST['telur_tipis_kg'] ? (float)str_replace(',', '.', $_POST['telur_tipis_kg']) : 0.0;
        $telur_pecah_kg = $_POST['telur_pecah_kg'] ? (float)str_replace(',', '.', $_POST['telur_pecah_kg']) : 0.0;
        $telur_terjual_kg = $_POST['telur_terjual_kg'] ? (float)str_replace(',', '.', $_POST['telur_terjual_kg']) : 0.0;
        $pemasukan_telur = $telur_terjual_kg * $harga_jual_rata2;

        $stmt_check = $koneksi->prepare("SELECT id_laporan FROM laporan_harian WHERE id_kandang = ? AND tanggal = ?");
        $stmt_check->bind_param("is", $id_kandang, $tanggal);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();

        if ($result_check->num_rows > 0) {
            $pesan = "<div class='alert alert-danger'>Gagal menyimpan: Laporan untuk kandang dan tanggal ini sudah ada.</div>";
        } elseif (empty($id_kandang)) {
            $pesan = "<div class='alert alert-danger'>Gagal menyimpan laporan: Silakan pilih kandang.</div>";
        } else {
            $sql = "INSERT INTO laporan_harian (id_kandang, tanggal, ayam_masuk, ayam_mati, ayam_afkir, pakan_terpakai_kg, telur_baik_kg, telur_tipis_kg, telur_pecah_kg, telur_terjual_kg, harga_jual_rata2, pemasukan_telur) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $koneksi->prepare($sql);
            $stmt->bind_param("isiiidddddid", $id_kandang, $tanggal, $ayam_masuk, $ayam_mati, $ayam_afkir, $pakan_terpakai_kg, $telur_baik_kg, $telur_tipis_kg, $telur_pecah_kg, $telur_terjual_kg, $harga_jual_rata2, $pemasukan_telur);

            if ($stmt->execute()) {
                $pesan = "<div class='alert alert-success'>Laporan harian berhasil disimpan!</div>";
                //token device
                $token = "WX1rB3Sd-3!#gX2spohH";
                $curl = curl_init();

                curl_setopt_array($curl, array(
                    CURLOPT_URL => 'https://api.fonnte.com/send',
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => 'POST',
                    CURLOPT_POSTFIELDS => array(
                        'target' => '087748672761, ',
                        'message' => '12312312312312',
                    ),
                    CURLOPT_HTTPHEADER => array(
                        "Authorization: $token" //change TOKEN to your actual token
                    ),
                ));

                $response = curl_exec($curl);
                if (curl_errno($curl)) {
                    $error_msg = curl_error($curl);
                }
                curl_close($curl);

                if (isset($error_msg)) {
                    echo $error_msg;
                }
            } else {
                $pesan = "<div class='alert alert-danger'>Gagal menyimpan laporan: " . $stmt->error . "</div>";
            }
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
                    <select class="form-select" id="id_kandang_filter" name="id_kandang_filter" required <?php echo ($current_user_role === 'Karyawan') ? 'disabled' : ''; ?>>
                        <?php if ($current_user_role === 'Pimpinan'): ?>
                            <option value="" disabled selected>-- Pilih Kandang --</option>
                        <?php endif; ?>

                        <?php if ($kandang_list && $kandang_list->num_rows > 0): ?>
                            <?php while ($k = $kandang_list->fetch_assoc()): ?>
                                <option value="<?php echo $k['id_kandang']; ?>" <?php echo ($current_user_role === 'Karyawan') ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($k['nama_kandang']); ?>
                                </option>
                            <?php endwhile; ?>
                        <?php elseif ($current_user_role === 'Pimpinan'): ?>
                            <option value="" disabled>Belum ada kandang aktif</option>
                        <?php endif; ?>
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
                    <strong><i class="fas fa-crow"></i> Sisa Ayam:</strong>
                    <span id="summary_total_ayam">Memuat...</span> Ekor
                </div>
                <div class="col-md-4">
                    <strong><i class="fas fa-egg"></i> Sisa Telur:</strong>
                    <span id="summary_total_telur">Memuat...</span> Kg
                </div>
                <div class="col-md-4">
                    <strong><i class="fas fa-boxes"></i> Sisa Pakan (Kandang Ini):</strong>
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
                                <input type="tel" class="form-control clear-on-focus format-number" id="ayam_masuk" name="ayam_masuk" value="0" autocomplete="off">
                            </div>
                            <div class="mb-3">
                                <label for="ayam_mati" class="form-label">Ayam Mati (ekor)</label>
                                <input type="tel" class="form-control clear-on-focus format-number" id="ayam_mati" name="ayam_mati" value="0" autocomplete="off">
                            </div>
                            <div class="mb-3">
                                <label for="ayam_afkir" class="form-label">Ayam Afkir (ekor)</label>
                                <input type="tel" class="form-control clear-on-focus format-number" id="ayam_afkir" name="ayam_afkir" value="0" autocomplete="off">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <h5><i class="fas fa-seedling"></i> Pakan & Produksi</h5>
                            <div class="mb-3">
                                <label for="harga_pakan_terbaru" class="form-label">Harga Pakan/Kg (Terbaru)</label>
                                <input type="text" class="form-control" id="harga_pakan_terbaru" name="harga_pakan_terbaru" value="Rp 0" readonly disabled>
                            </div>
                            <div class="mb-3">
                                <label for="pakan_terpakai_kg" class="form-label">Pakan Terpakai (kg)</label>
                                <input type="number" step="0.01" class="form-control clear-on-focus" id="pakan_terpakai_kg" name="pakan_terpakai_kg" value="0.00">
                            </div>
                            <div class="mb-3">
                                <label for="telur_baik_kg" class="form-label">Telur Baik (kg)</label>
                                <input type="number" step="0.01" class="form-control clear-on-focus" id="telur_baik_kg" name="telur_baik_kg" value="0.00">
                            </div>
                            <div class="mb-3">
                                <label for="telur_tipis_kg" class="form-label">Telur Tipis (kg)</label>
                                <input type="number" step="0.01" class="form-control clear-on-focus" id="telur_tipis_kg" name="telur_tipis_kg" value="0.00">
                            </div>
                            <div class="mb-3">
                                <label for="telur_pecah_kg" class="form-label">Telur Pecah (kg)</label>
                                <input type="number" step="0.01" class="form-control clear-on-focus" id="telur_pecah_kg" name="telur_pecah_kg" value="0.00">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <h5><i class="fas fa-dollar-sign"></i> Penjualan</h5>
                            <div class="mb-3">
                                <label for="telur_terjual_kg" class="form-label">Telur Terjual (kg)</label>
                                <input type="number" step="0.01" class="form-control clear-on-focus" id="telur_terjual_kg" name="telur_terjual_kg" value="0.00">
                            </div>
                            <div class="mb-3">
                                <label for="harga_jual_rata2" class="form-label">Harga Jual Rata-rata (Rp/kg)</label>
                                <input type="tel" class="form-control clear-on-focus format-number" id="harga_jual_rata2" name="harga_jual_rata2" value="0" autocomplete="off">
                            </div>
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
        const mainFormInputs = $('#mainFormContainer').find('input, select, button');
        const hargaPakanInput = $('#harga_pakan_terbaru');

        function checkAndShowForm() {
            const kandangId = kandangFilter.val();
            const tanggal = tanggalFilter.val();

            $('#id_kandang_hidden').val(kandangId);
            $('#tanggal_hidden').val(tanggal);

            hargaPakanInput.val('Memuat...');

            if (kandangId && tanggal) {
                mainFormContainer.slideDown();
                summaryContainer.css('display', 'flex');

                $.getJSON(`cek_laporan.php?id_kandang=${kandangId}&tanggal=${tanggal}`, function(response) {
                    if (response.exists) {
                        formDisabledMessage.show();
                        submitButton.prop('disabled', true).text('Laporan Untuk Tanggal Ini Sudah Diinput');
                        mainFormInputs.not('#submitButton').prop('disabled', true);
                        $.getJSON(`get_harga_pakan.php?id_kandang=${kandangId}&tanggal=${tanggal}`, function(hargaResponse) {
                            hargaPakanInput.val(hargaResponse.harga_pakan || 'Rp 0');
                        });
                    } else {
                        formDisabledMessage.hide();
                        submitButton.prop('disabled', false).text('Simpan Laporan Harian');
                        mainFormInputs.prop('disabled', false);
                        $.getJSON(`get_harga_pakan.php?id_kandang=${kandangId}&tanggal=${tanggal}`, function(hargaResponse) {
                            hargaPakanInput.val(hargaResponse.harga_pakan || 'Rp 0');
                        });
                    }
                });

                $('#summary_total_ayam, #summary_total_telur, #summary_total_pakan_tersedia').text('Memuat...');
                $.getJSON(`get_kandang_summary.php?id_kandang=${kandangId}`, function(data) {
                    if (data.error) {
                        $('#summary_total_ayam, #summary_total_telur, #summary_total_pakan_tersedia').text('Error');
                    } else {
                        $('#summary_total_ayam').text(data.total_ayam);
                        $('#summary_total_telur').text(data.total_telur);
                        $('#summary_total_pakan_tersedia').text(data.total_pakan_tersedia);
                    }
                });

            } else {
                mainFormContainer.slideUp();
                hargaPakanInput.val('Rp 0');
            }
        }

        kandangFilter.on('change', checkAndShowForm);
        tanggalFilter.on('change', checkAndShowForm);

        $('.clear-on-focus').on('focus', function() {
            if ($(this).val() == '0' || $(this).val() == '0.00') {
                $(this).val('');
            }
        });
        $('.clear-on-focus').on('blur', function() {
            if ($(this).val() === '') {
                if ($(this).attr('step') && $(this).attr('step').indexOf('.') !== -1) {
                    $(this).val('0.00');
                } else {
                    $(this).val('0');
                }
            }
            if ($(this).hasClass('format-number') && $(this).val() !== '0') {
                formatNumberWithDots(this);
            }
        });

        function formatNumberWithDots(input) {
            let value = $(input).val().replace(/[^0-9]/g, '');
            if (value === '' || value === null) {
                if (!$(input).is(':focus')) {
                    $(input).val('0');
                } else {
                    $(input).val('');
                }
                return;
            }
            $(input).val(new Intl.NumberFormat('id-ID').format(value));
        }

        $('.format-number').on('keyup input', function() {
            formatNumberWithDots(this);
        });

        const forms = document.querySelectorAll('.needs-validation');
        Array.from(forms).forEach(form => {
            form.addEventListener('submit', event => {
                $(form).find('.format-number').each(function() {
                    $(this).val($(this).val().replace(/\./g, ''));
                });
                $(form).find('input[step*="."]').each(function() {
                    $(this).val($(this).val().replace(',', '.'));
                });

                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');

                setTimeout(() => {
                    $(form).find('.format-number').each(function() {
                        formatNumberWithDots(this);
                    });
                }, 100);
            }, false);
        });

        <?php if ($current_user_role === 'Karyawan'): ?>
            checkAndShowForm();
        <?php endif; ?>
    });
</script>

</body>

</html>