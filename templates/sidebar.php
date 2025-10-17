<?php
// Mendapatkan nama file saat ini untuk logika 'active'
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
?>
<div class="sidebar">
    <a href="<?php echo $folder_base; ?>/index.php" class="sidebar-brand">
        <span class="sidebar-brand-icon">🥚</span>
        <span class="sidebar-brand-text">EggGo</span>
    </a>

    <hr class="sidebar-divider">

    <ul class="navbar-nav">
        <li class="nav-item <?php echo ($current_page == 'index.php' && $current_dir == 'egggo') ? 'active' : ''; ?>">
            <a class="nav-link" href="<?php echo $folder_base; ?>/index.php">
                <i class="fas fa-tachometer-alt"></i> <span>Dashboard</span>
            </a>
        </li>
        <li class="nav-item <?php echo ($current_dir == 'laporan_harian') ? 'active' : ''; ?>">
            <a class="nav-link" href="<?php echo $folder_base; ?>/laporan_harian/input.php">
                <i class="fas fa-edit"></i> <span>Input Harian</span>
            </a>
        </li>
        <li class="nav-item <?php echo ($current_dir == 'laporan_performa') ? 'active' : ''; ?>">
            <a class="nav-link" href="<?php echo $folder_base; ?>/laporan_performa/index.php">
                <i class="fas fa-chart-line"></i> <span>Laporan Performa</span>
            </a>
        </li>
        <li class="nav-item <?php echo ($current_dir == 'riwayat_laporan') ? 'active' : ''; ?>">
            <a class="nav-link" href="<?php echo $folder_base; ?>/riwayat_laporan/index.php">
                <i class="fas fa-history"></i> <span>Riwayat Laporan</span>
            </a>
        </li>

        <hr class="sidebar-divider">

        <li class="nav-item <?php echo ($current_dir == 'kandang') ? 'active' : ''; ?>">
            <a class="nav-link" href="<?php echo $folder_base; ?>/kandang/index.php">
                <i class="fas fa-home"></i> <span>Manajemen Kandang</span>
            </a>
        </li>
        <li class="nav-item <?php echo ($current_dir == 'stok_pakan') ? 'active' : ''; ?>">
            <a class="nav-link" href="<?php echo $folder_base; ?>/stok_pakan/index.php">
                <i class="fas fa-boxes"></i> <span>Stok Pakan</span>
            </a>
        </li>
        <li class="nav-item <?php echo ($current_dir == 'manajemen_user') ? 'active' : ''; ?>">
            <a class="nav-link" href="<?php echo $folder_base; ?>/manajemen_user/index.php">
                <i class="fas fa-users-cog"></i> <span>Manajemen User</span>
            </a>
        </li>
        <li class="nav-item <?php echo ($current_dir == 'tujuan_wa') ? 'active' : ''; ?>">
            <a class="nav-link" href="<?php echo $folder_base; ?>/tujuan_wa/index.php">
                <i class="fas fa-phone"></i> <span>Tujuan Notifikasi WA</span>
            </a>
        </li>
    </ul>

    <div class="sidebar-footer">
        <span class="username"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
        <a href="<?php echo $folder_base; ?>/auth/logout.php" class="btn btn-sm btn-outline-danger w-100">
            <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
        </a>
    </div>

    <button class="sidebar-toggle-button" id="sidebarToggle" title="Perkecil/Perbesar Sidebar"></button>
</div>