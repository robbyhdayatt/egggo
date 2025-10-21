<?php
include '../templates/header.php'; 

if ($current_user_role !== 'Pimpinan') {
     $_SESSION['error_message'] = "Anda tidak memiliki hak akses ke halaman ini.";
     header('Location: ' . $folder_base . '/index.php');
     exit();
}

// Ambil data kategori
$kategori_result = $koneksi->query("SELECT * FROM kategori_pengeluaran ORDER BY nama_kategori");

// Cek notifikasi
$pesan = '';
if (isset($_GET['status'])) {
     if ($_GET['status'] == 'sukses_tambah') { $pesan = "<div class='alert alert-success mt-3'>Kategori baru berhasil ditambahkan!</div>"; } 
     elseif ($_GET['status'] == 'sukses_update') { $pesan = "<div class='alert alert-success mt-3'>Kategori berhasil diperbarui!</div>"; } 
     elseif ($_GET['status'] == 'sukses_hapus') { $pesan = "<div class='alert alert-success mt-3'>Kategori berhasil dihapus!</div>"; } 
     elseif ($_GET['status'] == 'error') { $pesan = "<div class='alert alert-danger mt-3'>Terjadi kesalahan.</div>"; }
}
?>

<div class="container-fluid">
    <div class="page-header">
        <h1 class="page-title"><i class="fas fa-tags"></i> Manajemen Kategori</h1>
        <p class="page-subtitle">Kelola daftar kategori untuk menu pengeluaran.</p>
    </div>

    <?php echo $pesan; ?>

     <div class="mb-3">
         <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#tambahModal">
             <i class="fas fa-plus"></i> Tambah Kategori Baru
         </button>
     </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Daftar Kategori Pengeluaran</h6></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped" id="tabelKategori" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Nama Kategori</th>
                            <th>Status</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($kategori_result && $kategori_result->num_rows > 0): ?>
                            <?php while($kat = $kategori_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($kat['nama_kategori']); ?></td>
                                <td><span class="badge bg-<?php echo ($kat['status'] == 'Aktif') ? 'success' : 'secondary'; ?>"><?php echo $kat['status']; ?></span></td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-warning btn-edit" 
                                                data-bs-toggle="modal" data-bs-target="#editModal"
                                                data-id="<?php echo $kat['id_kategori']; ?>"
                                                data-nama="<?php echo htmlspecialchars($kat['nama_kategori']); ?>"
                                                data-status="<?php echo $kat['status']; ?>"
                                                title="Edit Kategori">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="hapus.php?id=<?php echo $kat['id_kategori']; ?>" class="btn btn-danger btn-hapus" title="Hapus Kategori">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                     </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="3" class="text-center">Belum ada data kategori.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="tambahModal" tabindex="-1" aria-labelledby="tambahModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title" id="tambahModalLabel">Tambah Kategori Baru</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form action="proses_tambah.php" method="POST" class="needs-validation" novalidate> 
        <div class="modal-body">
            <div class="mb-3">
                <label for="add_nama_kategori" class="form-label">Nama Kategori</label>
                <input type="text" class="form-control" id="add_nama_kategori" name="nama_kategori" required>
                <div class="invalid-feedback">Nama kategori wajib diisi.</div>
            </div>
            <div class="mb-3">
                 <label for="add_status" class="form-label">Status</label>
                 <select class="form-select" id="add_status" name="status" required>
                     <option value="Aktif" selected>Aktif</option>
                     <option value="Nonaktif">Nonaktif</option>
                 </select>
            </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary">Simpan</button></div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title" id="editModalLabel">Edit Kategori</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form action="proses_update.php" method="POST" class="needs-validation" novalidate> 
        <input type="hidden" id="edit_id_kategori" name="id_kategori">
        <div class="modal-body">
             <div class="mb-3">
                <label for="edit_nama_kategori" class="form-label">Nama Kategori</label>
                <input type="text" class="form-control" id="edit_nama_kategori" name="nama_kategori" required>
                 <div class="invalid-feedback">Nama kategori wajib diisi.</div>
            </div>
            <div class="mb-3">
                 <label for="edit_status" class="form-label">Status</label>
                 <select class="form-select" id="edit_status" name="status" required>
                     <option value="Aktif">Aktif</option>
                     <option value="Nonaktif">Nonaktif</option>
                 </select>
            </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary">Simpan Perubahan</button></div>
      </form>
    </div>
  </div>
</div>


<?php include '../templates/footer.php'; ?>

<script>
$(document).ready(function() {
    $('#tabelKategori').DataTable({
         "language": { "url": "https://cdn.datatables.net/plug-ins/1.13.7/i18n/id.json" },
         "order": [[ 0, "asc" ]]
    });

    // Reset modal tambah saat dibuka
     $('#tambahModal').on('show.bs.modal', function () {
       $(this).find('form')[0].reset(); 
       $(this).find('form').removeClass('was-validated'); 
    });

    // Logika untuk mengisi modal edit
    const editModal = document.getElementById('editModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            $('#edit_id_kategori').val(button.dataset.id);
            $('#edit_nama_kategori').val(button.dataset.nama);
            $('#edit_status').val(button.dataset.status);
            $(editModal).find('form').removeClass('was-validated');
        });
    }
    
     // Logika untuk konfirmasi hapus
     $('#tabelKategori tbody').on('click', '.btn-hapus', function(e) {
        e.preventDefault();
        const href = $(this).attr('href');
        Swal.fire({
            title: 'Anda yakin?',
            text: "Data kategori ini akan dihapus! (Pengeluaran yang sudah ada tidak akan terhapus)",
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

    // Validasi form Bootstrap
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