<?php
include '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Ambil semua data dari form
    $id_laporan = $_POST['id_laporan'];
    $ayam_masuk = (int)str_replace('.', '', $_POST['ayam_masuk']);
    $ayam_mati = (int)str_replace('.', '', $_POST['ayam_mati']);
    $ayam_afkir = (int)str_replace('.', '', $_POST['ayam_afkir']);
    $pakan_terpakai_kg = (float)str_replace(',', '.', $_POST['pakan_terpakai_kg']);
    $telur_baik_kg = (float)str_replace(',', '.', $_POST['telur_baik_kg']);
    $telur_tipis_kg = (float)str_replace(',', '.', $_POST['telur_tipis_kg']);
    $telur_pecah_kg = (float)str_replace(',', '.', $_POST['telur_pecah_kg']);
    $telur_terjual_kg = (float)str_replace(',', '.', $_POST['telur_terjual_kg']);
    $harga_jual_rata2 = (int)str_replace('.', '', $_POST['harga_jual_rata2']);

    // Hitung ulang pemasukan telur
    $pemasukan_telur = $telur_terjual_kg * $harga_jual_rata2;

    $stmt = $koneksi->prepare("
        UPDATE laporan_harian 
        SET 
            ayam_masuk = ?, 
            ayam_mati = ?, 
            ayam_afkir = ?, 
            pakan_terpakai_kg = ?, 
            telur_baik_kg = ?, 
            telur_tipis_kg = ?, 
            telur_pecah_kg = ?, 
            telur_terjual_kg = ?, 
            harga_jual_rata2 = ?, 
            pemasukan_telur = ?
        WHERE id_laporan = ?
    ");
    $stmt->bind_param(
        "iiidddddidi",
        $ayam_masuk,
        $ayam_mati,
        $ayam_afkir,
        $pakan_terpakai_kg,
        $telur_baik_kg,
        $telur_tipis_kg,
        $telur_pecah_kg,
        $telur_terjual_kg,
        $harga_jual_rata2,
        $pemasukan_telur,
        $id_laporan
    );

    if ($stmt->execute()) {
        // Redirect kembali ke halaman riwayat dengan parameter filter yang sama
        // Ambil parameter dari referer URL
        $redirect_url = $_SERVER['HTTP_REFERER'] ?? 'index.php';
        header('Location: ' . $redirect_url . '&status=sukses_update');
        exit();
    } else {
        die("Gagal memperbarui data: " . $stmt->error);
    }
} else {
    header('Location: index.php');
}
?>