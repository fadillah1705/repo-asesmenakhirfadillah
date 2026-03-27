<?php 
include 'koneksi.php'; 

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- 1. FITUR BARU: NAVIGASI DASHBOARD DINAMIS ---
$link_dashboard = "login.php"; 
if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'pembina' || $_SESSION['role'] === 'admin') {
        $link_dashboard = "dashboard.php";
    } else {
        $link_dashboard = "dashboard_guru.php";
    }
}

$nama_user = $_SESSION['nama_lengkap'] ?? $_SESSION['nama'] ?? 'User';

function getHariIni() {
    $hari = date('D');
    $map = ['Sun'=>'Minggu','Mon'=>'Senin','Tue'=>'Selasa','Wed'=>'Rabu','Thu'=>'Kamis','Fri'=>'Jumat','Sat'=>'Sabtu'];
    return $map[$hari];
}
$hari_sekarang = getHariIni();

// --- STATISTIK ---
$q_total = "SELECT COUNT(*) as total FROM jadwal WHERE hari = '$hari_sekarang'";
$total_tugas = mysqli_fetch_assoc(mysqli_query($conn, $q_total))['total'] ?? 0;
$q_hadir = "SELECT COUNT(*) as total FROM jurnal WHERE tanggal = CURDATE() AND status_hadir = 'Hadir'";
$hadir = mysqli_fetch_assoc(mysqli_query($conn, $q_hadir))['total'] ?? 0;
$q_izin = "SELECT COUNT(*) as total FROM jurnal WHERE tanggal = CURDATE() AND status_hadir IN ('Izin', 'Sakit')";
$izin = mysqli_fetch_assoc(mysqli_query($conn, $q_izin))['total'] ?? 0;
$pending = max(0, $total_tugas - ($hadir + $izin));

function renderAgendaCards($conn, $gender) {
    global $hari_sekarang;
    $query = "SELECT j.*, u.nama_lengkap, jr.status_hadir 
              FROM jadwal j 
              LEFT JOIN users u ON j.guru_id = u.id 
              LEFT JOIN jurnal jr ON j.id = jr.jadwal_id AND jr.tanggal = CURDATE()
              WHERE j.hari = '$hari_sekarang' AND j.gender = '$gender' ORDER BY kelas ASC";
    $res = mysqli_query($conn, $query);
    
    if(mysqli_num_rows($res) > 0) {
        while($row = mysqli_fetch_assoc($res)) {
            $nama = ucwords(strtolower($row['nama_lengkap'] ?? 'Tanpa Nama'));
            $status = $row['status_hadir'] ?? 'Belum Absen';
            $icon_bg = ($gender == 'L') ? '#010d58' : '#831843';
            
            if($status == 'Hadir') {
                $st_style = "background: #dcfce7; color: #151e80;";
            } elseif($status == 'Belum Absen') {
                $st_style = "background: #f1f5f9; color: #64748b;";
            } else {
                $st_style = "background: #fef3c7; color: #b40909;";
            }
            
            echo "
            <div class='agenda-card-img-style'>
                <div class='class-box-icon' style='background: $icon_bg'>
                    {$row['kelas']}
                </div>
                <div class='agenda-details-mid'>
                    <div class='mapel-text'>{$row['mapel']}</div>
                    <div class='teacher-text'>Ust. $nama</div>
                </div>
                <div class='status-badge-right' style='$st_style'>
                    $status
                </div>
            </div>";
        }
    } else { 
        echo "<div class='text-center text-muted py-5'>Tidak ada agenda untuk hari ini.</div>"; 
    }
}

function renderMatriksGrid($conn, $gender) {
    $hari_list = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
    $kelas_list = ['X', 'XI', 'XII'];
    
    echo "<div class='table-responsive'>
            <table class='matrix-table'>
                <thead>
                    <tr>
                        <th width='100'>KELAS</th>";
                        foreach ($hari_list as $h) {
                            $is_today = (getHariIni() == $h) ? "class='today-head'" : "";
                            echo "<th $is_today>$h</th>";
                        }
    echo "          </tr>
                </thead>
                <tbody>";

    foreach ($kelas_list as $kls) {
        echo "<tr>
                <td class='matrix-label'>KELAS $kls</td>";
        foreach ($hari_list as $h) {
            $q = "SELECT j.id, j.mapel, u.nama_lengkap FROM jadwal j 
                  LEFT JOIN users u ON j.guru_id = u.id 
                  WHERE j.hari = '$h' AND j.kelas = '$kls' AND j.gender = '$gender' LIMIT 1";
            $res = mysqli_query($conn, $q);
            if ($d = mysqli_fetch_assoc($res)) {
                $singkat = explode(' ', $d['nama_lengkap'])[0];
                $jid = $d['id'];
                $q_mat = "SELECT materi FROM jurnal WHERE jadwal_id = '$jid' AND status_hadir = 'Hadir' ORDER BY tanggal DESC LIMIT 1";
                $res_mat = mysqli_query($conn, $q_mat);
                $materi_last = (mysqli_num_rows($res_mat) > 0) ? mysqli_fetch_assoc($res_mat)['materi'] : "-";

                echo "<td>
                        <div class='cell-mapel'>{$d['mapel']}</div>
                        <div class='cell-guru'>Ust. $singkat</div>
                        <div class='cell-materi'>Last: $materi_last</div>
                      </td>";
            } else { echo "<td class='empty-cell'>-</td>"; }
        }
        echo "</tr>";
    }
    echo "</tbody></table></div>";
}

function renderRiwayat($conn, $gender) {
    // Query tetap sama, pastikan kolom 'materi' ikut terpanggil
    $query = "SELECT jr.*, j.mapel, j.kelas FROM jurnal jr JOIN jadwal j ON jr.jadwal_id = j.id WHERE j.gender = '$gender' ORDER BY jr.tanggal DESC";
    $res = mysqli_query($conn, $query);
    $sfx = ($gender == 'L') ? 'ikhwan' : 'akhwat';
    
    echo "<div class='print-area-wrapper'>
            <div class='table-responsive mt-2'>
                <table class='log-table'>
                    <thead>
                        <tr class='header-minimal'>
                            <th width='30'>NO</th>
                            <th width='110'>TANGGAL</th>
                            <th>KAJIAN & MATERI / ALASAN</th>
                            <th width='95' class='text-center'>STATUS</th>
                        </tr>
                    </thead>
                    <tbody id='bodyJurnal_$sfx'>";
    $no = 1;
    while($row = mysqli_fetch_assoc($res)) {
        $st = $row['status_hadir'];
        $pill = ($st == 'Hadir') ? 'st-hadir' : (($st == 'Izin' || $st == 'Sakit') ? 'st-izin' : 'st-absen');
        
        $info_tambahan = "";
        
        // LOGIKA BARU: Jika Hadir tampilkan sebagai Materi, jika Izin tampilkan sebagai Alasan
        if ($st == 'Hadir') {
            $materi = !empty($row['materi']) ? $row['materi'] : "<span class='text-muted'>Materi belum diisi</span>";
            $info_tambahan = "<div class='materi-box-jurnal'><strong>Materi:</strong> $materi</div>";
        } elseif ($st == 'Izin' || $st == 'Sakit') {
            // Kita mengambil data dari kolom 'materi' karena tadi kita simpan alasan di sana
            $alasan = !empty($row['materi']) ? $row['materi'] : "Tidak ada alasan spesifik";
            $info_tambahan = "<div class='alasan-box-jurnal'><strong>Alasan:</strong> $alasan</div>";
        }

        echo "<tr data-tgl='{$row['tanggal']}'>
                <td class='text-center text-muted' style='font-size: 0.85rem;'>$no</td>
                <td class='fw-bold' style='font-size: 0.9rem;'>".date('d/m/Y', strtotime($row['tanggal']))."</td>
                <td>
                    <div class='mapel-title-jurnal'>{$row['mapel']} (Kelas {$row['kelas']})</div>
                    $info_tambahan
                </td>
                <td class='text-center'><span class='status-pill $pill'>$st</span></td>
              </tr>";
        $no++;
    }
    echo "</tbody></table></div></div>";
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MAKN Monitor | Sistem Informasi Kajian</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { --primary: #0f172a; --accent: #1037b9; --bg: #f8fcf9; --border: #e2f0e5; }
        body { background-color: var(--bg); font-family: 'Plus Jakarta Sans', sans-serif; color: #1e223b; }
        .header-nav { padding: 15px 0; display: flex; justify-content: space-between; align-items: center; }
        .hero-section h1 { font-weight: 800; font-size: 2rem; color: var(--primary); letter-spacing: -1px; }
        .stats-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 30px; }
        .stat-card { background: white; border: 1px solid var(--border); border-radius: 16px; padding: 15px; text-align: center; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .stat-card h3 { font-size: 2rem; font-weight: 800; margin: 0; color: var(--primary); }
        .agenda-card-img-style { background: white; border: 1px solid #f1f5f9; border-radius: 12px; padding: 12px; margin-bottom: 12px; display: flex; align-items: center; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .class-box-icon { width: 42px; height: 42px; border-radius: 8px; color: white; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1rem; margin-right: 15px; flex-shrink: 0; }
        .agenda-details-mid { flex-grow: 1; }
        .mapel-text { font-weight: 700; font-size: 0.95rem; color: #1e293b; line-height: 1.2; }
        .teacher-text { font-size: 0.8rem; color: #94a3b8; margin-top: 2px; }
        .status-badge-right { font-size: 0.65rem; font-weight: 700; padding: 4px 10px; border-radius: 20px; text-transform: capitalize; white-space: nowrap; }
        .nav-pills-custom { background: #e2e8f0; padding: 4px; border-radius: 10px; display: inline-flex; }
        .nav-pills-custom .nav-link { border: none; border-radius: 8px; color: #64748b; font-weight: 700; font-size: 0.85rem; padding: 6px 20px; }
        .nav-pills-custom .nav-link.active { background: white; color: var(--primary); box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .matrix-table { width: 100%; border-collapse: separate; border-spacing: 0; border: 1px solid var(--border); border-radius: 12px; overflow: hidden; background: white; }
        .matrix-table th { background: #f1f5f9; padding: 12px; font-size: 0.75rem; font-weight: 800; text-align: center; border-bottom: 2px solid var(--border); border-right: 1px solid var(--border); }
        .matrix-table td { padding: 12px 8px; border-right: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; text-align: center; vertical-align: middle; min-width: 100px; }
        .matrix-label { background: #f8fafc !important; font-weight: 800; color: var(--primary); font-size: 0.8rem; border-right: 2px solid var(--border) !important; min-width: 100px !important; position: sticky; left: 0; z-index: 2; }
        .cell-mapel { font-weight: 700; font-size: 0.85rem; color: var(--primary); }
        .cell-guru { font-size: 0.75rem; color: var(--accent); font-weight: 700; margin-top: 2px; }
        .cell-materi { font-size: 0.6rem; color: #94a3b8; font-style: italic; margin-top: 4px; }
        .today-head { background: var(--primary) !important; color: white !important; }
        .log-table { width: 100%; border-collapse: separate; border-spacing: 0 8px; }
        .log-table td { background: white; padding: 15px; border-top: 1px solid var(--border); border-bottom: 1px solid var(--border); vertical-align: middle; }
        .log-table tr td:first-child { border-left: 1px solid var(--border); border-radius: 10px 0 0 10px; }
        .log-table tr td:last-child { border-right: 1px solid var(--border); border-radius: 0 10px 10px 0; }
        .mapel-title-jurnal { font-weight: 700; font-size: 0.95rem; color: var(--primary); }
        .materi-box-jurnal { font-size: 0.8rem; color: #475569; margin-top: 5px; padding-left: 10px; border-left: 3px solid var(--accent); }
        .alasan-box-jurnal { font-size: 0.8rem; color: #991b1b; margin-top: 5px; padding: 5px 10px; background: #fef2f2; border-radius: 6px; border-left: 3px solid #dc2626; }
        .status-pill { padding: 4px 12px; border-radius: 6px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; display: inline-block; min-width: 90px; }
        .st-hadir { background: #dce3fc; color: #091d8d; }
        .st-izin { background: #fec7c7; color: #900000; }
        .st-absen { background: #fee2e2; color: #b91c1c; }
        .marquee-strict-align { width: 100%; overflow: hidden; white-space: nowrap; margin-top: 5px; margin-bottom: 20px; }
        .text-emerging-right { display: inline-block; animation: jalan-mulus 40s linear infinite; font-weight: 700; color: #64748b; font-size: 0.95rem; padding-left: 100%; }
        @keyframes jalan-mulus { 0% { transform: translateX(0); } 100% { transform: translateX(-100%); } }
        .fade-in-page { animation: fadeIn 1.2s ease-out forwards; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }

        /* --- MEDIA QUERIES UNTUK HP --- */
        @media (max-width: 768px) {
            .hero-section h1 { font-size: 1.5rem; text-align: center; }
            .stats-row { grid-template-columns: repeat(2, 1fr); }
            .stats-row div:last-child { grid-column: span 2; }
            .stat-card h3 { font-size: 1.5rem; }
            .agenda-card-img-style { padding: 10px; }
            .class-box-icon { width: 35px; height: 35px; font-size: 0.8rem; margin-right: 10px; }
            .mapel-text { font-size: 0.85rem; }
            .status-badge-right { font-size: 0.6rem; padding: 3px 8px; }
            .nav-pills-custom .nav-link { padding: 6px 10px; font-size: 0.75rem; }
            
            /* Agar Header Jurnal & Jadwal Seminggu tidak pecah */
            div[style*="width:280px"] { width: 220px !important; font-size: 0.9rem !important; height: 40px !important; }
            div[style*="width: 160px"] { width: 140px !important; font-size: 0.7rem !important; }

            /* Tabel Responsif agar bisa di scroll */
            .table-responsive { border-radius: 12px; border: 1px solid var(--border); }
            .log-table td { padding: 10px; font-size: 0.8rem; }
            .mapel-title-jurnal { font-size: 0.85rem; }
            .status-pill { min-width: 70px; font-size: 0.65rem; }
            
            /* Kontrol filter tanggal & tombol cetak di HP */
            .d-flex.gap-2.no-print { flex-direction: column; width: 100%; }
            #filterTgl { width: 100% !important; }
        }

        @media print { .no-print { display: none !important; } .print-header { display: block !important; } }
    </style>
</head>
<body class="fade-in-page">

<div class="container py-3">
    <nav class="header-nav no-print">
        <div class="fw-800 text-dark">MAKN MONITORING</div>
        <div class="d-flex align-items-center gap-3">
            <a href="<?= $link_dashboard ?>" class="btn btn-primary btn-sm rounded-pill px-4 fw-bold shadow-sm">
                <i class="fas fa-th-large me-1"></i> DASHBOARD
            </a>
        </div>
    </nav>

    <header class="hero-section no-print mb-4">
        <h1 class="fw-800">Sistem Informasi Kajian MAKN</h1>
        <div class="marquee-strict-align">
            <div class="text-emerging-right">
                <i class="fas fa-info-circle me-2" style="color: var(--accent);"></i> 
                Selamat datang di Sistem Jadwal dan Jurnal Kehadiran Guru Kajian. Silakan mengisi kehadiran serta jurnal kajian secara real-time.
            </div>
        </div>
    </header>

    <div class="stats-row no-print mb-4">
        <div class="stat-card"><h3><?= $hadir ?></h3><p class="small fw-bold text-muted">Hadir Hari Ini</p></div>
        <div class="stat-card"><h3><?= $izin ?></h3><p class="small fw-bold text-muted">Izin Hari Ini</p></div>
        <div class="stat-card"><h3><?= $pending ?></h3><p class="small fw-bold text-muted">Belum Absen</p></div>
    </div>

    <div class="row g-3 mb-5 no-print">
        <div class="col-md-6">
            <div class="mb-3">
                <div class="fw-800 fw-bold border border-primary shadow-sm text-center d-flex align-items-center justify-content-center" 
                     style="width: 160px; height: 35px; border-radius: 6px; font-size: 0.8rem; background-color: white; color: #0d6efd; text-transform: uppercase; border-width: 1.5px !important;">
                    JADWAL HARI INI (PA)
                </div>
            </div>
            <?php renderAgendaCards($conn, 'L'); ?>
        </div>
        <div class="col-md-6">
            <div class="mb-3">
                <div class="fw-800 fw-bold border border-danger shadow-sm text-center d-flex align-items-center justify-content-center" 
                     style="width: 160px; height: 35px; border-radius: 6px; font-size: 0.8rem; background-color: white; color: #dc3545; text-transform: uppercase; border-width: 1.5px !important;">
                    JADWAL HARI INI (PI)
                </div>
            </div>
            <?php renderAgendaCards($conn, 'P'); ?>
        </div>
    </div>

    <div class="d-flex align-items-center justify-content-between mb-3 no-print flex-wrap gap-2">
        <div class="fw-800 m-0 fw-bold border border-secondary shadow-sm text-center d-flex align-items-center justify-content-center" 
             style="width:280px; height:50px; border-radius:10px; font-size:1.1rem; background-color: white; color: #343a40; text-transform: uppercase;">
            JADWAL KAJIAN SEMINGGU
        </div>
        <div class="nav nav-pills nav-pills-custom">
            <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#matL">Putra</button>
            <button class="nav-link" data-bs-toggle="pill" data-bs-target="#matP">Putri</button>
        </div>
    </div>
    <div class="tab-content no-print mb-5">
        <div class="tab-pane fade show active" id="matL"><?php renderMatriksGrid($conn, 'L'); ?></div>
        <div class="tab-pane fade" id="matP"><?php renderMatriksGrid($conn, 'P'); ?></div>
    </div>

    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <div class="fw-800 m-0 fw-bold border border-secondary shadow-sm text-center d-flex align-items-center justify-content-center" 
             style="width:280px; height:50px; border-radius:10px; font-size:1.1rem; background-color: white; color: #343a40; text-transform: uppercase;">
            JURNAL KAJIAN HARIAN
        </div>
        <div class="d-flex gap-2 no-print align-items-center">
            <input type="date" id="filterTgl" class="form-control form-control-sm fw-bold border-secondary shadow-sm" style="width:130px; border-radius:8px" onchange="filterSemua()">
            <button class="btn btn-outline-dark btn-sm fw-bold px-3" style="border-radius:8px" onclick="window.print()">CETAK</button>
        </div>
    </div>

    <div class="nav nav-pills nav-pills-custom no-print mb-3">
        <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#jurL">Putra</button>
        <button class="nav-link" data-bs-toggle="pill" data-bs-target="#jurP">Putri</button>
    </div>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="jurL"><?php renderRiwayat($conn, 'L'); ?></div>
        <div class="tab-pane fade" id="jurP"><?php renderRiwayat($conn, 'P'); ?></div>
    </div>

    <footer class="text-center py-5 no-print mt-4 border-top">
        <small class="text-muted fw-bold">SISTEM MONITORING MAKN ENDE &copy; 2026</small>
    </footer>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function filterSemua() {
    const tgl = document.getElementById('filterTgl').value;
    ['bodyJurnal_ikhwan', 'bodyJurnal_akhwat'].forEach(id => {
        document.querySelectorAll('#'+id+' tr').forEach(r => {
            r.style.display = (tgl === "" || r.getAttribute('data-tgl') === tgl) ? "" : "none";
        });
    });
}
</script>
</body>
</html>