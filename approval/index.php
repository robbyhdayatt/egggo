<?php
include '../templates/header.php'; // Sesuaikan path jika perlu
global $current_user_role, $folder_base; // Ambil role dan folder base

// Pastikan hanya Pimpinan yang bisa akses
if ($current_user_role !== 'Pimpinan') {
    $_SESSION['error_message'] = "Anda tidak memiliki hak akses ke halaman approval.";
    header('Location: ' . $folder_base . '/index.php');
    exit();
}

// Cek notifikasi
$pesan = '';
if (isset($_GET['status'])) {
    if ($_GET['status'] == 'approve_sukses') {
          $pesan = "<div class='alert alert-success mt-3'>Permintaan berhasil disetujui!</div>";
    } elseif ($_GET['status'] == 'approve_gagal') {
          $pesan = "<div class='alert alert-danger mt-3'>Gagal menyetujui permintaan. Silakan coba lagi.</div>";
    } elseif ($_GET['status'] == 'reject_sukses') { // <-- TAMBAHKAN INI
          $pesan = "<div class='alert alert-warning mt-3'>Permintaan berhasil ditolak.</div>";
    } elseif ($_GET['status'] == 'reject_gagal') { // <-- TAMBAHKAN INI
          $pesan = "<div class='alert alert-danger mt-3'>Gagal menolak permintaan. Silakan coba lagi.</div>";
    }
}


// Ambil data permintaan yang pending (requested tapi belum approved)
$query_requests = "
    SELECT
        lh.id_laporan,
        lh.tanggal,
        k.nama_kandang,
        u.username as requested_by_username,
        lh.edit_requested_at
    FROM laporan_harian lh
    JOIN users u ON lh.edit_requested_by = u.id_user
    JOIN kandang k ON lh.id_kandang = k.id_kandang
    WHERE lh.edit_requested_at IS NOT NULL
      AND lh.edit_approved_at IS NULL
    ORDER BY lh.edit_requested_at ASC
";
$result_requests = $koneksi->query($query_requests);

?>

<div class="container-fluid">
    <div class="page-header">
        <h1 class="page-title">Approval Edit Laporan</h1>
        <p class="page-subtitle">Daftar permintaan approval edit laporan harian dari Karyawan.</p>
    </div>

    <?php echo $pesan; // Tampilkan notifikasi ?>

    <div class="card">
        <div class="card-header">Permintaan Approval Pending</div>
        <div class="card-body">
            <?php if ($result_requests && $result_requests->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover" id="tabelApproval">
                    <thead class="text-center table-light">
                        <tr>
                            <th>Tanggal Laporan</th>
                            <th>Kandang</th>
                            <th>Diminta Oleh</th>
                            <th>Waktu Permintaan</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($req = $result_requests->fetch_assoc()): ?>
                        <tr>
                            <td class="text-center"><?php echo date('d M Y', strtotime($req['tanggal'])); ?></td>
                            <td><?php echo htmlspecialchars($req['nama_kandang']); ?></td>
                            <td><?php echo htmlspecialchars($req['requested_by_username']); ?></td>
                            <td class="text-center"><?php echo date('d M Y H:i', strtotime($req['edit_requested_at'])); ?></td>
                            <td class="text-center">
                                <form action="proses_approval.php" method="POST" style="display: inline-block;" class="form-approve">
                                    <input type="hidden" name="id_laporan" value="<?php echo $req['id_laporan']; ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="btn btn-sm btn-success" data-bs-toggle="tooltip" title="Setujui Permintaan">
                                        <i class="fas fa-check"></i> Setujui
                                    </button>
                                </form>

                                <form action="proses_approval.php" method="POST" style="display: inline-block;" class="form-reject">
                                    <input type="hidden" name="id_laporan" value="<?php echo $req['id_laporan']; ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <button type="submit" class="btn btn-sm btn-danger btn-tolak" data-bs-toggle="tooltip" title="Tolak Permintaan">
                                        <i class="fas fa-times"></i> Tolak
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="alert alert-info">Tidak ada permintaan approval yang pending saat ini.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../templates/footer.php'; // Sesuaikan path jika perlu ?>

<script>
$(document).ready(function() {
    $('#tabelApproval').DataTable({
        "language": { "url": "https://cdn.datatables.net/plug-ins/1.13.7/i18n/id.json" },
        "order": [[ 3, "asc" ]]
    });

    // Inisialisasi Tooltip
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // --- MODIFIKASI: Tambahkan Konfirmasi SweetAlert sebelum tolak ---
    // Gunakan event delegation karena tabel mungkin di-render ulang oleh DataTables
    $('#tabelApproval tbody').on('click', '.btn-tolak', function(e) {
        e.preventDefault(); // Mencegah form submit langsung
        const form = $(this).closest('form'); // Ambil form terdekat

        Swal.fire({
             title: 'Anda yakin?',
             text: "Permintaan approval ini akan ditolak dan dibatalkan.",
             icon: 'warning',
             showCancelButton: true,
             confirmButtonColor: '#d33',
             cancelButtonColor: '#6c757d', // secondary
             confirmButtonText: 'Ya, tolak!',
             cancelButtonText: 'Batal'
        }).then((result) => {
             if (result.isConfirmed) {
                 form.submit(); // Lanjutkan submit form jika dikonfirmasi
             }
        });
    });
    // --- AKHIR MODIFIKASI ---
});
</script>

</body>
</html>