<?php
include '../templates/header.php';

?>

<div class="container-fluid">
    <div class="page-header">
        <h1 class="page-title"><i class="fas fa-users-cog"></i> Manajemen User</h1>
        <p class="page-subtitle">Tambah, edit, atau hapus pengguna yang dapat mengakses sistem.</p>
    </div>

    <div class="card bg-danger-subtle text-danger border-0 shadow">
        <div class="card-body text-center p-5">
            <h1 class="display-4 fw-bold mb-4">AKSES DITOLAK SEMENTARA</h1>
            <p class="lead mb-4">
                <i class="fas fa-user-lock fa-3x mb-3"></i><br>
                Untuk alasan keamanan dan peningkatan sistem, akses ke manajemen pengguna sedang kami batasi.<br>
                Hanya administrator tertentu yang dapat mengaksesnya saat ini.
            </p>

            <div class="d-flex justify-content-center align-items-center mb-4">
                <div class="spinner-grow text-danger me-3" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <strong>Kami bekerja untuk mengembalikan fitur ini secepatnya.</strong>
            </div>

            <p class="text-muted">Jika Anda memiliki pertanyaan, silakan hubungi dukungan.</p>
        </div>
    </div>
</div>

<?php include '../templates/footer.php'; ?>

</body>

</html>