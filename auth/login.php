<?php
// Logika PHP untuk proses login (tetap sama)
include '../config/database.php';
$error_message = '';

if (isset($_SESSION['user_id'])) {
    header('Location: ' . $folder_base . '/index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!empty($_POST['username']) && !empty($_POST['password'])) {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);

        $stmt = $koneksi->prepare("SELECT id_user, username, password FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id_user'];
                $_SESSION['username'] = $user['username'];
                header('Location: ' . $folder_base . '/index.php');
                exit();
            }
        }
        $error_message = "Username atau password yang Anda masukkan salah!";
    } else {
        $error_message = "Username dan password tidak boleh kosong!";
    }
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - EggGo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <style>
        /* ... (Semua kode CSS dari sebelumnya tetap sama) ... */
        html, body { height: 100%; overflow: hidden; }
        body { font-family: 'Poppins', sans-serif; background-color: #f8f9fa; }
        .login-wrapper { min-height: 100vh; }
        .image-container { background: url('https://images.unsplash.com/photo-1587520061599-2c0a18aa53e2?q=80&w=1887&auto=format&fit=crop') no-repeat center center; background-size: cover; position: relative; }
        .image-container::before { content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(44, 62, 80, 0.6); }
        .image-text { position: relative; z-index: 10; color: white; animation: float 6s ease-in-out infinite; }
        .form-container { display: flex; align-items: center; justify-content: center; }
        .form-box { width: 100%; max-width: 400px; padding: 2rem; animation: fadeIn 0.8s ease-out forwards; }
        .form-box .logo { font-size: 2rem; font-weight: 700; color: #2c3e50; margin-bottom: 0.5rem; }
        .form-box .subtitle { color: #858796; margin-bottom: 2rem; }
        .form-control { border-radius: 0.5rem; padding: 0.9rem 1rem; border-color: #d1d3e2; box-shadow: none; }
        .form-control:focus { border-color: #4e73df; box-shadow: 0 0 0 0.25rem rgba(78, 115, 223, 0.25); }
        .btn-login { background-color: #4e73df; border-color: #4e73df; border-radius: 0.5rem; padding: 0.9rem 1rem; font-weight: 600; }
        @media (max-width: 767.98px) { .image-container { display: none; } .form-container { padding-top: 4rem; padding-bottom: 4rem; } html, body { overflow: auto; } }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes float { 0% { transform: translateY(0px); } 50% { transform: translateY(-15px); } 100% { transform: translateY(0px); } }
    </style>
</head>
<body>
    <div class="container-fluid p-0">
        <div class="row g-0 login-wrapper">
            <div class="col-md-7 d-flex align-items-center justify-content-center image-container">
                <div class="text-center p-5 image-text">
                    <h1 class="display-4 fw-bold">Selamat Datang di EggGo</h1>
                    <p class="lead">Solusi Cerdas untuk Manajemen Kandang Ayam Petelur Anda.</p>
                </div>
            </div>
            
            <div class="col-md-5 form-container">
                <div class="form-box">
                    <div class="logo text-center">🥚 EggGo</div>
                    <p class="subtitle text-center">Silakan login untuk melanjutkan</p>
                    
                    <?php if(!empty($error_message)): ?>
                        <div class="alert alert-danger"><?php echo $error_message; ?></div>
                    <?php endif; ?>

                    <form action="login.php" method="POST">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="password" name="password" required>
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="fas fa-eye" id="toggleIcon"></i>
                                </button>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 btn-login mt-3">Login</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');

            togglePassword.addEventListener('click', function() {
                // Cek tipe input saat ini
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);

                // Ganti ikon mata
                if (type === 'password') {
                    toggleIcon.classList.remove('fa-eye-slash');
                    toggleIcon.classList.add('fa-eye');
                } else {
                    toggleIcon.classList.remove('fa-eye');
                    toggleIcon.classList.add('fa-eye-slash');
                }
            });
        });
    </script>

</body>
</html>