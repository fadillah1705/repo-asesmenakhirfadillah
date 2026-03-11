<?php 
include 'koneksi.php'; 

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$is_logged_in = isset($_SESSION['id']);

function getHariIni() {
    $hari = date('D');
    $map = [
        'Sun' => 'Minggu', 'Mon' => 'Senin', 'Tue' => 'Selasa', 
        'Wed' => 'Rabu', 'Thu' => 'Kamis', 'Fri' => 'Jumat', 'Sat' => 'Sabtu'
    ];
    return $map[$hari];
}
$hari_sekarang = getHariIni();

// --- 1. RENDER JADWAL HARI INI ---
function renderJadwalHariIni($conn, $gender) {
    global $hari_sekarang;
    $query = "SELECT j.*, u.nama_lengkap, jr.status_hadir 
              FROM jadwal j 
              LEFT JOIN users u ON j.guru_id = u.id 
              LEFT JOIN jurnal jr ON j.id = jr.jadwal_id AND jr.tanggal = CURDATE()
              WHERE j.hari = '$hari_sekarang' AND j.gender = '$gender'
              ORDER BY kelas ASC";
    
    $res = mysqli_query($conn, $query);
    
    echo '<div class="row g-2">';
    if(mysqli_num_rows($res) > 0) {
        while($row = mysqli_fetch_assoc($res)) {
            $status = $row['status_hadir'] ?? 'Belum Absen';
            $st_css = ($status == 'Hadir') ? 'st-hadir' : (($status == 'Izin') ? 'st-izin' : 'st-pending');
            echo "
            <div class='col-12'>
                <div class='today-mini-card shadow-sm'>
                    <div class='d-flex align-items-center justify-content-between'>
                        <div class='d-flex align-items-center'>
                            <div class='kelas-tag'>{$row['kelas']}</div>
                            <div class='ms-2'>
                                <div class='fw-bold text-dark mb-0' style='font-size:13px;'>{$row['mapel']}</div>
                                <div class='text-muted' style='font-size:12px;'>Ust. {$row['nama_lengkap']}</div>
                            </div>
                        </div>
                        <div class='status-pill $st_css'>$status</div>
                    </div>
                </div>
            </div>";
        }
    } else {
        echo "<div class='text-center py-3 opacity-50 small'>Tidak ada agenda untuk hari ini.</div>";
    }
    echo '</div>';
}

// --- 2. RENDER JADWAL PEKANAN (MODEL TABEL TINGGI) ---
function renderJadwalVisual($conn, $gender) {
    $hari_list = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
    $kelas_list = ['X', 'XI', 'XII'];
    
    $data_jadwal = [];
    $query = "SELECT j.*, u.nama_lengkap FROM jadwal j 
              LEFT JOIN users u ON j.guru_id = u.id 
              WHERE j.gender = '$gender'";
    $res = mysqli_query($conn, $query);
    while ($row = mysqli_fetch_assoc($res)) {
        $data_jadwal[$row['kelas']][$row['hari']] = $row;
    }

    echo '<div class="table-responsive">';
    echo '<table class="table-custom">';
    echo '<thead><tr>';
    echo '<th class="cell-corner"></th>';
    foreach ($hari_list as $hari) {
        echo "<th class='header-day'>$hari</th>";
    }
    echo '</tr></thead>';
    echo '<tbody>';
    foreach ($kelas_list as $kelas) {
        echo '<tr>';
        echo "<td class='col-element shadow-sm'>KELAS $kelas</td>";
        foreach ($hari_list as $hari) {
           if (isset($data_jadwal[$kelas][$hari])) {
    $d = $data_jadwal[$kelas][$hari];
    echo "<td class='cell-content shadow-sm'>
            <div class='cell-mapel'>{$d['mapel']}</div>
            <div class='cell-teacher'>Ust. " . explode(' ', $d['nama_lengkap'])[0] . "</div>
          </td>";
} else {
    echo "<td class='cell-empty'>-</td>";
}
            
        }
        echo '</tr>';
    }
    echo '</tbody>';
    echo '</table>';
    echo '</div>';
}

// --- 3. RENDER RIWAYAT JURNAL ---
function renderRiwayatVisual($conn, $gender) {
    $query = "SELECT jr.*, j.kelas, j.mapel, u.nama_lengkap FROM jurnal jr JOIN jadwal j ON jr.jadwal_id = j.id JOIN users u ON j.guru_id = u.id WHERE j.gender = '$gender' ORDER BY jr.tanggal DESC LIMIT 6";
    $res = mysqli_query($conn, $query);
    echo '<div class="row g-3">';
    if(mysqli_num_rows($res) > 0) {
        while($row = mysqli_fetch_assoc($res)) {
            $tgl = date('d/m/Y', strtotime($row['tanggal']));
            $dot = ($row['status_hadir'] == 'Hadir') ? '#10b981' : '#f59e0b';
            $color = ($row['kelas'] == 'X') ? 'islami-green' : (($row['kelas'] == 'XI') ? 'islami-teal' : 'islami-gold');
            echo "<div class='col-md-4 col-sm-6'><div class='content-card $color shadow-sm riwayat-card'><div class='d-flex justify-content-between align-items-center mb-1'><span class='card-kelas' style='font-size:10px;'>$tgl</span><div class='status-dot' style='background:$dot;'></div></div><div class='card-title text-truncate' style='font-size:13px;'>{$row['mapel']}</div><div class='card-teacher border-top pt-2 mt-1'>kelas {$row['kelas']} • Ust. {$row['nama_lengkap']}</div></div></div>";
        }
    } else { echo "<div class='col-12 text-center py-5 opacity-50 small'>Belum ada riwayat.</div>"; }
    echo '</div>';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Kajian | Monitoring Boarding</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --emerald: #065f46; --gold: #b45309; --pink: #be123c; --bg: #f8fafc; }
        body { background: var(--bg); font-family: 'Plus Jakarta Sans', sans-serif; color: #334155; }
        .navbar { background: var(--emerald) !important; border-bottom: 3px solid var(--gold); }
        .navbar-brand { font-weight: 800; color: #fff !important; }

        .hero-banner { background: #fff; padding: 40px 0; border-bottom: 1px solid #e2e8f0; margin-bottom: 40px; text-align: center; }
        .main-title { font-weight: 800; color: var(--emerald); font-size: 2rem; margin-bottom: 5px; }
        .sub-title { font-weight: 600; color: #64748b; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 2px; }

        .area-container { background: #fff; border-radius: 20px; padding: 25px; border: 1px solid #e2e8f0; margin-bottom: 40px; }
        .section-header { font-weight: 800; font-size: 13px; color: var(--emerald); text-transform: uppercase; margin-bottom: 20px; display: flex; align-items: center; }
        .section-header::after { content: ""; flex: 1; height: 1px; background: #e2e8f0; margin-left: 10px; }

        /* TABEL JADWAL PEKANAN - ADJUST TINGGI */
        .table-custom { width: 100%; border-collapse: separate; border-spacing: 8px; min-width: 1000px; }
        .header-day { background: var(--emerald) !important; color: white; padding: 15px 10px; border-radius: 10px; text-align: center; font-size: 11px; text-transform: uppercase; font-weight: 700; }
        .col-element { background: #93c5fd !important; color: #1e3a8a; font-weight: 800; padding: 25px 15px; border-radius: 10px; text-align: center; font-size: 13px; width: 130px; }
        
        /* Kotak Isi Lebih Tinggi */
        .cell-content { 
            background: #fefce8; 
            border: 1px solid #fef08a; 
            padding: 22px 15px; /* Padding ditambah agar lebih tinggi */
            border-radius: 10px; 
            text-align: center; 
            vertical-align: middle;
            min-height: 90px;
        }
        
        .cell-mapel { font-weight: 800; font-size: 13px; color: #1e293b; line-height: 1.3; }
        .cell-teacher { font-size: 12px; color: #35393f; margin-top: 6px; }
        .cell-empty { background: #f1f5f9; border-radius: 10px; text-align: center; color: #cbd5e1; font-size: 10px; padding: 20px; }
        .cell-corner { background: transparent; border: none; }

        /* STYLES ASLI */
        .today-card-wrap { background: #fff; border-radius: 15px; padding: 18px; border: 1px solid #e2e8f0; height: 100%; border-top: 4px solid var(--emerald); }
        .today-mini-card { background: #f8fafc; border-radius: 10px; padding: 12px; border: 1px solid #e2e8f0; margin-bottom: 10px; }
        .kelas-tag { width: 26px; height: 26px; background: var(--emerald); color: #fff; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 10px; }
        .status-pill { font-size: 8px; font-weight: 700; padding: 3px 10px; border-radius: 15px; }
        .st-hadir { background: #dcfce7; color: #15803d; }
        .st-izin { background: #fef3c7; color: #92400e; }
        .st-pending { background: #f1f5f9; color: #64748b; }
        .nav-tabs-mini { border: none; background: #f1f5f9; padding: 4px; border-radius: 12px; display: inline-flex; }
        .nav-tabs-mini .nav-link { border: none; font-size: 11px; font-weight: 700; padding: 8px 25px; border-radius: 10px; color: #64748b; transition: none !important; }
        .nav-tabs-mini .nav-link.active { background: var(--emerald); color: #fff; }
        .nav-tabs-mini .nav-link.active.link-akhwat { background: var(--pink); }
        .content-card { border-radius: 12px; padding: 12px; border-left: 5px solid transparent; margin-bottom: 10px; }
        .islami-green { background: #f0fdf4; border: 1px solid #dcfce7; border-left-color: #22c55e; }
        .islami-teal { background: #f0f9ff; border: 1px solid #e0f2fe; border-left-color: #0ea5e9; }
        .islami-gold { background: #fffbeb; border: 1px solid #fef3c7; border-left-color: #f59e0b; }
        .card-kelas { font-size: 8px; font-weight: 900; color: #64748b; }
        .card-title { font-size: 13px; font-weight: 700; color: #1e293b; margin: 3px 0; }
        .card-teacher { font-size: 10px; color: #64748b; }
        .status-dot { width: 10px; height: 10px; border-radius: 50%; }
    </style>
</head>
<body>

<nav class="navbar navbar-dark sticky-top shadow-sm">
    <div class="container d-flex justify-content-between align-items-center">
        <a class="navbar-brand" href="index.php"><i class="fas fa-mosque me-2"></i> E-KAJIAN</a>
        <?php if ($is_logged_in): ?><a href="dashboard.php" class="btn btn-warning btn-sm rounded-pill px-4 fw-bold">DASHBOARD</a>
        <?php else: ?><a href="login.php" class="btn btn-outline-light btn-sm rounded-pill px-4 fw-bold">MASUK</a><?php endif; ?>
    </div>
</nav>

<div class="hero-banner shadow-sm">
    <div class="container">
        <h1 class="main-title">Monitoring Jurnal Pembinaan</h1>
        <div class="sub-title"><?= $hari_sekarang ?>, <?= date('d F Y') ?></div>
    </div>
</div>

<div class="container pb-5">

    <div class="row g-4 mb-5">
        <div class="col-lg-6">
            <span class="section-header">Agenda Hari Ini (Ikhwan)</span>
            <div class="today-card-wrap shadow-sm"><?php renderJadwalHariIni($conn, 'L'); ?></div>
        </div>
        <div class="col-lg-6">
            <span class="section-header" style="color:var(--pink);">Agenda Hari Ini (Akhwat)</span>
            <div class="today-card-wrap shadow-sm" style="border-top-color: var(--pink);"><?php renderJadwalHariIni($conn, 'P'); ?></div>
        </div>
    </div>

    <div class="area-container shadow-sm">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <span class="section-header m-0 flex-grow-1">Jadwal Pekanan Umum</span>
            <div class="nav nav-tabs nav-tabs-mini" id="tabJadwal">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#jd-L">Ikhwan (L)</button>
                <button class="nav-link link-akhwat" data-bs-toggle="tab" data-bs-target="#jd-P">Akhwat (P)</button>
            </div>
        </div>
        <div class="tab-content" id="contentJadwal">
            <div class="tab-pane active" id="jd-L"><?php renderJadwalVisual($conn, 'L'); ?></div>
            <div class="tab-pane" id="jd-P"><?php renderJadwalVisual($conn, 'P'); ?></div>
        </div>
    </div>

    <div class="area-container shadow-sm">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <span class="section-header m-0 flex-grow-1">Riwayat Jurnal Kajian</span>
            <div class="nav nav-tabs nav-tabs-mini" id="tabRiwayat">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#rw-L">Ikhwan (L)</button>
                <button class="nav-link link-akhwat" data-bs-toggle="tab" data-bs-target="#rw-P">Akhwat (P)</button>
            </div>
        </div>
        <div class="tab-content" id="contentRiwayat">
            <div class="tab-pane active" id="rw-L"><?php renderRiwayatVisual($conn, 'L'); ?></div>
            <div class="tab-pane" id="rw-P"><?php renderRiwayatVisual($conn, 'P'); ?></div>
        </div>
    </div>

</div>

<footer class="py-4 text-center bg-white border-top mt-5 small text-muted">
    E-KAJIAN MONITORING SYSTEM &copy; 2026
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>