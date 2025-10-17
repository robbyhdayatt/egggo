<?php
include '../config/database.php';

// Proteksi halaman, pastikan user sudah login
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . $folder_base . '/auth/login.php');
    exit();
}

// Pastikan request adalah POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_laporan = $_POST['id_laporan'];

    // Ambil semua data dari form modal
    $ayam_masuk = $_POST['ayam_masuk'] ?: 0;
    $ayam_mati = $_POST['ayam_mati'] ?: 0;
    $ayam_afkir = $_POST['ayam_afkir'] ?: 0;
    $pakan_terpakai_kg = $_POST['pakan_terpakai_kg'] ?: 0;
    $telur_baik_kg = $_POST['telur_baik_kg'] ?: 0;
    $telur_tipis_kg = $_POST['telur_tipis_kg'] ?: 0;
    $telur_pecah_kg = $_POST['telur_pecah_kg'] ?: 0;
    $telur_terjual_kg = $_POST['telur_terjual_kg'] ?: 0;
    $harga_jual_rata2 = $_POST['harga_jual_rata2'] ?: 0;
    $gaji_harian = $_POST['gaji_harian'] ?: 0;
    $gaji_bulanan = $_POST['gaji_bulanan'] ?: 0;
    $obat_vitamin = $_POST['obat_vitamin'] ?: 0;
    $lain_lain_operasional = $_POST['lain_lain_operasional'] ?: 0;
    $keterangan_pengeluaran = $_POST['keterangan_pengeluaran'];

    // Hitung ulang pemasukan telur
    $pemasukan_telur = $telur_terjual_kg * $harga_jual_rata2;

    $sql = "UPDATE laporan_harian SET 
                ayam_masuk = ?, ayam_mati = ?, ayam_afkir = ?, 
                pakan_terpakai_kg = ?, telur_baik_kg = ?, telur_tipis_kg = ?, telur_pecah_kg = ?, 
                telur_terjual_kg = ?, harga_jual_rata2 = ?, pemasukan_telur = ?, 
                gaji_harian = ?, gaji_bulanan = ?, obat_vitamin = ?, lain_lain_operasional = ?, 
                keterangan_pengeluaran = ? 
            WHERE id_laporan = ?";
            
    $stmt = $koneksi->prepare($sql);
    $stmt->bind_param("iiidddddiiiiissi", 
        $ayam_masuk, $ayam_mati, $ayam_afkir,
        $pakan_terpakai_kg, $telur_baik_kg, $telur_tipis_kg, $telur_pecah_kg,
        $telur_terjual_kg, $harga_jual_rata2, $pemasukan_telur,
        $gaji_harian, $gaji_bulanan, $obat_vitamin, $lain_lain_operasional,
        $keterangan_pengeluaran,
        $id_laporan
    );
    
    if ($stmt->execute()) {
        // Kembali ke halaman sebelumnya. HTTP_REFERER akan mengingat URL lengkap termasuk filter.
        header('Location: ' . $_SERVER['HTTP_REFERER']);
    } else {
        echo "Gagal memperbarui data: " . $stmt->error;
    }
} else {
    // Jika diakses langsung, redirect ke index
    header('Location: index.php');
}
?>