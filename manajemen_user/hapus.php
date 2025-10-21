<?php
include '../templates/header.php'; 

if (isset($_GET['id'])) {
    $id_user_to_delete = $_GET['id'];

    if (!filter_var($id_user_to_delete, FILTER_VALIDATE_INT)) {
         header('Location: index.php?status=error&msg=IdTidakValid');
         exit();
    }

    if ($id_user_to_delete == $_SESSION['user_id']) {
         header('Location: index.php?status=error&msg=HapusDiriSendiri');
         exit();
    }

     if ($user_role === 'Pimpinan') {
         $stmt_check_admin = $koneksi->prepare("SELECT role FROM users WHERE id_user = ?");
         $stmt_check_admin->bind_param("i", $id_user_to_delete);
         $stmt_check_admin->execute();
         $role_to_delete = $stmt_check_admin->get_result()->fetch_assoc()['role'] ?? null;
         $stmt_check_admin->close();

         if ($role_to_delete === 'Pimpinan') {
             $result_count = $koneksi->query("SELECT COUNT(*) as total_pimpinan FROM users WHERE role = 'Pimpinan'");
             $total_pimpinan = $result_count->fetch_assoc()['total_pimpinan'] ?? 0;
             if ($total_pimpinan <= 1) {
                  header('Location: index.php?status=error&msg=HapusPimpinanTerakhir');
                  exit();
             }
         }
     }

    // Siapkan query DELETE
    $stmt = $koneksi->prepare("DELETE FROM users WHERE id_user = ?");
    $stmt->bind_param("i", $id_user_to_delete);

    // Eksekusi query
    if ($stmt->execute()) {
        header('Location: index.php?status=sukses_hapus');
        exit();
    } else {
         error_log("Gagal hapus user: " . $stmt->error);
         header('Location: index.php?status=error&msg=GagalHapus');
         exit();
    }
    $stmt->close();

} else {
    header('Location: index.php');
    exit();
}

$koneksi->close();
?>