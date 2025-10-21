<?php
include '../templates/header.php'; 

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_user = $_POST['id_user'];
    $username = trim($_POST['username']);
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $password = trim($_POST['password']); 
    $role = $_POST['role'];
    $id_kandang = ($role === 'Karyawan' && !empty($_POST['id_kandang'])) ? (int)$_POST['id_kandang'] : NULL;

    if (empty($id_user) || empty($username) || empty($nama_lengkap) || empty($role)) { header('Location: index.php?status=error&msg=InputTidakLengkap'); exit(); }
     
     // --- PERUBAHAN DI SINI ---
     if (!empty($password) && strlen($password) < 3) { // Diubah dari 6 menjadi 3
          header('Location: index.php?status=error&msg=PasswordPendek'); exit();
     }

    // Cek username unik (kecuali user itu sendiri)
    $stmt_check = $koneksi->prepare("SELECT id_user FROM users WHERE username = ? AND id_user != ?");
    $stmt_check->bind_param("si", $username, $id_user); $stmt_check->execute(); $result_check = $stmt_check->get_result();
    if ($result_check->num_rows > 0) { $stmt_check->close(); header('Location: index.php?status=error&msg=UsernameSudahAda'); exit(); }
    $stmt_check->close();


    // Siapkan query UPDATE
    if (!empty($password)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $koneksi->prepare("UPDATE users SET username = ?, password = ?, nama_lengkap = ?, role = ?, id_kandang = ? WHERE id_user = ?");
        $stmt->bind_param("ssssii", $username, $hashed_password, $nama_lengkap, $role, $id_kandang, $id_user);
    } else {
        $stmt = $koneksi->prepare("UPDATE users SET username = ?, nama_lengkap = ?, role = ?, id_kandang = ? WHERE id_user = ?");
        $stmt->bind_param("sssii", $username, $nama_lengkap, $role, $id_kandang, $id_user);
    }

    if ($stmt->execute()) { header('Location: index.php?status=sukses_update'); } 
    else { error_log("Gagal update user: " . $stmt->error); header('Location: index.php?status=error&msg=GagalUpdate'); }
    $stmt->close();
    exit();

} else { header('Location: index.php'); exit(); }
$koneksi->close();
?>