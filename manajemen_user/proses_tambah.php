<?php
include '../templates/header.php'; 

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $password = trim($_POST['password']);
    $role = $_POST['role']; 
    $id_kandang = ($role === 'Karyawan' && !empty($_POST['id_kandang'])) ? (int)$_POST['id_kandang'] : NULL;

    if (empty($username) || empty($nama_lengkap) || empty($password) || empty($role)) {
        header('Location: index.php?status=error&msg=InputTidakLengkap'); exit();
    }
    
    // --- PERUBAHAN DI SINI ---
    if (strlen($password) < 3) { // Diubah dari 6 menjadi 3
         header('Location: index.php?status=error&msg=PasswordPendek'); exit();
    }

    // Cek username
    $stmt_check = $koneksi->prepare("SELECT id_user FROM users WHERE username = ?");
    $stmt_check->bind_param("s", $username); $stmt_check->execute(); $result_check = $stmt_check->get_result();
    if ($result_check->num_rows > 0) { $stmt_check->close(); header('Location: index.php?status=error&msg=UsernameSudahAda'); exit(); }
    $stmt_check->close();

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $koneksi->prepare("INSERT INTO users (username, password, nama_lengkap, role, id_kandang) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssi", $username, $hashed_password, $nama_lengkap, $role, $id_kandang); 

    if ($stmt->execute()) { header('Location: index.php?status=sukses_tambah'); } 
    else { error_log("Gagal tambah user: " . $stmt->error); header('Location: index.php?status=error&msg=GagalSimpan'); }
    $stmt->close();
    exit();

} else { header('Location: index.php'); exit(); }
$koneksi->close(); 
?>