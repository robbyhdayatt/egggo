<?php
include '../templates/header.php'; // Header sudah otomatis cek role Pimpinan

// Ambil data users untuk ditampilkan
$users_result = $koneksi->query("
    SELECT u.id_user, u.username, u.nama_lengkap, u.role, u.id_kandang, k.nama_kandang 
    FROM users u
    LEFT JOIN kandang k ON u.id_kandang = k.id_kandang
    ORDER BY u.username
");

// Ambil daftar kandang aktif untuk dropdown
$kandang_list = $koneksi->query("SELECT id_kandang, nama_kandang FROM kandang WHERE status = 'Aktif' ORDER BY nama_kandang");

// Cek notifikasi
$pesan = '';
if (isset($_GET['status'])) {
     if ($_GET['status'] == 'sukses_tambah') { $pesan = "<div class='alert alert-success mt-3'>User baru berhasil ditambahkan!</div>"; } 
     elseif ($_GET['status'] == 'sukses_update') { $pesan = "<div class='alert alert-success mt-3'>Data user berhasil diperbarui!</div>"; } 
     elseif ($_GET['status'] == 'sukses_hapus') { $pesan = "<div class='alert alert-success mt-3'>User berhasil dihapus!</div>"; } 
     elseif ($_GET['status'] == 'error') { 
         $msg = $_GET['msg'] ?? 'Gagal';
         $pesan_error = "Terjadi kesalahan.";
         if ($msg == 'UsernameSudahAda') $pesan_error = "Username sudah digunakan.";
         if ($msg == 'InputTidakLengkap') $pesan_error = "Semua field wajib diisi.";
         if ($msg == 'PasswordPendek') $pesan_error = "Password terlalu pendek (minimal 3 karakter)."; // Pesan error baru
         if ($msg == 'HapusDiriSendiri') $pesan_error = "Anda tidak dapat menghapus akun Anda sendiri.";
         if ($msg == 'HapusPimpinanTerakhir') $pesan_error = "Tidak dapat menghapus Pimpinan terakhir.";
         $pesan = "<div class='alert alert-danger mt-3'>Error: " . $pesan_error ."</div>"; 
     }
}

?>

<div class="container-fluid">
    <div class="page-header">
        <h1 class="page-title"><i class="fas fa-users-cog"></i> Manajemen User</h1>
        <p class="page-subtitle">Tambah, edit, atau hapus pengguna sistem.</p>
    </div>

     <?php echo $pesan; ?>

     <div class="mb-3">
         <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#tambahUserModal">
             <i class="fas fa-plus"></i> Tambah User Baru
         </button>
     </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Daftar Pengguna</h6></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped" id="tabelUser" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Nama Lengkap</th>
                            <th>Role</th>
                            <th>Kandang Ditugaskan</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($users_result && $users_result->num_rows > 0): ?>
                            <?php while($user = $users_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['nama_lengkap']); ?></td>
                                <td><span class="badge bg-<?php echo ($user['role'] == 'Pimpinan') ? 'primary' : 'secondary'; ?>"><?php echo $user['role']; ?></span></td>
                                <td>
                                    <?php 
                                        if ($user['role'] == 'Karyawan' && $user['id_kandang']) {
                                            echo htmlspecialchars($user['nama_kandang'] ?? 'Kandang tidak ditemukan/nonaktif');
                                        } elseif ($user['role'] == 'Karyawan' && !$user['id_kandang']) {
                                            echo '<span class="text-muted">Belum ditugaskan</span>';
                                        } else { // Pimpinan
                                            echo '<span class="text-info">Semua Akses</span>';
                                        }
                                    ?>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-warning btn-edit-user" 
                                                data-bs-toggle="modal" data-bs-target="#editUserModal"
                                                data-id="<?php echo $user['id_user']; ?>"
                                                data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                                data-nama="<?php echo htmlspecialchars($user['nama_lengkap']); ?>"
                                                data-role="<?php echo $user['role']; ?>"
                                                data-id-kandang="<?php echo $user['id_kandang']; ?>" 
                                                title="Edit User">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                         <?php if ($_SESSION['user_id'] != $user['id_user']): ?>
                                            <a href="hapus.php?id=<?php echo $user['id_user']; ?>" class="btn btn-danger btn-hapus-user" title="Hapus User">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                         <?php endif; ?>
                                     </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center">Belum ada data user.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="tambahUserModal" tabindex="-1" aria-labelledby="tambahUserModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title" id="tambahUserModalLabel">Tambah User Baru</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form action="proses_tambah.php" method="POST" class="needs-validation" novalidate> 
        <div class="modal-body">
            <div class="mb-3">
                <label for="add_username" class="form-label">Username</label>
                <input type="text" class="form-control" id="add_username" name="username" required>
                <div class="invalid-feedback">Username wajib diisi.</div>
            </div>
            <div class="mb-3">
                <label for="add_nama_lengkap" class="form-label">Nama Lengkap</label>
                <input type="text" class="form-control" id="add_nama_lengkap" name="nama_lengkap" required>
                 <div class="invalid-feedback">Nama lengkap wajib diisi.</div>
            </div>
             <div class="mb-3">
                <label for="add_password" class="form-label">Password</label>
                <input type="password" class="form-control" id="add_password" name="password" required minlength="3">
                 <div class="invalid-feedback">Password wajib diisi (minimal 3 karakter).</div>
            </div>
            <div class="mb-3">
                 <label for="add_role" class="form-label">Role</label>
                 <select class="form-select" id="add_role" name="role" required>
                     <option value="Karyawan" selected>Karyawan</option>
                     <option value="Pimpinan">Pimpinan</option>
                 </select>
                  <div class="invalid-feedback">Role wajib dipilih.</div>
            </div>
            <div class="mb-3" id="add_kandang_div">
                 <label for="add_id_kandang" class="form-label">Tugaskan ke Kandang</label>
                 <select class="form-select" id="add_id_kandang" name="id_kandang">
                     <option value="">-- Tidak Ditugaskan --</option>
                      <?php 
                          if ($kandang_list) mysqli_data_seek($kandang_list, 0); 
                          while ($k = $kandang_list->fetch_assoc()) : 
                      ?>
                          <option value="<?php echo $k['id_kandang']; ?>"><?php echo htmlspecialchars($k['nama_kandang']); ?></option>
                      <?php endwhile; ?>
                 </select>
                 <small class="text-muted">Kosongkan jika role Pimpinan.</small>
            </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary">Simpan User</button></div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title" id="editUserModalLabel">Edit User</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form action="proses_update.php" method="POST" class="needs-validation" novalidate> 
        <input type="hidden" id="edit_id_user" name="id_user">
        <div class="modal-body">
             <div class="mb-3">
                <label for="edit_username" class="form-label">Username</label>
                <input type="text" class="form-control" id="edit_username" name="username" required>
                 <div class="invalid-feedback">Username wajib diisi.</div>
            </div>
            <div class="mb-3">
                <label for="edit_nama_lengkap" class="form-label">Nama Lengkap</label>
                <input type="text" class="form-control" id="edit_nama_lengkap" name="nama_lengkap" required>
                 <div class="invalid-feedback">Nama lengkap wajib diisi.</div>
            </div>
             <div class="mb-3">
                <label for="edit_password" class="form-label">Password Baru</label>
                <input type="password" class="form-control" id="edit_password" name="password" minlength="3" aria-describedby="passwordHelp">
                 <div id="passwordHelp" class="form-text">Kosongkan jika tidak ingin mengubah password. Minimal 3 karakter jika diisi.</div>
            </div>
            <div class="mb-3">
                 <label for="edit_role" class="form-label">Role</label>
                 <select class="form-select" id="edit_role" name="role" required>
                     <option value="Karyawan">Karyawan</option>
                     <option value="Pimpinan">Pimpinan</option>
                 </select>
                  <div class="invalid-feedback">Role wajib dipilih.</div>
            </div>
            <div class="mb-3" id="edit_kandang_div">
                 <label for="edit_id_kandang" class="form-label">Tugaskan ke Kandang</label>
                 <select class="form-select" id="edit_id_kandang" name="id_kandang">
                      <option value="">-- Tidak Ditugaskan --</option>
                      <?php 
                          if ($kandang_list) mysqli_data_seek($kandang_list, 0); 
                          while ($k = $kandang_list->fetch_assoc()) : 
                      ?>
                          <option value="<?php echo $k['id_kandang']; ?>"><?php echo htmlspecialchars($k['nama_kandang']); ?></option>
                      <?php endwhile; ?>
                 </select>
                 <small class="text-muted">Kosongkan jika role Pimpinan.</small>
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
    $('#tabelUser').DataTable({
         "language": { "url": "https://cdn.datatables.net/plug-ins/1.13.7/i18n/id.json" },
         "order": [[ 0, "asc" ]]
    });

    // Fungsi untuk menampilkan/menyembunyikan dropdown kandang
    function toggleKandangDropdown(roleSelect, kandangDiv) {
        if ($(roleSelect).val() === 'Karyawan') {
            $(kandangDiv).slideDown();
        } else {
            $(kandangDiv).slideUp();
            $(kandangDiv).find('select').val(''); 
        }
    }

    // Terapkan pada modal tambah
    $('#add_role').on('change', function() {
        toggleKandangDropdown(this, '#add_kandang_div');
    });
    $('#tambahUserModal').on('show.bs.modal', function () { // 'show.bs.modal' agar reset sebelum muncul
       $(this).find('form')[0].reset(); 
       $(this).find('form').removeClass('was-validated'); 
       toggleKandangDropdown('#add_role', '#add_kandang_div'); // Set state awal
    });

    // Terapkan pada modal edit
    $('#edit_role').on('change', function() {
        toggleKandangDropdown(this, '#edit_kandang_div');
    });

    // Logika untuk mengisi modal edit
    const editUserModal = document.getElementById('editUserModal');
    if (editUserModal) {
        editUserModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const idKandang = button.dataset.idKandang || ""; 
            
            $('#edit_id_user').val(button.dataset.id);
            $('#edit_username').val(button.dataset.username);
            $('#edit_nama_lengkap').val(button.dataset.nama);
            $('#edit_role').val(button.dataset.role);
            $('#edit_id_kandang').val(idKandang); 
            $('#edit_password').val(''); 
            
            toggleKandangDropdown('#edit_role', '#edit_kandang_div'); 
            $(editUserModal).find('form').removeClass('was-validated');
        });
    }
    
     // Logika untuk konfirmasi hapus
     $('#tabelUser tbody').on('click', '.btn-hapus-user', function(e) {
        e.preventDefault();
        const href = $(this).attr('href');
        Swal.fire({
            title: 'Anda yakin?',
            text: "Data user ini akan dihapus permanen!",
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