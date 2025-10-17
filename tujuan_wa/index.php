<?php
include '../templates/header.php';
global $folder_base;

$pesan = '';

// Logika untuk memproses UPDATE data saat form disubmit
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nowa1 = $_POST['nowa1'];
    $nowa2 = $_POST['nowa2'];
    $nowa3 = $_POST['nowa3'];
    $nowa4 = $_POST['nowa4'];

    // Karena hanya ada satu baris, kita bisa update tanpa WHERE clause yang kompleks
    $stmt = $koneksi->prepare("UPDATE tujuanwa SET nowa1 = ?, nowa2 = ?, nowa3 = ?, nowa4 = ?");
    $stmt->bind_param("ssss", $nowa1, $nowa2, $nowa3, $nowa4);
    
    if ($stmt->execute()) {
        $pesan = "<div class='alert alert-success'>Nomor tujuan WhatsApp berhasil diperbarui!</div>";
    } else {
        $pesan = "<div class='alert alert-danger'>Gagal memperbarui data: " . $stmt->error . "</div>";
    }
}

// Ambil data nomor WA yang ada saat ini dari database untuk ditampilkan di form
$data_wa_result = $koneksi->query("SELECT * FROM tujuanwa LIMIT 1");
$data_wa = $data_wa_result->fetch_assoc();
?>

<div class="container-fluid">
    <div class="page-header">
        <h1 class="page-title">Pengaturan Tujuan Notifikasi WhatsApp</h1>
        <p class="page-subtitle">Atur nomor-nomor yang akan menerima notifikasi dari sistem.</p>
    </div>

    <?php echo $pesan; ?>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h4><i class="fab fa-whatsapp"></i> Daftar Nomor Tujuan</h4>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label for="nowa1" class="form-label">Nomor WhatsApp 1</label>
                            <input type="text" class="form-control" id="nowa1" name="nowa1" value="<?php echo htmlspecialchars($data_wa['nowa1']); ?>" placeholder="Contoh: 6281234567890">
                        </div>
                        <div class="mb-3">
                            <label for="nowa2" class="form-label">Nomor WhatsApp 2</label>
                            <input type="text" class="form-control" id="nowa2" name="nowa2" value="<?php echo htmlspecialchars($data_wa['nowa2']); ?>" placeholder="Kosongkan jika tidak ada">
                        </div>
                        <div class="mb-3">
                            <label for="nowa3" class="form-label">Nomor WhatsApp 3</label>
                            <input type="text" class="form-control" id="nowa3" name="nowa3" value="<?php echo htmlspecialchars($data_wa['nowa3']); ?>" placeholder="Kosongkan jika tidak ada">
                        </div>
                        <div class="mb-3">
                            <label for="nowa4" class="form-label">Nomor WhatsApp 4</label>
                            <input type="text" class="form-control" id="nowa4" name="nowa4" value="<?php echo htmlspecialchars($data_wa['nowa4']); ?>" placeholder="Kosongkan jika tidak ada">
                        </div>
                        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
             <div class="card">
                <div class="card-header">
                    <h4><i class="fas fa-info-circle"></i> Petunjuk</h4>
                </div>
                 <div class="card-body">
                     <p>Masukkan nomor WhatsApp dengan format internasional, diawali dengan kode negara (misalnya `62` untuk Indonesia) dan tanpa tanda `+` atau spasi.</p>
                     <p><strong>Contoh Benar:</strong> `6281234567890`</p>
                     <p class="text-muted">Fitur notifikasi WhatsApp akan menggunakan nomor-nomor ini sebagai tujuan pengiriman pesan otomatis dari sistem (jika fitur tersebut diaktifkan di masa depan).</p>
                 </div>
             </div>
        </div>
    </div>
</div>

<?php include '../templates/footer.php'; ?>

</body>
</html>