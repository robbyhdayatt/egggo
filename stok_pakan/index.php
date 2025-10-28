<?php
include '../templates/header.php';
global $koneksi, $current_user_role, $current_assigned_kandang_id;

$pesan = '';
if (isset($_GET['status'])) {
    if ($_GET['status'] == 'sukses_tambah') {
        $pesan = "<div class='alert alert-success mt-3'>Data pembelian pakan berhasil disimpan!</div>";
    } elseif ($_GET['status'] == 'sukses_update') {
        $pesan = "<div class='alert alert-success mt-3'>Data pembelian pakan berhasil diperbarui!</div>";
    } elseif ($_GET['status'] == 'sukses_hapus') {
        $pesan = "<div class='alert alert-success mt-3'>Data pembelian pakan berhasil dihapus!</div>";
    } elseif ($_GET['status'] == 'error') {
        $msg = $_GET['msg'] ?? 'Terjadi kesalahan.';
        $pesan = "<div class='alert alert-danger mt-3'>Error: " . htmlspecialchars($msg) . "</div>";
    }
}

// --- Query Kandang (Aktif) ---
$kandang_options = [];
$kandang_query = "SELECT id_kandang, nama_kandang FROM kandang WHERE status = 'Aktif'";
if ($current_user_role === 'Karyawan' && $current_assigned_kandang_id) {
    $kandang_query .= " AND id_kandang = " . (int)$current_assigned_kandang_id;
}
$kandang_query .= " ORDER BY nama_kandang";
$kandang_list_result = $koneksi->query($kandang_query);
if ($kandang_list_result) {
    while ($k = $kandang_list_result->fetch_assoc()) {
        $kandang_options[] = $k;
    }
}

// --- Logika Tambah Stok (POST) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah_stok'])) {
    $id_kandang = $_POST['id_kandang'] ?? null;
    if ($current_user_role === 'Karyawan' && $current_assigned_kandang_id) {
         $id_kandang = $current_assigned_kandang_id;
    }
    $tanggal_beli = $_POST['tanggal_beli'];
    $nama_pakan = trim($_POST['nama_pakan']);
    $jumlah_kg_str = str_replace('.', '', $_POST['jumlah_kg'] ?? '0');
    $jumlah_kg = (float)str_replace(',', '.', $jumlah_kg_str);
    $harga_per_kg_str = str_replace('.', '', $_POST['harga_per_kg'] ?? '0');
    $harga_per_kg = (float)$harga_per_kg_str;
    $harga_total = round($jumlah_kg * $harga_per_kg); // Hitung ulang di server

    if ($current_user_role === 'Karyawan' && $id_kandang != $current_assigned_kandang_id) {
        $pesan = "<div class='alert alert-danger mt-3'>Error: Anda tidak berhak menginput data untuk kandang ini.</div>";
    } elseif (empty($id_kandang) || empty($nama_pakan) || $jumlah_kg <= 0 || $harga_per_kg < 0) {
        $pesan = "<div class='alert alert-danger mt-3'>Error: Pastikan Kandang, Nama Pakan, Jumlah (lebih dari 0), dan Harga per Kg (minimal 0) terisi dengan benar.</div>";
    } else {
        $stmt = $koneksi->prepare("INSERT INTO stok_pakan (id_kandang, tanggal_beli, nama_pakan, jumlah_kg, harga_per_kg, harga_total) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("issddd", $id_kandang, $tanggal_beli, $nama_pakan, $jumlah_kg, $harga_per_kg, $harga_total);
            if ($stmt->execute()) {
                header('Location: index.php?status=sukses_tambah');
                exit();
            } else {
                $pesan = "<div class='alert alert-danger mt-3'>Gagal menyimpan data: " . $stmt->error . "</div>";
            }
            $stmt->close();
        } else {
            $pesan = "<div class='alert alert-danger mt-3'>Gagal menyiapkan statement: " . $koneksi->error . "</div>";
        }
    }
}

// --- Query Data Stok Pakan ---
$stok_data = []; // Simpan data di array dulu
$stok_query = "
    SELECT sp.*, k.nama_kandang
    FROM stok_pakan sp
    JOIN kandang k ON sp.id_kandang = k.id_kandang
";
$where_clauses = [];
if ($current_user_role === 'Karyawan' && $current_assigned_kandang_id) {
    $where_clauses[] = "sp.id_kandang = " . (int)$current_assigned_kandang_id;
}
if (!empty($where_clauses)) {
    $stok_query .= " WHERE " . implode(' AND ', $where_clauses);
}
$stok_query .= " ORDER BY sp.tanggal_beli DESC, sp.id_stok DESC";
$stok_result = $koneksi->query($stok_query);
if($stok_result) {
    while($row = $stok_result->fetch_assoc()) {
        $stok_data[] = $row;
    }
}

// Tentukan jumlah kolom berdasarkan role
$is_pimpinan = ($current_user_role === 'Pimpinan');
$total_kolom = $is_pimpinan ? 7 : 6;
$aksi_kolom_index = $total_kolom - 1; // Index kolom terakhir (dimulai dari 0)

?>

<div class="container-fluid">
    <div class="page-header">
        <h1 class="page-title">Stok Pakan per Kandang</h1>
        <p class="page-subtitle">Catat dan kelola riwayat pembelian pakan untuk setiap kandang.</p>
    </div>
    <?php echo $pesan; ?>
    <div class="row">
        <div class="col-12 mb-4">
            <h4><i class="fas fa-plus-circle"></i> Input Stok Pakan Baru</h4>
            <div class="card">
                <div class="card-body">
                    <form id="formTambahStok" method="POST" class="needs-validation" novalidate>
                        <input type="hidden" name="tambah_stok" value="1">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="id_kandang" class="form-label">Untuk Kandang <span class="text-danger">*</span></label>
                                <select class="form-select" id="id_kandang" name="id_kandang" required <?php echo (!$is_pimpinan) ? 'disabled' : ''; ?>>
                                    <?php if ($is_pimpinan): ?>
                                        <option value="" disabled selected>-- Pilih Kandang --</option>
                                    <?php endif; ?>
                                    <?php foreach ($kandang_options as $k) : ?>
                                        <option value="<?php echo $k['id_kandang']; ?>" <?php echo (!$is_pimpinan && $k['id_kandang'] == $current_assigned_kandang_id) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($k['nama_kandang']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <?php if (empty($kandang_options) && $is_pimpinan): ?>
                                         <option value="" disabled>Belum ada kandang aktif</option>
                                    <?php endif; ?>
                                </select>
                                <div class="invalid-feedback">Silakan pilih kandang.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="tanggal_beli" class="form-label">Tanggal Beli <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="tanggal_beli" name="tanggal_beli" value="<?php echo date('Y-m-d'); ?>" required max="<?php echo date('Y-m-d'); ?>">
                                <div class="invalid-feedback">Tanggal beli tidak boleh kosong dan tidak boleh melebihi hari ini.</div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="nama_pakan" class="form-label">Nama/Merk Pakan <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="nama_pakan" name="nama_pakan" required>
                                <div class="invalid-feedback">Nama pakan tidak boleh kosong.</div>
                            </div>
                            <div class="col-md-2 mb-3">
                                <label for="jumlah_kg" class="form-label">Jumlah (kg) <span class="text-danger">*</span></label>
                                <input type="text" inputmode="decimal" class="form-control format-decimal text-end" id="jumlah_kg" name="jumlah_kg" required placeholder="0,00" value="0,00">
                                <div class="invalid-feedback">Jumlah wajib diisi (gunakan koma untuk desimal).</div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="harga_per_kg" class="form-label">Harga per Kg (Rp) <span class="text-danger">*</span></label>
                                <input type="text" inputmode="numeric" class="form-control format-number text-end" id="harga_per_kg" name="harga_per_kg" required placeholder="0" autocomplete="off" value="0">
                                <div class="invalid-feedback">Harga per kg wajib diisi.</div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="harga_total" class="form-label">Harga Total (Rp)</label>
                                <input type="text" inputmode="numeric" class="form-control format-number text-end" id="harga_total" name="harga_total" placeholder="0" autocomplete="off" value="0" readonly>
                            </div>
                        </div>
                        <button type="submit" id="submitButton" class="btn btn-primary">Simpan Stok</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12">
            <h4><i class="fas fa-list-alt"></i> Riwayat Pembelian Pakan</h4>
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover display compact" id="tabelStokPakan" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <?php if ($is_pimpinan): ?>
                                        <th>Nama Kandang</th>
                                    <?php endif; ?>
                                    <th>Nama Pakan</th>
                                    <th class="text-end">Jumlah (kg)</th> <th class="text-end">Harga per Kg</th> <th class="text-end">Harga Total</th> <th class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($stok_data)): ?>
                                    <?php foreach ($stok_data as $row): ?>
                                        <tr>
                                            <td><?php echo date('d M Y', strtotime($row['tanggal_beli'])); ?></td>
                                            <?php if ($is_pimpinan): ?>
                                                <td><?php echo htmlspecialchars($row['nama_kandang']); ?></td>
                                            <?php endif; ?>
                                            <td><?php echo htmlspecialchars($row['nama_pakan']); ?></td>
                                            <td class="text-end"><?php echo number_format($row['jumlah_kg'], 2, ',', '.'); ?></td>
                                            <td class="text-end">Rp <?php echo number_format($row['harga_per_kg'] ?? 0, 0, ',', '.'); ?></td>
                                            <td class="text-end">Rp <?php echo number_format($row['harga_total'], 0, ',', '.'); ?></td>
                                            <td class="text-center">
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn btn-warning btn-edit"
                                                        data-bs-toggle="modal" data-bs-target="#editModal"
                                                        data-id="<?php echo $row['id_stok']; ?>"
                                                        data-id-kandang="<?php echo $row['id_kandang']; ?>"
                                                        data-tanggal="<?php echo $row['tanggal_beli']; ?>"
                                                        data-nama-pakan="<?php echo htmlspecialchars($row['nama_pakan']); ?>"
                                                        data-jumlah="<?php echo $row['jumlah_kg']; ?>"
                                                        data-harga-per-kg="<?php echo $row['harga_per_kg'] ?? 0; ?>"
                                                        data-bs-toggle-tooltip="tooltip" title="Edit Data">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <a href="hapus.php?id=<?php echo $row['id_stok']; ?>" class="btn btn-danger btn-hapus" data-bs-toggle-tooltip="tooltip" title="Hapus Data">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editModalLabel">Edit Stok Pakan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formEditStok" action="proses_update.php" method="POST" class="needs-validation" novalidate>
                 <div class="modal-body">
                    <input type="hidden" id="edit_id_stok" name="id_stok">
                     <?php if (!$is_pimpinan && $current_assigned_kandang_id): ?>
                         <input type="hidden" name="id_kandang" value="<?php echo $current_assigned_kandang_id; ?>" />
                     <?php endif; ?>

                    <div class="mb-3">
                        <label for="edit_id_kandang" class="form-label">Untuk Kandang <span class="text-danger">*</span></label>
                        <select class="form-select" id="edit_id_kandang" name="id_kandang" required <?php echo (!$is_pimpinan) ? 'disabled' : ''; ?>>
                             <?php if ($is_pimpinan): ?>
                                 <option value="" disabled>-- Pilih Kandang --</option>
                             <?php endif; ?>
                             <?php foreach ($kandang_options as $k) : ?>
                                 <option value="<?php echo $k['id_kandang']; ?>"><?php echo htmlspecialchars($k['nama_kandang']); ?></option>
                             <?php endforeach; ?>
                             <?php if (empty($kandang_options) && $is_pimpinan): ?>
                                 <option value="" disabled>Belum ada kandang aktif</option>
                             <?php endif; ?>
                        </select>
                        <div class="invalid-feedback">Silakan pilih kandang.</div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_tanggal_beli" class="form-label">Tanggal Beli <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="edit_tanggal_beli" name="tanggal_beli" required max="<?php echo date('Y-m-d'); ?>">
                        <div class="invalid-feedback">Tanggal beli tidak boleh kosong dan tidak boleh melebihi hari ini.</div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_nama_pakan" class="form-label">Nama/Merk Pakan <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_nama_pakan" name="nama_pakan" required>
                        <div class="invalid-feedback">Nama pakan tidak boleh kosong.</div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_jumlah_kg" class="form-label">Jumlah (kg) <span class="text-danger">*</span></label>
                        <input type="text" inputmode="decimal" class="form-control format-decimal text-end" id="edit_jumlah_kg" name="jumlah_kg" required placeholder="0,00">
                        <div class="invalid-feedback">Jumlah wajib diisi (gunakan koma untuk desimal).</div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_harga_per_kg" class="form-label">Harga per Kg (Rp) <span class="text-danger">*</span></label>
                        <input type="text" inputmode="numeric" class="form-control format-number text-end" id="edit_harga_per_kg" name="harga_per_kg" required placeholder="0" autocomplete="off">
                        <div class="invalid-feedback">Harga per kg wajib diisi.</div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_harga_total" class="form-label">Harga Total (Rp)</label>
                        <input type="text" inputmode="numeric" class="form-control format-number text-end" id="edit_harga_total" name="harga_total" placeholder="0" autocomplete="off" readonly>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../templates/footer.php'; ?>

<script>
    $(document).ready(function() {
        console.log("Document ready. Initializing DataTables...");
        // --- Inisialisasi DataTables ---
        const nonSortableColumnTarget = <?php echo $aksi_kolom_index; ?>;
        try {
            $('#tabelStokPakan').DataTable({
                "language": {
                    "url": "https://cdn.datatables.net/plug-ins/1.13.7/i18n/id.json",
                    "emptyTable": "Tidak ada data riwayat pembelian pakan."
                },
                "order": [
                    [0, "desc"] // Urutkan berdasarkan kolom pertama (Tanggal) descending
                ],
                "columnDefs": [
                    { "orderable": false, "targets": nonSortableColumnTarget } // Nonaktifkan sorting untuk kolom 'Aksi'
                ]
            });
            console.log("DataTables initialized successfully.");
        } catch (e) {
            console.error("Error initializing DataTables:", e);
            // Tampilkan pesan error kepada pengguna jika perlu
            // $('#tabelStokPakan').before('<div class="alert alert-danger">Gagal memuat tabel data. Error: ' + e.message + '</div>');
        }


        // --- Inisialisasi Tooltip ---
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle-tooltip="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
            return tooltipTriggerEl ? new bootstrap.Tooltip(tooltipTriggerEl) : null;
        });

        // --- Fungsi Helper Format & Unformat Angka ---

        // Format angka ribuan (Rp) -> 1.234
        function formatNumber(element) {
            let value = $(element).val().replace(/[^0-9]/g, '');
            // Hanya format jika value tidak kosong
             $(element).val(value === '' ? '' : new Intl.NumberFormat('id-ID').format(parseInt(value, 10)));
         }


        // Unformat angka ribuan (Rp) -> 1234 (return number)
        function unformatNumber(value) {
            if (typeof value !== 'string') value = String(value);
            const num = parseInt(value.replace(/\./g, ''), 10); // Hapus titik, parse ke int
            return isNaN(num) ? 0 : num; // Kembalikan 0 jika NaN
        }

        // Format angka desimal (Kg) -> 1.234,56
        function formatDecimal(element) {
            let value = $(element).val().replace(/[^0-9,]/g, ''); // Hanya angka dan koma
             let parts = value.split(',');
             let integerPart = parts[0].replace(/\./g, ''); // Hapus titik ribuan dari bagian integer
             let decimalPart = parts.length > 1 ? parts[1] : '';

             // Jika integer kosong dan desimal kosong, biarkan kosong
             if (integerPart === '' && decimalPart === '') {
                 $(element).val('');
                 return;
             }

             integerPart = integerPart === '' ? '0' : integerPart;
             decimalPart = (decimalPart + '00').substring(0, 2); // Pastikan 2 desimal

             // Format bagian integer dengan titik ribuan
             let formattedInteger = new Intl.NumberFormat('id-ID').format(parseInt(integerPart, 10));

             $(element).val(formattedInteger + ',' + decimalPart);
         }


        // Unformat angka desimal (Kg) -> 1234.56 (return number)
        function unformatDecimal(value) {
            if (typeof value !== 'string') value = String(value);
            // Hapus titik ribuan, ganti koma desimal jadi titik
            const num = parseFloat(value.replace(/\./g, '').replace(',', '.'));
            return isNaN(num) ? 0.0 : num; // Kembalikan 0.0 jika NaN
        }


        // --- Fungsi Kalkulasi Total ---
        function calculateTotal(jumlahKgSelector, hargaPerKgSelector, hargaTotalSelector) {
            const jumlahKg = unformatDecimal($(jumlahKgSelector).val());
            const hargaPerKg = unformatNumber($(hargaPerKgSelector).val());
            const total = Math.round(jumlahKg * hargaPerKg);

            console.log(`Calculating Total: Jumlah=${jumlahKg}, Harga/Kg=${hargaPerKg}, Total=${total}`); // DEBUG

            const totalInput = $(hargaTotalSelector);
            totalInput.val(total); // Set nilai numerik dulu
            formatNumber(totalInput); // Baru format tampilan
        }


        // --- Event Listeners untuk Formatting Input ---
        $(document).on('input', '.format-number', function() { formatNumber(this); });
        $(document).on('input', '.format-decimal', function() { formatDecimal(this); });

        $(document).on('blur', '.format-number', function() {
            if ($(this).val() === '') $(this).val('0'); // Set default 0 jika kosong saat blur
            formatNumber(this);
        });
        $(document).on('blur', '.format-decimal', function() {
             // Set default 0,00 jika kosong atau hanya koma saat blur
            if ($(this).val() === '' || $(this).val() === ',') $(this).val('0,00');
            formatDecimal(this);
        });

        $(document).on('focus', '.format-number', function() {
            if ($(this).val() === '0') $(this).val(''); // Hapus 0 saat fokus
        });
        $(document).on('focus', '.format-decimal', function() {
             if ($(this).val() === '0,00') $(this).val(''); // Hapus 0,00 saat fokus
             // Jika hanya koma, hapus juga
             if ($(this).val() === ',') $(this).val('');
        });


        // --- Event Listeners untuk Kalkulasi ---
        // Panggil calculateTotal setiap kali input jumlah atau harga/kg berubah (input) atau diformat ulang (blur)
        $('#formTambahStok').on('input blur', '#jumlah_kg, #harga_per_kg', function() {
            calculateTotal('#jumlah_kg', '#harga_per_kg', '#harga_total');
        });
        // Pakai event delegation untuk modal
        $(document).on('input blur', '#edit_jumlah_kg, #edit_harga_per_kg', function() {
             // Pastikan ini di dalam modal edit
             if ($(this).closest('#formEditStok').length) {
                calculateTotal('#edit_jumlah_kg', '#edit_harga_per_kg', '#edit_harga_total');
             }
        });


        // --- Logika Modal Edit ---
        const editModalEl = document.getElementById('editModal');
        if (editModalEl) {
            editModalEl.addEventListener('show.bs.modal', function(event) {
                console.log("Modal edit opened."); // DEBUG
                const button = event.relatedTarget;
                const dataset = button.dataset;

                // Isi form modal
                $('#edit_id_stok').val(dataset.id);
                $('#edit_id_kandang').val(dataset.idKandang || ""); // Set selected kandang
                $('#edit_tanggal_beli').val(dataset.tanggal);
                $('#edit_nama_pakan').val(dataset.namaPakan);

                // Set nilai awal dan format untuk Jumlah & Harga/Kg
                const jumlahInput = $('#edit_jumlah_kg');
                // Set nilai dengan koma sebagai pemisah desimal jika ada
                let jumlahVal = parseFloat(dataset.jumlah || 0).toFixed(2).replace('.', ',');
                jumlahInput.val(jumlahVal);
                formatDecimal(jumlahInput); // Format setelah set value

                const hargaKgInput = $('#edit_harga_per_kg');
                hargaKgInput.val(parseInt(dataset.hargaPerKg || 0)); // Set nilai integer
                formatNumber(hargaKgInput); // Format setelah set value

                // Kalkulasi total awal saat modal dibuka
                calculateTotal('#edit_jumlah_kg', '#edit_harga_per_kg', '#edit_harga_total');

                // Reset validasi Bootstrap
                $(editModalEl).find('form').removeClass('was-validated');
            });
        }

        // --- Konfirmasi Hapus ---
        $('#tabelStokPakan tbody').on('click', '.btn-hapus', function(e) {
            e.preventDefault();
            const href = $(this).attr('href');
            Swal.fire({ /* ... Konfirmasi SweetAlert ... */
                title: 'Anda yakin?',
                text: "Data ini akan dihapus permanen!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonText: 'Batal',
                confirmButtonText: 'Ya, hapus!'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = href;
                }
            });
        });

        // --- Validasi Bootstrap & Unformat sebelum Submit ---
        const forms = document.querySelectorAll('.needs-validation');
        Array.from(forms).forEach(form => {
            form.addEventListener('submit', event => {
                // Simpan referensi ke input sebelum di-unformat
                const numbersToReformat = $(form).find('.format-number');
                const decimalsToReformat = $(form).find('.format-decimal');

                // Unformat angka SEBELUM checkValidity
                numbersToReformat.each(function() {
                    if (!$(this).prop('readonly')) { // Jangan unformat readonly (Harga Total)
                        $(this).val(unformatNumber($(this).val()));
                    }
                });
                decimalsToReformat.each(function() {
                    let unformattedVal = unformatDecimal($(this).val());
                    // Pastikan > 0 jika field jumlah_kg
                    if ($(this).attr('name') === 'jumlah_kg' && unformattedVal <= 0) {
                        // Jika jumlah 0 atau kurang, set invalid manual (opsional, tergantung validasi server)
                        // this.setCustomValidity('Jumlah harus lebih dari 0'); // -> Ini akan menghentikan submit jika browser support
                        // Atau biarkan server yang validasi, tapi pastikan nilai 0.00 terkirim jika kosong
                        if ($(this).val() === '' || $(this).val() === '0') {
                           $(this).val('0.00'); // Kirim 0.00 jika memang kosong
                        } else {
                           $(this).val(unformattedVal.toFixed(2)); // Kirim angka asli
                        }
                    } else {
                         $(this).val(unformattedVal.toFixed(2)); // Kirim angka asli dengan 2 desimal
                    }
                });


                if (!form.checkValidity()) {
                    console.log("Form validation failed."); // DEBUG
                    event.preventDefault();
                    event.stopPropagation();
                    // Kembalikan format SEGERA jika validasi gagal
                     numbersToReformat.each(function() { formatNumber(this); });
                     decimalsToReformat.each(function() { formatDecimal(this); });
                } else {
                    console.log("Form validation passed. Submitting..."); // DEBUG
                }

                form.classList.add('was-validated');

                // SELALU kembalikan format setelah jeda singkat, baik submit berhasil/gagal
                 setTimeout(() => {
                    console.log("Reformatting inputs after submit attempt."); // DEBUG
                    numbersToReformat.each(function() { formatNumber(this); });
                    decimalsToReformat.each(function() { formatDecimal(this); });
                 }, 200); // Beri sedikit lebih banyak waktu

            }, false);
        });

         // Panggil format awal saat halaman dimuat
         $('.format-number').each(function() { formatNumber(this); });
         $('.format-decimal').each(function() { formatDecimal(this); });

         // Trigger kalkulasi awal untuk form tambah (jika ada nilai default selain 0)
         calculateTotal('#jumlah_kg', '#harga_per_kg', '#harga_total');


    }); // End $(document).ready()
</script>

</body>
</html>