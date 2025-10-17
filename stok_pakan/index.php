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

// Logika untuk proses tambah stok pakan
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah_stok'])) {
    $tanggal_beli = $_POST['tanggal_beli'];
    $nama_pakan = $_POST['nama_pakan'];
    $jumlah_kg = $_POST['jumlah_kg'];
    $harga_total = str_replace('.', '', $_POST['harga_total']);

    $stmt = $koneksi->prepare("INSERT INTO stok_pakan (tanggal_beli, nama_pakan, jumlah_kg, harga_total) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssdi", $tanggal_beli, $nama_pakan, $jumlah_kg, $harga_total);
    
    if ($stmt->execute()) {
        header('Location: index.php?status=sukses_tambah');
        exit();
    } else {
        $pesan = "<div class='alert alert-danger mt-3'>Gagal menyimpan data: " . $stmt->error . "</div>";
    }
}

$stok_result = $koneksi->query("SELECT * FROM stok_pakan ORDER BY tanggal_beli DESC");
?>

<div class="container-fluid">
    <div class="page-header">
        <h1 class="page-title">Stok Pakan</h1>
        <p class="page-subtitle">Catat dan kelola riwayat pembelian pakan untuk seluruh peternakan.</p>
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
                            <div class="col-md-3 mb-3">
                                <label for="tanggal_beli" class="form-label">Tanggal Beli</label>
                                <input type="date" class="form-control" id="tanggal_beli" name="tanggal_beli" value="<?php echo date('Y-m-d'); ?>" required>
                                <div class="invalid-feedback">Tanggal beli tidak boleh kosong.</div>
                            </div>
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
                                <label for="harga_total" class="form-label">Harga Total (Rp)</label>
                                <input type="tel" class="form-control" id="harga_total" name="harga_total" required autocomplete="off">
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
                                    <th>Nama Pakan</th>
                                    <th>Jumlah (kg)</th>
                                    <th>Harga Total</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($row = $stok_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo date('d M Y', strtotime($row['tanggal_beli'])); ?></td>
                                    <td><?php echo htmlspecialchars($row['nama_pakan']); ?></td>
                                    <td><?php echo number_format($row['jumlah_kg'], 2); ?></td>
                                    <td>Rp <?php echo number_format($row['harga_total']); ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-warning btn-edit" 
                                            data-bs-toggle="modal" data-bs-target="#editModal"
                                            data-id="<?php echo $row['id_stok']; ?>"
                                            data-tanggal="<?php echo $row['tanggal_beli']; ?>"
                                            data-nama-pakan="<?php echo htmlspecialchars($row['nama_pakan']); ?>"
                                            data-jumlah="<?php echo $row['jumlah_kg']; ?>"
                                            data-harga="<?php echo $row['harga_total']; ?>">
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
                <label for="edit_harga_total" class="form-label">Harga Total (Rp)</label>
                <input type="tel" class="form-control" id="edit_harga_total" name="harga_total" required autocomplete="off">
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
    // 1. Inisialisasi DataTables
    $('#tabelStokPakan').DataTable({
        "language": { "url": "https://cdn.datatables.net/plug-ins/1.13.7/i18n/id.json" }
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

    // 3. Terapkan fungsi format ke input 'Harga Total' di form utama
    $('#harga_total').on('keyup input', function() {
        formatNumberWithDots(this);
    });

    // 4. Terapkan juga ke input 'Harga Total' di modal edit
    $('#edit_harga_total').on('keyup input', function() {
        formatNumberWithDots(this);
    });

    // 5. Logika untuk Modal Edit
    const editModal = document.getElementById('editModal');
    editModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const id = button.getAttribute('data-id');
        const tanggal = button.getAttribute('data-tanggal');
        const namaPakan = button.getAttribute('data-nama-pakan');
        const jumlah = button.getAttribute('data-jumlah');
        const harga = button.getAttribute('data-harga');

        $('#edit_id_stok').val(id);
        $('#edit_tanggal_beli').val(tanggal);
        $('#edit_nama_pakan').val(namaPakan);
        $('#edit_jumlah_kg').val(jumlah);
        $('#edit_harga_total').val(new Intl.NumberFormat('id-ID').format(harga));
    });

    // 6. Logika untuk SweetAlert Hapus
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

    // 7. Logika untuk Validasi Form Bootstrap
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