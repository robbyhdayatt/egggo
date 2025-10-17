<?php
include '../templates/header.php';

// Cek notifikasi dari URL
$pesan = '';
if (isset($_GET['status'])) {
    if ($_GET['status'] == 'sukses_tambah') {
        $pesan = "<div class='alert alert-success mt-3'>Data pembelian pakan berhasil disimpan!</div>";
    } elseif ($_GET['status'] == 'sukses_update') {
        $pesan = "<div class='alert alert-success mt-3'>Data pembelian pakan berhasil diperbarui!</div>";
    } elseif ($_GET['status'] == 'sukses_hapus') {
        $pesan = "<div class='alert alert-success mt-3'>Data pembelian pakan berhasil dihapus!</div>";
    }
}

// Mengambil daftar kandang untuk dropdown
$kandang_list = $koneksi->query("SELECT id_kandang, nama_kandang FROM kandang WHERE status = 'Aktif' ORDER BY nama_kandang");

// Logika untuk proses tambah stok pakan
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah_stok'])) {
    $id_kandang = $_POST['id_kandang'];
    $tanggal_beli = $_POST['tanggal_beli'];
    $nama_pakan = $_POST['nama_pakan'];
    $jumlah_kg = $_POST['jumlah_kg'];
    // Harga total diambil langsung dari input yang sudah terkalkulasi
    $harga_total = str_replace('.', '', $_POST['harga_total']);

    // Tambahkan harga per kg ke database (opsional, tapi baik untuk data historis)
    $harga_per_kg = str_replace('.', '', $_POST['harga_per_kg']);


    $stmt = $koneksi->prepare("INSERT INTO stok_pakan (id_kandang, tanggal_beli, nama_pakan, jumlah_kg, harga_per_kg, harga_total) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issdii", $id_kandang, $tanggal_beli, $nama_pakan, $jumlah_kg, $harga_per_kg, $harga_total);

    if ($stmt->execute()) {
        header('Location: index.php?status=sukses_tambah');
        exit();
    } else {
        $pesan = "<div class='alert alert-danger mt-3'>Gagal menyimpan data: " . $stmt->error . "</div>";
    }
}

// Mengambil data stok pakan dengan join ke tabel kandang, diurutkan berdasarkan data terbaru
$stok_result = $koneksi->query("
    SELECT sp.*, k.nama_kandang 
    FROM stok_pakan sp
    JOIN kandang k ON sp.id_kandang = k.id_kandang
    ORDER BY sp.tanggal_beli DESC, sp.id_stok DESC
");
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
                    <form method="POST" class="needs-validation" novalidate>
                        <input type="hidden" name="tambah_stok" value="1">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="id_kandang" class="form-label">Untuk Kandang</label>
                                <select class="form-select" id="id_kandang" name="id_kandang" required>
                                    <option value="" disabled selected>-- Pilih Kandang --</option>
                                    <?php while ($k = $kandang_list->fetch_assoc()) : ?>
                                        <option value="<?php echo $k['id_kandang']; ?>"><?php echo htmlspecialchars($k['nama_kandang']); ?></option>
                                    <?php endwhile; ?>
                                </select>
                                <div class="invalid-feedback">Silakan pilih kandang.</div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="tanggal_beli" class="form-label">Tanggal Beli</label>
                                <input type="date" class="form-control" id="tanggal_beli" name="tanggal_beli" value="<?php echo date('Y-m-d'); ?>" required>
                                <div class="invalid-feedback">Tanggal beli tidak boleh kosong.</div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="nama_pakan" class="form-label">Nama/Merk Pakan</label>
                                <input type="text" class="form-control" id="nama_pakan" name="nama_pakan" required>
                                <div class="invalid-feedback">Nama pakan tidak boleh kosong.</div>
                            </div>
                            <div class="col-md-3 mb-3">
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
                        <button type="submit" class="btn btn-primary">Simpan Stok</button>
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
                                    <th>Nama Kandang</th>
                                    <th>Nama Pakan</th>
                                    <th>Jumlah (kg)</th>
                                    <th>Harga per Kg</th>
                                    <th>Harga Total</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($row = $stok_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo date('d M Y', strtotime($row['tanggal_beli'])); ?></td>
                                    <td><?php echo htmlspecialchars($row['nama_kandang']); ?></td>
                                    <td><?php echo htmlspecialchars($row['nama_pakan']); ?></td>
                                    <td class="text-end"><?php echo number_format($row['jumlah_kg'], 2); ?></td>
                                    <td class="text-end">Rp <?php echo number_format($row['harga_per_kg'] ?? 0); ?></td>
                                    <td class="text-end">Rp <?php echo number_format($row['harga_total']); ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-warning btn-edit" 
                                            data-bs-toggle="modal" data-bs-target="#editModal"
                                            data-id="<?php echo $row['id_stok']; ?>"
                                            data-id-kandang="<?php echo $row['id_kandang']; ?>"
                                            data-tanggal="<?php echo $row['tanggal_beli']; ?>"
                                            data-nama-pakan="<?php echo htmlspecialchars($row['nama_pakan']); ?>"
                                            data-jumlah="<?php echo $row['jumlah_kg']; ?>"
                                            data-harga-per-kg="<?php echo $row['harga_per_kg'] ?? 0; ?>"
                                            data-harga-total="<?php echo $row['harga_total']; ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="hapus.php?id=<?php echo $row['id_stok']; ?>" class="btn btn-sm btn-danger btn-hapus">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
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
      <form action="proses_update.php" method="POST">
        <div class="modal-body">
            <input type="hidden" id="edit_id_stok" name="id_stok">
             <div class="mb-3">
                <label for="edit_id_kandang" class="form-label">Untuk Kandang</label>
                <select class="form-select" id="edit_id_kandang" name="id_kandang" required>
                    <option value="" disabled>-- Pilih Kandang --</option>
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
    // 1. Inisialisasi DataTables dengan sorting default terbaru di atas
    $('#tabelStokPakan').DataTable({
        "language": { "url": "https://cdn.datatables.net/plug-ins/1.13.7/i18n/id.json" },
        "order": [[ 0, "desc" ]] // <-- Perubahan di sini
    });

    // 2. Fungsi untuk memformat angka dengan titik
    function formatNumberWithDots(input) {
        let value = input.value.replace(/[^0-9]/g, '');
        if (value === '' || value === null) {
            input.value = '';
            return;
        }
        input.value = new Intl.NumberFormat('id-ID').format(value);
    }
    
    // 3. Fungsi untuk menghapus format titik
    function unformatNumber(value) {
        return value.replace(/[^0-9]/g, '');
    }

    // 4. Fungsi untuk kalkulasi total harga
    function calculateTotal(jumlahKgInput, hargaPerKgInput, hargaTotalInput) {
        const jumlahKg = parseFloat($(jumlahKgInput).val()) || 0;
        const hargaPerKg = parseFloat(unformatNumber($(hargaPerKgInput).val())) || 0;
        const total = jumlahKg * hargaPerKg;
        
        // Format dan set nilai ke input harga total
        $(hargaTotalInput).val(new Intl.NumberFormat('id-ID').format(total));
    }

    // 5. Terapkan fungsi format dan kalkulasi ke form utama
    $('#harga_per_kg').on('keyup input', function() {
        formatNumberWithDots(this);
        calculateTotal('#jumlah_kg', '#harga_per_kg', '#harga_total');
    });
    $('#jumlah_kg').on('keyup input', function() {
        calculateTotal('#jumlah_kg', '#harga_per_kg', '#harga_total');
    });

    // 6. Terapkan fungsi format dan kalkulasi ke form modal edit
    $('#edit_harga_per_kg').on('keyup input', function() {
        formatNumberWithDots(this);
        calculateTotal('#edit_jumlah_kg', '#edit_harga_per_kg', '#edit_harga_total');
    });
     $('#edit_jumlah_kg').on('keyup input', function() {
        calculateTotal('#edit_jumlah_kg', '#edit_harga_per_kg', '#edit_harga_total');
    });

    // 7. Logika untuk Modal Edit
    const editModal = document.getElementById('editModal');
    editModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        // Ambil semua data dari atribut data-*
        $('#edit_id_stok').val(button.dataset.id);
        $('#edit_id_kandang').val(button.dataset.idKandang);
        $('#edit_tanggal_beli').val(button.dataset.tanggal);
        $('#edit_nama_pakan').val(button.dataset.namaPakan);
        $('#edit_jumlah_kg').val(button.dataset.jumlah);
        // Format angka saat mengisi modal
        $('#edit_harga_per_kg').val(new Intl.NumberFormat('id-ID').format(button.dataset.hargaPerKg));
        $('#edit_harga_total').val(new Intl.NumberFormat('id-ID').format(button.dataset.hargaTotal));
    });

    // 8. Logika untuk SweetAlert Hapus
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

    // 9. Logika untuk Validasi Form Bootstrap
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