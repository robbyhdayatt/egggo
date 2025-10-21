<?php
include '../templates/header.php';
// Ambil variabel role global dari header.php
global $current_user_role, $current_assigned_kandang_id;

$pesan = '';
if (isset($_GET['status'])) {
    if ($_GET['status'] == 'sukses_tambah') {
        $pesan = "<div class='alert alert-success mt-3'>Data pembelian pakan berhasil disimpan!</div>";
    } elseif ($_GET['status'] == 'sukses_update') {
        $pesan = "<div class='alert alert-success mt-3'>Data pembelian pakan berhasil diperbarui!</div>";
    } elseif ($_GET['status'] == 'sukses_hapus') {
        $pesan = "<div class='alert alert-success mt-3'>Data pembelian pakan berhasil dihapus!</div>";
    } elseif ($_GET['status'] == 'error') { // Tambahkan penanganan error umum
        $msg = $_GET['msg'] ?? 'Terjadi kesalahan.';
        $pesan = "<div class='alert alert-danger mt-3'>Error: " . htmlspecialchars($msg) . "</div>";
    }
}

// --- Query Kandang (Aktif) ---
$kandang_query = "SELECT id_kandang, nama_kandang FROM kandang WHERE status = 'Aktif'";
if ($current_user_role === 'Karyawan' && $current_assigned_kandang_id) {
    $kandang_query .= " AND id_kandang = " . (int)$current_assigned_kandang_id;
}
$kandang_query .= " ORDER BY nama_kandang";
$kandang_list = $koneksi->query($kandang_query);
// --- Akhir Query Kandang ---


// --- Logika Tambah Stok (POST) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah_stok'])) {
    $id_kandang = $_POST['id_kandang'];
    $tanggal_beli = $_POST['tanggal_beli'];
    $nama_pakan = trim($_POST['nama_pakan']); // Trim input text
    // Konversi jumlah_kg dari format IDN (koma desimal) ke float
    $jumlah_kg_raw = str_replace('.', '', $_POST['jumlah_kg'] ?? '0'); // Hapus titik ribuan
    $jumlah_kg = (float)str_replace(',', '.', $jumlah_kg_raw); // Ganti koma desimal jadi titik
    // Hapus format ribuan dari harga
    $harga_per_kg = (float)str_replace('.', '', $_POST['harga_per_kg'] ?? '0');
    $harga_total = (float)str_replace('.', '', $_POST['harga_total'] ?? '0');

    // Validasi Hak Akses
    if ($current_user_role === 'Karyawan' && $id_kandang != $current_assigned_kandang_id) {
        $pesan = "<div class='alert alert-danger mt-3'>Error: Anda tidak berhak menginput data untuk kandang ini.</div>";
    }
    // Validasi Input Dasar
    elseif (empty($nama_pakan) || $jumlah_kg <= 0 || $harga_per_kg < 0 || $harga_total < 0) {
        $pesan = "<div class='alert alert-danger mt-3'>Error: Pastikan semua field terisi dengan benar (jumlah harus lebih dari 0).</div>";
    } else {
        // Validasi duplikat (opsional, tergantung kebutuhan bisnis apakah boleh input >1x per hari)
        // $stmt_check = $koneksi->prepare("SELECT id_stok FROM stok_pakan WHERE id_kandang = ? AND tanggal_beli = ?");
        // $stmt_check->bind_param("is", $id_kandang, $tanggal_beli);
        // $stmt_check->execute();
        // $result_check = $stmt_check->get_result();
        // if ($result_check->num_rows > 0) {
        //     $pesan = "<div class='alert alert-danger mt-3'>Gagal menyimpan: Data untuk kandang dan tanggal ini sudah ada.</div>";
        // } else {
        $stmt = $koneksi->prepare("INSERT INTO stok_pakan (id_kandang, tanggal_beli, nama_pakan, jumlah_kg, harga_per_kg, harga_total) VALUES (?, ?, ?, ?, ?, ?)");
        // Gunakan tipe data 'd' (double) untuk jumlah_kg, harga_per_kg, dan harga_total
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
        // } // End else duplicate check
    } // End else validasi akses & input
} // End POST tambah_stok

// --- Query Data Stok Pakan ---
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
                        <?php if ($current_user_role === 'Karyawan' && $current_assigned_kandang_id): ?>
                            <input type="hidden" name="id_kandang" value="<?php echo $current_assigned_kandang_id; ?>" />
                        <?php endif; ?>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="id_kandang" class="form-label">Untuk Kandang</label>
                                <select class="form-select filter-check" id="id_kandang" name="id_kandang" required <?php echo ($current_user_role === 'Karyawan') ? 'disabled' : ''; ?>>
                                    <?php if ($current_user_role === 'Pimpinan'): ?>
                                        <option value="" disabled selected>-- Pilih Kandang --</option>
                                    <?php endif; ?>
                                    <?php
                                    if ($kandang_list) mysqli_data_seek($kandang_list, 0);
                                    while ($k = $kandang_list->fetch_assoc()) :
                                    ?>
                                        <option value="<?php echo $k['id_kandang']; ?>" <?php echo ($current_user_role === 'Karyawan' || (isset($id_kandang) && $k['id_kandang'] == $id_kandang)) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($k['nama_kandang']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                                <div class="invalid-feedback">Silakan pilih kandang.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="tanggal_beli" class="form-label">Tanggal Beli</label>
                                <input type="date" class="form-control filter-check" id="tanggal_beli" name="tanggal_beli" value="<?php echo date('Y-m-d'); ?>" required max="<?php echo date('Y-m-d');
                                                                                                                                                                                ?>">
                                <div class="invalid-feedback">Tanggal beli tidak boleh kosong dan tidak boleh melebihi hari ini.</div>
                            </div>
                        </div>

                        <div id="formDisabledMessage" class="alert alert-warning text-center" style="display: none;"></div>

                        <div id="form-fields-container" style="<?php echo ($current_user_role === 'Karyawan' && !$kandang_list) ? 'display:none;' : ''; // Sembunyikan jika karyawan tpi tdk ada kandang 
                                                                ?>">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="nama_pakan" class="form-label">Nama/Merk Pakan</label>
                                    <input type="text" class="form-control" id="nama_pakan" name="nama_pakan" required>
                                    <div class="invalid-feedback">Nama pakan tidak boleh kosong.</div>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <label for="jumlah_kg" class="form-label">Jumlah (kg)</label>
                                    <input type="text" inputmode="decimal" class="form-control format-kg" id="jumlah_kg" name="jumlah_kg" required placeholder="0,00">
                                    <div class="invalid-feedback">Jumlah (kg) wajib diisi dan harus angka (gunakan koma untuk desimal).</div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="harga_per_kg" class="form-label">Harga per Kg (Rp)</label>
                                    <input type="text" inputmode="numeric" class="form-control format-number" id="harga_per_kg" name="harga_per_kg" required placeholder="0" autocomplete="off">
                                    <div class="invalid-feedback">Harga per kg wajib diisi dan harus angka.</div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="harga_total" class="form-label">Harga Total (Rp)</label>
                                    <input type="text" inputmode="numeric" class="form-control format-number" id="harga_total" name="harga_total" required placeholder="0" autocomplete="off" readonly>
                                    <div class="invalid-feedback">Harga total tidak boleh kosong.</div>
                                </div>
                            </div>
                            <button type="submit" id="submitButton" class="btn btn-primary">Simpan Stok</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12">
            <h4><i class="fas fa-list-alt"></i> Riwayat Pembelian Pakan</h4>
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="tabelStokPakan">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <?php if ($current_user_role === 'Pimpinan'): ?>
                                        <th>Nama Kandang</th>
                                    <?php endif; ?>
                                    <th>Nama Pakan</th>
                                    <th>Jumlah (kg)</th>
                                    <th>Harga per Kg</th>
                                    <th>Harga Total</th>
                                    <th class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($stok_result && $stok_result->num_rows > 0): ?>
                                    <?php while ($row = $stok_result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo date('d M Y', strtotime($row['tanggal_beli'])); ?></td>
                                            <?php if ($current_user_role === 'Pimpinan'): ?>
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
                                                        data-harga-total="<?php echo $row['harga_total']; ?>"
                                                        data-bs-toggle-tooltip="tooltip" title="Edit Data">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <a href="hapus.php?id=<?php echo $row['id_stok']; ?>" class="btn btn-danger btn-hapus" data-bs-toggle-tooltip="tooltip" title="Hapus Data">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="<?php echo ($current_user_role === 'Pimpinan' ? '7' : '6'); ?>" class="text-center text-muted">Belum ada data pembelian pakan.</td>
                                    </tr>
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
            <form action="proses_update.php" method="POST" class="needs-validation" novalidate>
                <div class="modal-body">
                    <input type="hidden" id="edit_id_stok" name="id_stok">
                    <?php if ($current_user_role === 'Karyawan' && $current_assigned_kandang_id): ?>
                        <input type="hidden" name="id_kandang" value="<?php echo $current_assigned_kandang_id; ?>" />
                    <?php endif; ?>
                    <div class="mb-3">
                        <label for="edit_id_kandang" class="form-label">Untuk Kandang</label>
                        <select class="form-select" id="edit_id_kandang" name="id_kandang" required <?php echo ($current_user_role === 'Karyawan') ? 'disabled' : ''; ?>>
                            <?php if ($current_user_role === 'Pimpinan'): ?>
                                <option value="" disabled>-- Pilih Kandang --</option>
                            <?php endif; ?>
                            <?php
                            // Reset pointer lagi untuk loop di modal
                            if ($kandang_list) mysqli_data_seek($kandang_list, 0);
                            while ($k = $kandang_list->fetch_assoc()) :
                            ?>
                                <option value="<?php echo $k['id_kandang']; ?>"><?php echo htmlspecialchars($k['nama_kandang']); ?></option>
                            <?php endwhile; ?>
                        </select>
                        <div class="invalid-feedback">Silakan pilih kandang.</div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_tanggal_beli" class="form-label">Tanggal Beli</label>
                        <input type="date" class="form-control" id="edit_tanggal_beli" name="tanggal_beli" required max="<?php echo date('Y-m-d'); ?>">
                        <div class="invalid-feedback">Tanggal beli tidak boleh kosong dan tidak boleh melebihi hari ini.</div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_nama_pakan" class="form-label">Nama/Merk Pakan</label>
                        <input type="text" class="form-control" id="edit_nama_pakan" name="nama_pakan" required>
                        <div class="invalid-feedback">Nama pakan tidak boleh kosong.</div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_jumlah_kg" class="form-label">Jumlah (kg)</label>
                        <input type="text" inputmode="decimal" class="form-control format-kg" id="edit_jumlah_kg" name="jumlah_kg" required placeholder="0,00">
                        <div class="invalid-feedback">Jumlah (kg) wajib diisi dan harus angka (gunakan koma untuk desimal).</div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_harga_per_kg" class="form-label">Harga per Kg (Rp)</label>
                        <input type="text" inputmode="numeric" class="form-control format-number" id="edit_harga_per_kg" name="harga_per_kg" required placeholder="0" autocomplete="off">
                        <div class="invalid-feedback">Harga per kg wajib diisi dan harus angka.</div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_harga_total" class="form-label">Harga Total (Rp)</label>
                        <input type="text" inputmode="numeric" class="form-control format-number" id="edit_harga_total" name="harga_total" required placeholder="0" autocomplete="off" readonly>
                        <div class="invalid-feedback">Harga total tidak boleh kosong.</div>
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
        $('#tabelStokPakan').DataTable({
            "language": {
                "url": "https://cdn.datatables.net/plug-ins/1.13.7/i18n/id.json"
            },
            "order": [
                [0, "desc"]
            ]
        });

        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle-tooltip="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
            return tooltipTriggerEl ? new bootstrap.Tooltip(tooltipTriggerEl) : null;
        });

        const kandangFilter = $('#id_kandang');
        const tanggalFilter = $('#tanggal_beli');
        const formFieldsContainer = $('#form-fields-container');
        const formDisabledMessage = $('#formDisabledMessage');
        const isKaryawan = <?php echo ($current_user_role === 'Karyawan') ? 'true' : 'false'; ?>;
        const folderBase = "<?php echo $folder_base; ?>";

        function checkStokExist() {
            const kandangId = kandangFilter.val();
            const tanggalBeli = tanggalFilter.val();
            const namaKandang = kandangFilter.find('option:selected').text();

            if ((!isKaryawan || kandangId) && tanggalBeli) {
                if (isKaryawan) {
                    formFieldsContainer.slideDown();
                    formDisabledMessage.slideUp();
                    formFieldsContainer.find('input, button').prop('disabled', false);
                    $('#harga_total, #edit_harga_total').prop('readonly', true);
                }
                if (!isKaryawan) {
                    formFieldsContainer.slideDown();
                    formDisabledMessage.slideUp();
                    formFieldsContainer.find('input, button').prop('disabled', false);
                    $('#harga_total, #edit_harga_total').prop('readonly', true);
                }

            } else if (!isKaryawan) {
                formFieldsContainer.slideUp();
                formDisabledMessage.slideUp();
            }
        }

        function formatNumberWithDots(inputElement) {
            let value = $(inputElement).val().replace(/[^0-9]/g, '');
            if (value === '' || value === null) {
                $(inputElement).val('');
                return;
            }
            $(inputElement).val(new Intl.NumberFormat('id-ID').format(value));
        }

        function formatKgNumber(inputElement) {
            let value = $(inputElement).val();
            let decimalPart = '';
            const commaIndex = value.indexOf(',');
            if (commaIndex !== -1) {
                decimalPart = value.substring(commaIndex).replace(/[^0-9]/g, '');
                value = value.substring(0, commaIndex);
            }
            let integerPart = value.replace(/[^0-9]/g, '');
            if (integerPart === '' || integerPart === null) {
                integerPart = '0';
            }
            const formattedInteger = new Intl.NumberFormat('id-ID').format(integerPart);

            let finalValue = formattedInteger;
            if (decimalPart.length > 0) {
                finalValue += ',' + decimalPart.substring(0, 2);
            } else if (commaIndex !== -1) {
                finalValue += ',';
            }
            $(inputElement).val(finalValue);
        }

        function unformatNumber(value) {
            if (typeof value !== 'string') {
                value = String(value);
            }
            return value.replace(/\./g, '');
        }

        function unformatKgNumber(value) {
            if (typeof value !== 'string') {
                value = String(value);
            }
            return value.replace(/\./g, '').replace(',', '.');
        }

        function calculateTotal(jumlahKgInputSelector, hargaPerKgInputSelector, hargaTotalInputSelector) {
            const jumlahKg = parseFloat(unformatKgNumber($(jumlahKgInputSelector).val())) || 0;
            const hargaPerKg = parseFloat(unformatNumber($(hargaPerKgInputSelector).val())) || 0;
            const total = Math.round(jumlahKg * hargaPerKg);
            // Format output total
            $(hargaTotalInputSelector).val(new Intl.NumberFormat('id-ID').format(total));
        }
        $('.filter-check').on('change', checkStokExist);

        $(document).on('keyup input', '.format-number', function() {
            formatNumberWithDots(this);
        });
        $(document).on('blur', '.format-number', function() {
            if ($(this).val() === '') $(this).val('0');
            formatNumberWithDots(this);
        });
        $(document).on('focus', '.format-number', function() {
            if ($(this).val() === '0') $(this).val('');
        });
        $(document).on('keyup input', '.format-kg', function() {
            formatKgNumber(this);
        });
        $(document).on('blur', '.format-kg', function() {
            if ($(this).val() === '' || $(this).val() === '0') $(this).val('0,00');
            formatKgNumber(this);
        });
        $(document).on('focus', '.format-kg', function() {
            if ($(this).val() === '0,00') $(this).val('');
        });
        $('#harga_per_kg, #jumlah_kg').on('keyup input blur', function() {
            calculateTotal('#jumlah_kg', '#harga_per_kg', '#harga_total');
        });
        $(document).on('keyup input blur', '#edit_harga_per_kg, #edit_jumlah_kg', function() {
            calculateTotal('#edit_jumlah_kg', '#edit_harga_per_kg', '#edit_harga_total');
        });
        const editModalEl = document.getElementById('editModal');
        if (editModalEl) {
            editModalEl.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const dataset = button.dataset;

                $('#edit_id_stok').val(dataset.id);
                $('#edit_id_kandang').val(dataset.idKandang || "");
                $('#edit_tanggal_beli').val(dataset.tanggal);
                $('#edit_nama_pakan').val(dataset.namaPakan);
                const initialJumlahKg = parseFloat(dataset.jumlah || 0).toFixed(2).replace('.', ',');
                $('#edit_jumlah_kg').val(initialJumlahKg);
                formatKgNumber(document.getElementById('edit_jumlah_kg'));
                const initialHargaPerKg = dataset.hargaPerKg || 0;
                const initialHargaTotal = dataset.hargaTotal || 0;
                $('#edit_harga_per_kg').val(initialHargaPerKg);
                $('#edit_harga_total').val(initialHargaTotal);
                formatNumberWithDots(document.getElementById('edit_harga_per_kg'));
                formatNumberWithDots(document.getElementById('edit_harga_total'));

                $(editModalEl).find('form').removeClass('was-validated');
            });
        }
        $('#tabelStokPakan tbody').on('click', '.btn-hapus', function(e) {
            /* ... kode hapus ... */
            e.preventDefault();
            const href = $(this).attr('href');
            Swal.fire({
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
        const forms = document.querySelectorAll('.needs-validation');
        Array.from(forms).forEach(form => {
            form.addEventListener('submit', event => {
                $(form).find('.format-number').each(function() {
                    $(this).val(unformatNumber($(this).val()));
                });
                $(form).find('.format-kg').each(function() {
                    $(this).val(unformatKgNumber($(this).val()));
                });

                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                    setTimeout(() => {
                        $(form).find('.format-number').each(function() {
                            formatNumberWithDots(this);
                        });
                        $(form).find('.format-kg').each(function() {
                            formatKgNumber(this);
                        });
                        if (form.id === 'formTambahStok') {
                            calculateTotal('#jumlah_kg', '#harga_per_kg', '#harga_total');
                        } else {
                            calculateTotal('#edit_jumlah_kg', '#edit_harga_per_kg', '#edit_harga_total');
                        }
                    }, 50); // Small delay
                }
                form.classList.add('was-validated');
            }, false);
        });

        checkStokExist();

    });
</script>

</body>

</html>