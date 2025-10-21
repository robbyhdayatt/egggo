<?php
include '../templates/header.php';
// Ambil variabel role global dari header.php
global $current_user_role, $current_assigned_kandang_id;

$pesan = '';
if (isset($_GET['status'])) {
    if ($_GET['status'] == 'sukses_tambah') { $pesan = "<div class='alert alert-success mt-3'>Data pembelian pakan berhasil disimpan!</div>"; }
    elseif ($_GET['status'] == 'sukses_update') { $pesan = "<div class='alert alert-success mt-3'>Data pembelian pakan berhasil diperbarui!</div>"; } 
    elseif ($_GET['status'] == 'sukses_hapus') { $pesan = "<div class='alert alert-success mt-3'>Data pembelian pakan berhasil dihapus!</div>"; }
}

// --- MODIFIKASI QUERY KANDANG ---
$kandang_query = "SELECT id_kandang, nama_kandang FROM kandang WHERE status = 'Aktif'";
if ($current_user_role === 'Karyawan' && $current_assigned_kandang_id) {
    $kandang_query .= " AND id_kandang = " . (int)$current_assigned_kandang_id;
}
$kandang_query .= " ORDER BY nama_kandang";
$kandang_list = $koneksi->query($kandang_query);
// --- AKHIR MODIFIKASI ---


// Logika untuk proses tambah stok pakan
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah_stok'])) {
    $id_kandang = $_POST['id_kandang'];
    $tanggal_beli = $_POST['tanggal_beli'];
    $nama_pakan = $_POST['nama_pakan'];
    $jumlah_kg = (float)str_replace(',', '.', $_POST['jumlah_kg']); // Pastikan float
    $harga_per_kg = str_replace('.', '', $_POST['harga_per_kg']); // Hapus titik
    $harga_total = str_replace('.', '', $_POST['harga_total']); // Hapus titik

    // --- VALIDASI HAK AKSES DATA ---
    if ($current_user_role === 'Karyawan' && $id_kandang != $current_assigned_kandang_id) {
         $pesan = "<div class='alert alert-danger mt-3'>Error: Anda tidak berhak menginput data untuk kandang ini.</div>";
    } else {
    // --- AKHIR VALIDASI ---
        
        // Validasi duplikat
        $stmt_check = $koneksi->prepare("SELECT id_stok FROM stok_pakan WHERE id_kandang = ? AND tanggal_beli = ?");
        $stmt_check->bind_param("is", $id_kandang, $tanggal_beli);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();

        if ($result_check->num_rows > 0) {
            $pesan = "<div class='alert alert-danger mt-3'>Gagal menyimpan: Data untuk kandang dan tanggal ini sudah ada.</div>";
        } else {
            // --- PERBAIKAN TIPE DATA BIND_PARAM ---
            // Ubah 'i' (integer) untuk harga menjadi 'd' (double) agar muat angka besar
            $stmt = $koneksi->prepare("INSERT INTO stok_pakan (id_kandang, tanggal_beli, nama_pakan, jumlah_kg, harga_per_kg, harga_total) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issddd", $id_kandang, $tanggal_beli, $nama_pakan, $jumlah_kg, $harga_per_kg, $harga_total);
            // --- AKHIR PERBAIKAN ---

            if ($stmt->execute()) {
                header('Location: index.php?status=sukses_tambah'); exit();
            } else {
                $pesan = "<div class='alert alert-danger mt-3'>Gagal menyimpan data: " . $stmt->error . "</div>";
            }
        }
    }
}

// --- MODIFIKASI QUERY DATA STOK PAKAN ---
$stok_query = "
    SELECT sp.*, k.nama_kandang 
    FROM stok_pakan sp
    JOIN kandang k ON sp.id_kandang = k.id_kandang
";
if ($current_user_role === 'Karyawan' && $current_assigned_kandang_id) {
    $stok_query .= " WHERE sp.id_kandang = " . (int)$current_assigned_kandang_id;
}
$stok_query .= " ORDER BY sp.tanggal_beli DESC, sp.id_stok DESC";
$stok_result = $koneksi->query($stok_query);
// --- AKHIR MODIFIKASI ---
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
                                        <option value="<?php echo $k['id_kandang']; ?>" <?php echo ($current_user_role === 'Karyawan') ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($k['nama_kandang']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                                <div class="invalid-feedback">Silakan pilih kandang.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="tanggal_beli" class="form-label">Tanggal Beli</label>
                                <input type="date" class="form-control filter-check" id="tanggal_beli" name="tanggal_beli" value="<?php echo date('Y-m-d'); ?>" required>
                                <div class="invalid-feedback">Tanggal beli tidak boleh kosong.</div>
                            </div>
                        </div>

                        <div id="formDisabledMessage" class="alert alert-warning text-center" style="display: none;"></div>

                        <div id="form-fields-container" style="<?php echo ($current_user_role === 'Karyawan') ? 'display:none;' : ''; // Sembunyikan awal untuk karyawan ?>">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="nama_pakan" class="form-label">Nama/Merk Pakan</label>
                                    <input type="text" class="form-control" id="nama_pakan" name="nama_pakan" required>
                                    <div class="invalid-feedback">Nama pakan tidak boleh kosong.</div>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <label for="jumlah_kg" class="form-label">Jumlah (kg)</label>
                                    <input type="number" step="0.01" class="form-control" id="jumlah_kg" name="jumlah_kg" required>
                                    <div class="invalid-feedback">Jumlah (kg) tidak boleh kosong.</div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="harga_per_kg" class="form-label">Harga per Kg (Rp)</label>
                                    <input type="tel" class="form-control" id="harga_per_kg" name="harga_per_kg" required autocomplete="off">
                                    <div class="invalid-feedback">Harga per kg tidak boleh kosong.</div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="harga_total" class="form-label">Harga Total (Rp)</label>
                                    <input type="tel" class="form-control" id="harga_total" name="harga_total" required autocomplete="off" readonly>
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
                                <?php if($stok_result): ?>
                                <?php while($row = $stok_result->fetch_assoc()): ?>
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
                                                data-bs-toggle="tooltip" title="Edit Data">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <a href="hapus.php?id=<?php echo $row['id_stok']; ?>" class="btn btn-danger btn-hapus" data-bs-toggle="tooltip" title="Hapus Data">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
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
                        if ($kandang_list) mysqli_data_seek($kandang_list, 0);
                        while ($k = $kandang_list->fetch_assoc()) : 
                    ?>
                        <option value="<?php echo $k['id_kandang']; ?>"><?php echo htmlspecialchars($k['nama_kandang']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="mb-3">
                <label for="edit_tanggal_beli" class="form-label">Tanggal Beli</label>
                <input type="date" class="form-control" id="edit_tanggal_beli" name="tanggal_beli" required>
            </div>
            <div class="mb-3">
                <label for="edit_nama_pakan" class="form-label">Nama/Merk Pakan</label>
                <input type="text" class="form-control" id="edit_nama_pakan" name="nama_pakan" required>
            </div>
            <div class="mb-3">
                <label for="edit_jumlah_kg" class="form-label">Jumlah (kg)</label>
                <input type="number" step="0.01" class="form-control" id="edit_jumlah_kg" name="jumlah_kg" required>
            </div>
             <div class="mb-3">
                <label for="edit_harga_per_kg" class="form-label">Harga per Kg (Rp)</label>
                <input type="tel" class="form-control" id="edit_harga_per_kg" name="harga_per_kg" required autocomplete="off">
            </div>
            <div class="mb-3">
                <label for="edit_harga_total" class="form-label">Harga Total (Rp)</label>
                <input type="tel" class="form-control" id="edit_harga_total" name="harga_total" required autocomplete="off" readonly>
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
    // --- 1. INISIALISASI ---
    $('#tabelStokPakan').DataTable({
        "language": { "url": "https://cdn.datatables.net/plug-ins/1.13.7/i18n/id.json" },
        "order": [[ 0, "desc" ]] 
    });
    
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // --- 2. VARIABEL GLOBAL JS ---
    const kandangFilter = $('#id_kandang');
    const tanggalFilter = $('#tanggal_beli');
    const formFieldsContainer = $('#form-fields-container');
    const formDisabledMessage = $('#formDisabledMessage');
    const isKaryawan = <?php echo ($current_user_role === 'Karyawan') ? 'true' : 'false'; ?>;

    // --- 3. FUNGSI UTAMA ---

    // Fungsi Cek Duplikat (AJAX)
    function checkStokExist() {
        const kandangId = kandangFilter.val();
        const tanggalBeli = tanggalFilter.val();
        const namaKandang = kandangFilter.find('option:selected').text();

        if (kandangId && tanggalBeli) {
            // Tampilkan form fields dulu agar user bisa input
            formFieldsContainer.slideDown(); 
            
            $.getJSON(`cek_stok.php?id_kandang=${kandangId}&tanggal_beli=${tanggalBeli}`, function(response) {
                if (response.exists) {
                    formDisabledMessage.html(`<i class="fas fa-exclamation-triangle"></i> Kandang <strong>${namaKandang}</strong> sudah menginput data pakan pada tanggal ini. Silakan edit data yang ada di riwayat.`).slideDown();
                    formFieldsContainer.find('input, button').prop('disabled', true);
                } else {
                    formDisabledMessage.slideUp();
                    formFieldsContainer.find('input, button').prop('disabled', false);
                    $('#harga_total, #edit_harga_total').prop('readonly', true); // Pastikan total tetap readonly
                }
            }).fail(function() {
                 formDisabledMessage.html(`<i class="fas fa-exclamation-circle"></i> Terjadi kesalahan saat memeriksa data.`).slideDown();
                 formFieldsContainer.find('input, button').prop('disabled', true);
            });
        } else if (!isKaryawan) { // Jika Pimpinan dan belum pilih
            formFieldsContainer.slideUp(); // Sembunyikan jika kandang/tanggal belum lengkap
        }
    }

    // Fungsi Format Angka Ribuan
    function formatNumberWithDots(input) {
        let value = input.value.replace(/[^0-9]/g, '');
        if (value === '' || value === null) {
            input.value = '';
            return;
        }
        input.value = new Intl.NumberFormat('id-ID').format(value);
    }
    
    // Fungsi Hapus Format
    function unformatNumber(value) {
        if(typeof value !== 'string') {
             value = String(value);
        }
        return value.replace(/[^0-9.]/g, '').replace(/\./g, ''); // Hapus semua titik
    }

    // Fungsi Kalkulasi Total Harga
    function calculateTotal(jumlahKgInput, hargaPerKgInput, hargaTotalInput) {
        // parseFloat('10.000,00') akan error, jadi kita ganti koma jadi titik
        const jumlahKg = parseFloat($(jumlahKgInput).val().replace(/\./g, '').replace(',', '.')) || 0; 
        const hargaPerKg = parseFloat(unformatNumber($(hargaPerKgInput).val())) || 0;
        
        // Gunakan Math.round untuk menghindari masalah presisi float
        const total = Math.round(jumlahKg * hargaPerKg); 
        
        $(hargaTotalInput).val(new Intl.NumberFormat('id-ID').format(total));
    }

    // --- 4. EVENT LISTENERS ---

    // Listener untuk Pilihan Filter (Kandang & Tanggal)
    $('.filter-check').on('change', checkStokExist);

    // Listener untuk Format & Kalkulasi Form TAMBAH
    $('#harga_per_kg').on('keyup input', function() {
        formatNumberWithDots(this); // Format diri sendiri
        calculateTotal('#jumlah_kg', '#harga_per_kg', '#harga_total'); // Kalkulasi
    });
    $('#jumlah_kg').on('keyup input', function() {
        // Opsi: format input jumlah_kg jika diperlukan (misal ganti titik dgn koma)
        // $(this).val($(this).val().replace('.', ',')); 
        calculateTotal('#jumlah_kg', '#harga_per_kg', '#harga_total'); // Kalkulasi
    });

    // Listener untuk Format & Kalkulasi Form EDIT
    $('#edit_harga_per_kg').on('keyup input', function() {
        formatNumberWithDots(this); // Format diri sendiri
        calculateTotal('#edit_jumlah_kg', '#edit_harga_per_kg', '#edit_harga_total'); // Kalkulasi
    });
    $('#edit_jumlah_kg').on('keyup input', function() {
        // $(this).val($(this).val().replace('.', ','));
        calculateTotal('#edit_jumlah_kg', '#edit_harga_per_kg', '#edit_harga_total'); // Kalkulasi
    });

    // Listener untuk Modal Edit (Mengisi data)
    const editModal = document.getElementById('editModal');
    if(editModal) {
        editModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const idKandang = button.dataset.idKandang || "";
            const hargaPerKg = button.dataset.hargaPerKg || 0;
            const hargaTotal = button.dataset.hargaTotal || 0;
            
            $('#edit_id_stok').val(button.dataset.id);
            $('#edit_id_kandang').val(idKandang);
            $('#edit_tanggal_beli').val(button.dataset.tanggal);
            $('#edit_nama_pakan').val(button.dataset.namaPakan);
            $('#edit_jumlah_kg').val(parseFloat(button.dataset.jumlah).toFixed(2).replace('.', ',')); // Format IDN
            
            // Set nilai DAN format
            $('#edit_harga_per_kg').val(new Intl.NumberFormat('id-ID').format(hargaPerKg));
            $('#edit_harga_total').val(new Intl.NumberFormat('id-ID').format(hargaTotal));
            
            $(editModal).find('form').removeClass('was-validated');
        });
    }

    // Listener untuk Tombol Hapus (SweetAlert)
    $('#tabelStokPakan tbody').on('click', '.btn-hapus', function(e) {
        e.preventDefault();
        const href = $(this).attr('href');
        Swal.fire({
            title: 'Anda yakin?',
            text: "Data pembelian pakan ini akan dihapus permanen!",
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

    // Listener untuk Validasi Form Bootstrap
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            // Hapus format titik dari input harga SEBELUM submit
            $(form).find('#harga_per_kg, #edit_harga_per_kg, #harga_total, #edit_harga_total').each(function() {
                $(this).val(unformatNumber($(this).val()));
            });
            // Ganti koma jadi titik untuk jumlah_kg
             $(form).find('#jumlah_kg, #edit_jumlah_kg').each(function() {
                $(this).val($(this).val().replace(',', '.'));
            });

            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
            
            // Kembalikan format setelah submit (jika validasi gagal)
            setTimeout(() => {
                formatNumberWithDots(document.getElementById('harga_per_kg'));
                formatNumberWithDots(document.getElementById('edit_harga_per_kg'));
                calculateTotal('#jumlah_kg', '#harga_per_kg', '#harga_total');
                calculateTotal('#edit_jumlah_kg', '#edit_harga_per_kg', '#edit_harga_total');
            }, 100);
        }, false);
    });

    // --- 5. TRIGGER AWAL ---
    if (isKaryawan) {
        checkStokExist(); // Panggil pengecekan awal untuk karyawan
    } else {
        // Untuk pimpinan, sembunyikan form fields sampai mereka memilih
        formFieldsContainer.slideUp();
    }

});
</script>

</body>
</html>