<?php
include '../templates/header.php';

// Cek notifikasi dari URL
$pesan = '';
if (isset($_GET['status'])) {
    if ($_GET['status'] == 'sukses_tambah') {
        $pesan = "<div class='alert alert-success mt-3'>Data pengeluaran berhasil disimpan!</div>";
    } elseif ($_GET['status'] == 'sukses_update') {
        $pesan = "<div class='alert alert-success mt-3'>Data pengeluaran berhasil diperbarui!</div>";
    } elseif ($_GET['status'] == 'sukses_hapus') {
        $pesan = "<div class='alert alert-success mt-3'>Data pengeluaran berhasil dihapus!</div>";
    }
}

// Mengambil daftar kandang untuk dropdown
$kandang_list = $koneksi->query("SELECT id_kandang, nama_kandang FROM kandang WHERE status = 'Aktif' ORDER BY nama_kandang");

// Logika untuk proses tambah pengeluaran
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah_pengeluaran'])) {
    $id_kandang = $_POST['id_kandang'];
    $tanggal_pengeluaran = $_POST['tanggal_pengeluaran'];
    $kategori = $_POST['kategori'];
    $jumlah = str_replace('.', '', $_POST['jumlah']);
    $keterangan = $_POST['keterangan'];

    $stmt = $koneksi->prepare("INSERT INTO pengeluaran (id_kandang, tanggal_pengeluaran, kategori, jumlah, keterangan) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issis", $id_kandang, $tanggal_pengeluaran, $kategori, $jumlah, $keterangan);

    if ($stmt->execute()) {
        header('Location: index.php?status=sukses_tambah');
        exit();
    } else {
        $pesan = "<div class='alert alert-danger mt-3'>Gagal menyimpan data: " . $stmt->error . "</div>";
    }
}

// Mengambil data pengeluaran dengan join ke tabel kandang
$pengeluaran_result = $koneksi->query("
    SELECT p.*, k.nama_kandang 
    FROM pengeluaran p
    JOIN kandang k ON p.id_kandang = k.id_kandang
    ORDER BY p.tanggal_pengeluaran DESC, p.id_pengeluaran DESC
");
?>

<div class="container-fluid">
    <div class="page-header">
        <h1 class="page-title">Manajemen Pengeluaran</h1>
        <p class="page-subtitle">Catat semua pengeluaran operasional per kandang.</p>
    </div>
    <?php echo $pesan; ?>
    <div class="row">
        <div class="col-12 mb-4">
            <h4><i class="fas fa-plus-circle"></i> Input Pengeluaran Baru</h4>
            <div class="card">
                <div class="card-body">
                    <form method="POST" class="needs-validation" novalidate>
                        <input type="hidden" name="tambah_pengeluaran" value="1">
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
                                <label for="tanggal_pengeluaran" class="form-label">Tanggal</label>
                                <input type="date" class="form-control" id="tanggal_pengeluaran" name="tanggal_pengeluaran" value="<?php echo date('Y-m-d'); ?>" required>
                                <div class="invalid-feedback">Tanggal tidak boleh kosong.</div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="kategori" class="form-label">Kategori</label>
                                <select class="form-select" id="kategori" name="kategori" required>
                                    <option value="" disabled selected>-- Pilih Kategori --</option>
                                    <option value="Gaji">Gaji</option>
                                    <option value="Obat & Vitamin">Obat & Vitamin</option>
                                    <option value="Operasional Lain">Operasional Lain</option>
                                </select>
                                <div class="invalid-feedback">Kategori wajib diisi.</div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="jumlah" class="form-label">Jumlah (Rp)</label>
                                <input type="tel" class="form-control" id="jumlah" name="jumlah" required autocomplete="off">
                                <div class="invalid-feedback">Jumlah tidak boleh kosong.</div>
                            </div>
                            <div class="col-md-8 mb-3">
                                <label for="keterangan" class="form-label">Keterangan</label>
                                <input type="text" class="form-control" id="keterangan" name="keterangan" placeholder="Contoh: Gaji Budi, Beli Vitachick, Perbaikan Pipa" required>
                                 <div class="invalid-feedback">Keterangan tidak boleh kosong.</div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Simpan Pengeluaran</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12">
            <h4><i class="fas fa-list-alt"></i> Riwayat Pengeluaran</h4>
            <div class="card">
                <div class="card-body table-responsive">
                    <table class="table table-striped table-hover" id="tabelPengeluaran">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Kandang</th>
                                <th>Kategori</th>
                                <th>Jumlah</th>
                                <th>Keterangan</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $pengeluaran_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo date('d M Y', strtotime($row['tanggal_pengeluaran'])); ?></td>
                                <td><?php echo htmlspecialchars($row['nama_kandang']); ?></td>
                                <td><?php echo htmlspecialchars($row['kategori']); ?></td>
                                <td class="text-end">Rp <?php echo number_format($row['jumlah']); ?></td>
                                <td><?php echo htmlspecialchars($row['keterangan']); ?></td>
                                <td class="text-center">
                                    <div class="btn-group" role="group">
                                        <button type="button" class="btn btn-sm btn-warning btn-edit" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#editModal"
                                            data-id="<?php echo $row['id_pengeluaran']; ?>"
                                            data-id-kandang="<?php echo $row['id_kandang']; ?>"
                                            data-tanggal="<?php echo $row['tanggal_pengeluaran']; ?>"
                                            data-kategori="<?php echo $row['kategori']; ?>"
                                            data-jumlah="<?php echo $row['jumlah']; ?>"
                                            data-keterangan="<?php echo htmlspecialchars($row['keterangan']); ?>"
                                            data-bs-toggle="tooltip" title="Edit Data">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="hapus.php?id=<?php echo $row['id_pengeluaran']; ?>" class="btn btn-sm btn-danger btn-hapus" data-bs-toggle="tooltip" title="Hapus Data">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
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
        <h5 class="modal-title" id="editModalLabel">Edit Data Pengeluaran</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="proses_update.php" method="POST">
          <div class="modal-body">
              <input type="hidden" id="edit_id_pengeluaran" name="id_pengeluaran">
              <div class="mb-3">
                  <label for="edit_id_kandang" class="form-label">Untuk Kandang</label>
                  <select class="form-select" id="edit_id_kandang" name="id_kandang" required>
                      <?php 
                          if ($kandang_list) mysqli_data_seek($kandang_list, 0);
                          while ($k = $kandang_list->fetch_assoc()) : 
                      ?>
                          <option value="<?php echo $k['id_kandang']; ?>"><?php echo htmlspecialchars($k['nama_kandang']); ?></option>
                      <?php endwhile; ?>
                  </select>
              </div>
              <div class="mb-3">
                  <label for="edit_tanggal" class="form-label">Tanggal</label>
                  <input type="date" class="form-control" id="edit_tanggal" name="tanggal_pengeluaran" required>
              </div>
              <div class="mb-3">
                  <label for="edit_kategori" class="form-label">Kategori</label>
                  <select class="form-select" id="edit_kategori" name="kategori" required>
                      <option value="Gaji">Gaji</option>
                      <option value="Obat & Vitamin">Obat & Vitamin</option>
                      <option value="Operasional Lain">Operasional Lain</option>
                  </select>
              </div>
              <div class="mb-3">
                  <label for="edit_jumlah" class="form-label">Jumlah (Rp)</label>
                  <input type="tel" class="form-control" id="edit_jumlah" name="jumlah" required autocomplete="off">
              </div>
              <div class="mb-3">
                  <label for="edit_keterangan" class="form-label">Keterangan</label>
                  <input type="text" class="form-control" id="edit_keterangan" name="keterangan" required>
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
    $('#tabelPengeluaran').DataTable({
        "language": { "url": "https://cdn.datatables.net/plug-ins/1.13.7/i18n/id.json" },
        "order": [[ 0, "desc" ]]
    });

    // --- Inisialisasi Tooltip Bootstrap ---
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl)
    })

    function formatNumberWithDots(input) {
        let value = input.value.replace(/[^0-9]/g, '');
        if (value === '' || value === null) {
            input.value = '';
            return;
        }
        input.value = new Intl.NumberFormat('id-ID').format(value);
    }

    $('#jumlah, #edit_jumlah').on('keyup input', function() {
        formatNumberWithDots(this);
    });

    // Logika untuk Modal Edit
    const editModal = document.getElementById('editModal');
    editModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        
        const id = button.dataset.id;
        const idKandang = button.dataset.idKandang;
        const tanggal = button.dataset.tanggal;
        const kategori = button.dataset.kategori;
        const jumlah = button.dataset.jumlah;
        const keterangan = button.dataset.keterangan;
        
        $('#edit_id_pengeluaran').val(id);
        $('#edit_id_kandang').val(idKandang);
        $('#edit_tanggal').val(tanggal);
        $('#edit_kategori').val(kategori);
        $('#edit_jumlah').val(new Intl.NumberFormat('id-ID').format(jumlah));
        $('#edit_keterangan').val(keterangan);
    });

    $('#tabelPengeluaran tbody').on('click', '.btn-hapus', function(e) {
        e.preventDefault();
        const href = $(this).attr('href');
        Swal.fire({
            title: 'Anda yakin?',
            text: "Data pengeluaran ini akan dihapus permanen!",
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