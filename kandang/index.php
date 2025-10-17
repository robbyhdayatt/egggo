<?php
include '../templates/header.php';

// Cek notifikasi dari URL
$pesan = '';
if (isset($_GET['status'])) {
    if ($_GET['status'] == 'sukses_tambah') {
        $pesan = "<div class='alert alert-success'>Kandang baru berhasil ditambahkan!</div>";
    } elseif ($_GET['status'] == 'sukses_update') {
        $pesan = "<div class='alert alert-success'>Data kandang berhasil diperbarui!</div>";
    } elseif ($_GET['status'] == 'sukses_hapus') {
        $pesan = "<div class='alert alert-success'>Data kandang berhasil dihapus!</div>";
    }
}

// Logika untuk proses tambah kandang
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah_kandang'])) {
    $nama_kandang = $_POST['nama_kandang'];
    $tgl_masuk_awal = $_POST['tgl_masuk_awal'];
    $populasi_awal = $_POST['populasi_awal'];
    
    // Konversi umur dari minggu ke hari sebelum disimpan
    $umur_ayam_awal_minggu = $_POST['umur_ayam_awal'];
    $umur_ayam_awal_hari = $umur_ayam_awal_minggu * 7;
    
    $stok_telur_awal_kg = $_POST['stok_telur_awal_kg'];

    $stmt = $koneksi->prepare("INSERT INTO kandang (nama_kandang, tgl_masuk_awal, populasi_awal, umur_ayam_awal, stok_telur_awal_kg) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssiid", $nama_kandang, $tgl_masuk_awal, $populasi_awal, $umur_ayam_awal_hari, $stok_telur_awal_kg);
    $stmt->execute();
    header('Location: index.php?status=sukses_tambah');
    exit();
}

// Mengambil data kandang untuk ditampilkan
$hasil = $koneksi->query("SELECT * FROM kandang ORDER BY id_kandang DESC");
?>

<div class="container-fluid">
    <div class="page-header">
        <h1 class="page-title">Manajemen Kandang</h1>
        <p class="page-subtitle">Tambah, edit, atau hapus data master kandang Anda.</p>
    </div>
    <?php echo $pesan; ?>
    <div class="row">
        <div class="col-12 mb-4">
            <h4><i class="fas fa-plus-circle"></i> Tambah Kandang Baru</h4>
            <div class="card">
                <div class="card-body">
                    <form method="POST" class="needs-validation" novalidate>
                        <input type="hidden" name="tambah_kandang" value="1">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="nama_kandang" class="form-label">Nama Kandang</label>
                                <input type="text" class="form-control" id="nama_kandang" name="nama_kandang" required>
                                <div class="invalid-feedback">Nama kandang wajib diisi.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="tgl_masuk_awal" class="form-label">Tanggal Masuk Awal</label>
                                <input type="date" class="form-control" id="tgl_masuk_awal" name="tgl_masuk_awal" required>
                                <div class="invalid-feedback">Tanggal masuk wajib diisi.</div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="populasi_awal" class="form-label">Populasi Awal (ekor)</label>
                                <input type="number" class="form-control" id="populasi_awal" name="populasi_awal" required>
                                <div class="invalid-feedback">Populasi awal wajib diisi.</div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="umur_ayam_awal" class="form-label">Umur Ayam Awal (minggu)</label>
                                <input type="number" class="form-control" id="umur_ayam_awal" name="umur_ayam_awal" required>
                                <div class="invalid-feedback">Umur ayam awal wajib diisi.</div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="stok_telur_awal_kg" class="form-label">Stok Telur Awal (kg)</label>
                                <input type="number" step="0.01" class="form-control" id="stok_telur_awal_kg" name="stok_telur_awal_kg" value="0.00" required>
                                <div class="invalid-feedback">Stok telur awal wajib diisi.</div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Simpan Kandang</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12">
            <h4><i class="fas fa-home"></i> Daftar Kandang</h4>
            <div class="card">
                <div class="card-body table-responsive">
                    <table class="table table-striped table-hover" id="tabelKandang">
                        <thead>
                            <tr>
                                <th>Nama Kandang</th>
                                <th>Populasi Awal</th>
                                <th>Umur Awal (minggu)</th>
                                <th>Stok Telur Awal (kg)</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $hasil->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <?php echo htmlspecialchars($row['nama_kandang']); ?><br>
                                    <small class="text-muted">Tgl Masuk: <?php echo date('d M Y', strtotime($row['tgl_masuk_awal'])); ?></small>
                                </td>
                                <td><?php echo number_format($row['populasi_awal']); ?></td>
                                <td><?php echo number_format($row['umur_ayam_awal'] / 7, 1); ?></td>
                                <td><?php echo number_format($row['stok_telur_awal_kg'], 2); ?></td>
                                <td><span class="badge bg-<?php echo $row['status'] == 'Aktif' ? 'success' : 'secondary'; ?>"><?php echo $row['status']; ?></span></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-warning btn-edit" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#editModal"
                                        data-id="<?php echo $row['id_kandang']; ?>"
                                        data-nama="<?php echo htmlspecialchars($row['nama_kandang']); ?>"
                                        data-tgl="<?php echo $row['tgl_masuk_awal']; ?>"
                                        data-populasi="<?php echo $row['populasi_awal']; ?>"
                                        data-umur="<?php echo $row['umur_ayam_awal']; ?>"
                                        data-stok-telur="<?php echo $row['stok_telur_awal_kg']; ?>"
                                        data-status="<?php echo $row['status']; ?>">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <a href="hapus.php?id=<?php echo $row['id_kandang']; ?>" class="btn btn-sm btn-danger btn-hapus">
                                        <i class="fas fa-trash"></i> Hapus
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

<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editModalLabel">Edit Data Kandang</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="proses_update.php" method="POST">
          <div class="modal-body">
              <input type="hidden" id="edit_id_kandang" name="id_kandang">
              <div class="mb-3">
                  <label for="edit_nama_kandang" class="form-label">Nama Kandang</label>
                  <input type="text" class="form-control" id="edit_nama_kandang" name="nama_kandang" required>
              </div>
              <div class="mb-3">
                  <label for="edit_tgl_masuk_awal" class="form-label">Tanggal Masuk Awal</label>
                  <input type="date" class="form-control" id="edit_tgl_masuk_awal" name="tgl_masuk_awal" required>
              </div>
              <div class="mb-3">
                  <label for="edit_populasi_awal" class="form-label">Populasi Awal (ekor)</label>
                  <input type="number" class="form-control" id="edit_populasi_awal" name="populasi_awal" required>
              </div>
              <div class="mb-3">
                  <label for="edit_umur_ayam_awal" class="form-label">Umur Ayam Awal (minggu)</label>
                  <input type="number" class="form-control" id="edit_umur_ayam_awal" name="umur_ayam_awal" required>
              </div>
              <div class="mb-3">
                  <label for="edit_stok_telur_awal_kg" class="form-label">Stok Telur Awal (kg)</label>
                  <input type="number" step="0.01" class="form-control" id="edit_stok_telur_awal_kg" name="stok_telur_awal_kg" required>
              </div>
              <div class="mb-3">
                  <label for="edit_status" class="form-label">Status</label>
                  <select class="form-select" id="edit_status" name="status">
                      <option value="Aktif">Aktif</option>
                      <option value="Panen">Panen</option>
                  </select>
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
    $('#tabelKandang').DataTable({
        "language": { "url": "https://cdn.datatables.net/plug-ins/1.13.7/i18n/id.json" }
    });
    
    // Logika untuk Modal Edit
    const editModal = document.getElementById('editModal');
    editModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        
        // Mengambil semua data dari data-* attributes
        const id = button.getAttribute('data-id');
        const nama = button.getAttribute('data-nama');
        const tgl = button.getAttribute('data-tgl');
        const populasi = button.getAttribute('data-populasi');
        const umurHari = button.getAttribute('data-umur'); // Ini berisi nilai dalam HARI (misal: 35)
        const stokTelur = button.getAttribute('data-stok-telur'); // Atribut untuk stok telur
        const status = button.getAttribute('data-status');
        
        // --- PERBAIKAN LOGIKA DI SINI ---
        // 1. Konversi umur dari HARI kembali ke MINGGU untuk ditampilkan di form
        const umurMinggu = umurHari / 7;

        // Update isi form di dalam modal
        editModal.querySelector('.modal-title').textContent = 'Edit Kandang: ' + nama;
        editModal.querySelector('#edit_id_kandang').value = id;
        editModal.querySelector('#edit_nama_kandang').value = nama;
        editModal.querySelector('#edit_tgl_masuk_awal').value = tgl;
        editModal.querySelector('#edit_populasi_awal').value = populasi;
        editModal.querySelector('#edit_umur_ayam_awal').value = umurMinggu; // Gunakan hasil konversi
        editModal.querySelector('#edit_stok_telur_awal_kg').value = stokTelur; // Tampilkan stok telur
        editModal.querySelector('#edit_status').value = status;
    });

    // Logika untuk SweetAlert Hapus (tidak berubah)
    document.querySelectorAll('.btn-hapus').forEach(button => {
        button.addEventListener('click', function (event) {
            event.preventDefault();
            const href = this.getAttribute('href');
            Swal.fire({
                title: 'Apakah Anda yakin?',
                text: "Data kandang dan semua laporan terkait akan dihapus permanen!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Ya, hapus!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = href;
                }
            });
        });
    });

    // Logika untuk Validasi Form Bootstrap (tidak berubah)
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