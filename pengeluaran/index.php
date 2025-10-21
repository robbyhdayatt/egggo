<?php
include '../templates/header.php';

global $current_user_role, $current_assigned_kandang_id;

$pesan = '';
if (isset($_GET['status'])) {
    if ($_GET['status'] == 'sukses_tambah') { $pesan = "<div class='alert alert-success mt-3'>Data pengeluaran berhasil disimpan!</div>"; }
    elseif ($_GET['status'] == 'sukses_update') { $pesan = "<div class='alert alert-success mt-3'>Data pengeluaran berhasil diperbarui!</div>"; } 
    elseif ($_GET['status'] == 'sukses_hapus') { $pesan = "<div class='alert alert-success mt-3'>Data pengeluaran berhasil dihapus!</div>"; }
    elseif ($_GET['status'] == 'error') { $pesan = "<div class='alert alert-danger mt-3'>Terjadi kesalahan.</div>"; }
}

$kandang_query = "SELECT id_kandang, nama_kandang FROM kandang WHERE status = 'Aktif'";
if ($current_user_role === 'Karyawan' && $current_assigned_kandang_id) {
    $kandang_query .= " AND id_kandang = " . (int)$current_assigned_kandang_id;
}
$kandang_query .= " ORDER BY nama_kandang";
$kandang_list = $koneksi->query($kandang_query);
$kategori_list = $koneksi->query("SELECT * FROM kategori_pengeluaran WHERE status = 'Aktif' ORDER BY nama_kategori");

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah_pengeluaran'])) {
    $id_kandang = $_POST['id_kandang'];
    $tanggal_pengeluaran = $_POST['tanggal_pengeluaran'];
    $id_kategori = (int)$_POST['id_kategori']; 
    $jumlah = str_replace('.', '', $_POST['jumlah']);
    $keterangan = $_POST['keterangan'];

    if ($current_user_role === 'Karyawan' && $id_kandang != $current_assigned_kandang_id) {
         $pesan = "<div class='alert alert-danger mt-3'>Error: Anda tidak berhak menginput data untuk kandang ini.</div>";
    } else {
        $stmt = $koneksi->prepare("INSERT INTO pengeluaran (id_kandang, tanggal_pengeluaran, id_kategori, jumlah, keterangan) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("isiis", $id_kandang, $tanggal_pengeluaran, $id_kategori, $jumlah, $keterangan); 
        if ($stmt->execute()) {
            header('Location: index.php?status=sukses_tambah'); exit();
        } else {
            $pesan = "<div class='alert alert-danger mt-3'>Gagal menyimpan data: " . $stmt->error . "</div>";
        }
    }
}

$pengeluaran_query = "
    SELECT p.*, k.nama_kandang, kat.nama_kategori 
    FROM pengeluaran p
    JOIN kandang k ON p.id_kandang = k.id_kandang
    LEFT JOIN kategori_pengeluaran kat ON p.id_kategori = kat.id_kategori
";
if ($current_user_role === 'Karyawan' && $current_assigned_kandang_id) {
    $pengeluaran_query .= " WHERE p.id_kandang = " . (int)$current_assigned_kandang_id;
}
$pengeluaran_query .= " ORDER BY p.tanggal_pengeluaran DESC, p.id_pengeluaran DESC";
$pengeluaran_result = $koneksi->query($pengeluaran_query);
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
                         <?php if ($current_user_role === 'Karyawan' && $current_assigned_kandang_id): ?>
                            <input type="hidden" name="id_kandang" value="<?php echo $current_assigned_kandang_id; ?>" />
                        <?php endif; ?>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="id_kandang" class="form-label">Untuk Kandang</label>
                                <select class="form-select" id="id_kandang" name="id_kandang" required <?php echo ($current_user_role === 'Karyawan') ? 'disabled' : ''; ?>>
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
                            <div class="col-md-4 mb-3">
                                <label for="tanggal_pengeluaran" class="form-label">Tanggal</label>
                                <input type="date" class="form-control" id="tanggal_pengeluaran" name="tanggal_pengeluaran" value="<?php echo date('Y-m-d'); ?>" required>
                                <div class="invalid-feedback">Tanggal tidak boleh kosong.</div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="id_kategori" class="form-label">Kategori</label>
                                <select class="form-select" id="id_kategori" name="id_kategori" required>
                                    <option value="" disabled selected>-- Pilih Kategori --</option>
                                    <?php 
                                        if ($kategori_list) mysqli_data_seek($kategori_list, 0);
                                        while ($kat = $kategori_list->fetch_assoc()) : 
                                    ?>
                                        <option value="<?php echo $kat['id_kategori']; ?>"><?php echo htmlspecialchars($kat['nama_kategori']); ?></option>
                                    <?php endwhile; ?>
                                </select>
                                <div class="invalid-feedback">Kategori wajib diisi.</div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="jumlah" class="form-label">Jumlah (Rp)</label>
                                <input type="tel" class="form-control clear-on-focus" id="jumlah" name="jumlah" value="0" required autocomplete="off">
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
                                <?php if ($current_user_role === 'Pimpinan'): ?>
                                <th>Kandang</th>
                                <?php endif; ?>
                                <th>Kategori</th>
                                <th>Jumlah</th>
                                <th>Keterangan</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($pengeluaran_result): ?>
                            <?php while($row = $pengeluaran_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo date('d M Y', strtotime($row['tanggal_pengeluaran'])); ?></td>
                                <?php if ($current_user_role === 'Pimpinan'): ?>
                                <td><?php echo htmlspecialchars($row['nama_kandang']); ?></td>
                                <?php endif; ?>
                                <td><?php echo htmlspecialchars($row['nama_kategori'] ?? '<span class="text-muted">N/A</span>'); ?></td>
                                <td class="text-end">Rp <?php echo number_format($row['jumlah'], 0, ',', '.'); ?></td>
                                <td><?php echo htmlspecialchars($row['keterangan']); ?></td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-warning btn-edit" 
                                            data-bs-toggle="modal" data-bs-target="#editModal"
                                            data-id="<?php echo $row['id_pengeluaran']; ?>"
                                            data-id-kandang="<?php echo $row['id_kandang']; ?>"
                                            data-tanggal="<?php echo $row['tanggal_pengeluaran']; ?>"
                                            data-id-kategori="<?php echo $row['id_kategori']; ?>"
                                            data-jumlah="<?php echo $row['jumlah']; ?>"
                                            data-keterangan="<?php echo htmlspecialchars($row['keterangan']); ?>"
                                            data-bs-toggle="tooltip" title="Edit Data">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="hapus.php?id=<?php echo $row['id_pengeluaran']; ?>" class="btn btn-danger btn-hapus" data-bs-toggle="tooltip" title="Hapus Data">
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

<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editModalLabel">Edit Data Pengeluaran</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="proses_update.php" method="POST" class="needs-validation" novalidate>
          <div class="modal-body">
              <input type="hidden" id="edit_id_pengeluaran" name="id_pengeluaran">
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
                  <label for="edit_tanggal" class="form-label">Tanggal</label>
                  <input type="date" class="form-control" id="edit_tanggal" name="tanggal_pengeluaran" required>
              </div>
              <div class="mb-3">
                  <label for="edit_id_kategori" class="form-label">Kategori</label>
                  <select class="form-select" id="edit_id_kategori" name="id_kategori" required>
                      <option value="" disabled>-- Pilih Kategori --</option>
                      <?php 
                          if ($kategori_list) mysqli_data_seek($kategori_list, 0);
                          while ($kat = $kategori_list->fetch_assoc()) : 
                      ?>
                          <option value="<?php echo $kat['id_kategori']; ?>"><?php echo htmlspecialchars($kat['nama_kategori']); ?></option>
                      <?php endwhile; ?>
                  </select>
                  </div>
              <div class="mb-3">
                  <label for="edit_jumlah" class="form-label">Jumlah (Rp)</label>
                  <input type="tel" class="form-control clear-on-focus" id="edit_jumlah" name="jumlah" required autocomplete="off">
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

    // Inisialisasi Tooltip
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl)
    });

    // 1. Fungsi format angka
    function formatNumberWithDots(input) {
        // Hapus semua karakter non-digit
        let value = input.value.replace(/[^0-9]/g, '');
        if (value === '' || value === null) {
            input.value = '';
            return;
        }
        // Format angka dengan titik sebagai pemisah ribuan
        input.value = new Intl.NumberFormat('id-ID').format(value);
    }
    
    // 2. Fungsi unformat angka
    function unformatNumber(value) {
        if(typeof value !== 'string') { value = String(value); }
        return value.replace(/[^0-9]/g, ''); // Hapus semua titik/non-angka
    }

    // 3. Terapkan listener ke input #jumlah dan #edit_jumlah
    $('#jumlah, #edit_jumlah').on('keyup input', function() { 
        formatNumberWithDots(this); 
    });

    // 4. Tambahkan listener clear on focus
     $('.clear-on-focus').on('focus', function() {
        if ($(this).val() == '0') {
            $(this).val('');
        }
    });
    $('.clear-on-focus').on('blur', function() {
        if ($(this).val() === '') {
            $(this).val('0');
            formatNumberWithDots(this); // Format '0'
        }
    });


    // Logika untuk Modal Edit
    const editModal = document.getElementById('editModal');
    editModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        $('#edit_id_pengeluaran').val(button.dataset.id);
        $('#edit_id_kandang').val(button.dataset.idKandang); 
        $('#edit_tanggal').val(button.dataset.tanggal);
        $('#edit_id_kategori').val(button.dataset.idKategori); 
        $('#edit_jumlah').val(new Intl.NumberFormat('id-ID').format(button.dataset.jumlah));
        $('#edit_keterangan').val(button.dataset.keterangan);
        $(editModal).find('form').removeClass('was-validated');
    });

    // Logika untuk konfirmasi hapus
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
            if (result.isConfirmed) { window.location.href = href; }
        });
     });

    // Validasi form bootstrap
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => { 
        form.addEventListener('submit', event => {
            // Hapus format titik dari input jumlah SEBELUM submit
            $(form).find('#jumlah, #edit_jumlah').each(function() {
                $(this).val(unformatNumber($(this).val()));
            });

            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');

            setTimeout(() => {
                let addJumlahInput = document.getElementById('jumlah');
                if (addJumlahInput) formatNumberWithDots(addJumlahInput);
                let editJumlahInput = document.getElementById('edit_jumlah');
                if (editJumlahInput) formatNumberWithDots(editJumlahInput);
            }, 100);
        }, false);
    });
});
</script>

</body>
</html>