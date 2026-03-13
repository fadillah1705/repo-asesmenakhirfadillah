<?php 
include 'koneksi.php'; 

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function getHariIni() {
    $hari = date('D');
    $map = ['Sun'=>'Minggu','Mon'=>'Senin','Tue'=>'Selasa','Wed'=>'Rabu','Thu'=>'Kamis','Fri'=>'Jumat','Sat'=>'Sabtu'];
    return $map[$hari];
}
$hari_sekarang = getHariIni();

// --- LOGIKA STATISTIK ---
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
            
            // Warna Ikon Kelas (Hijau untuk Ikhwan, Merah Tua untuk Akhwat)
            $icon_bg = ($gender == 'L') ? '#010d58' : '#831843';
            
            // Logika Warna Status Badge (Sesuai Gambar)
            if($status == 'Hadir') {
                $st_style = "background: #dcfce7; color: #151e80;";
            } elseif($status == 'Belum Absen') {
                $st_style = "background: #f1f5f9; color: #64748b;";
            } else {
                $st_style = "background: #fef3c7; color: #b40909;"; // Warna Izin/Sakit
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
// --- 2. RENDER MATRIKS PEKANAN ---
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

// --- 3. RENDER JURNAL ---
function renderRiwayat($conn, $gender) {
    $query = "SELECT jr.*, j.mapel, j.kelas FROM jurnal jr JOIN jadwal j ON jr.jadwal_id = j.id WHERE j.gender = '$gender' ORDER BY jr.tanggal DESC";
    $res = mysqli_query($conn, $query);
    $sfx = ($gender == 'L') ? 'ikhwan' : 'akhwat';
    $title = ($gender == 'L') ? 'PUTRA (IKHWAN)' : 'PUTRI (AKHWAT)';
    
    echo "<div class='print-area-wrapper'>
            <h3 class='print-header'>JURNAL KEHADIRAN KAJIAN - $title</h3>
            <div class='table-responsive mt-2'>
                <table class='log-table'>
                    <thead>
                        <tr class='header-minimal'>
                            <th width='30'>NO</th>
                            <th width='110'>TANGGAL</th>
                            <th>KAJIAN & MATERI</th>
                            <th width='95' class='text-center'>STATUS</th>
                        </tr>
                    </thead>
                    <tbody id='bodyJurnal_$sfx'>";
    $no = 1;
    while($row = mysqli_fetch_assoc($res)) {
        $st = $row['status_hadir'];
        $pill = ($st == 'Hadir') ? 'st-hadir' : (($st == 'Izin' || $st == 'Sakit') ? 'st-izin' : 'st-absen');
        echo "<tr data-tgl='{$row['tanggal']}'>
                <td class='text-center text-muted' style='font-size: 0.85rem;'>$no</td>
                <td class='fw-bold' style='font-size: 0.9rem;'>".date('d/m/Y', strtotime($row['tanggal']))."</td>
                <td>
                    <div class='mapel-title-jurnal'>{$row['mapel']} (Kelas {$row['kelas']})</div>
                    ".($st == 'Hadir' ? "<div class='materi-box-jurnal'>{$row['materi']}</div>" : "")."
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
    <style>
        :root { --primary: #0f172a; --accent: #1037b9; --bg: #f8fcf9; --border: #e2f0e5; }
        body { background-color: var(--bg); font-family: 'Plus Jakarta Sans', sans-serif; color: #1e223b; }
        
        .header-nav { padding: 15px 0; display: flex; justify-content: space-between; align-items: center; }
        .hero-section h1 { font-weight: 800; font-size: 2rem; color: var(--primary); letter-spacing: -1px; }

        .stats-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 30px; }
        .stat-card { background: white; border: 1px solid var(--border); border-radius: 16px; padding: 15px; text-align: center; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .stat-card h3 { font-size: 2rem; font-weight: 800; margin: 0; color: var(--primary); }

        /* AGENDA CARD - TAMPILAN BARU */
        .agenda-container-card {
            background: white; border: 1px solid var(--border); border-radius: 20px;
            padding: 20px; min-height: 250px; position: relative; overflow: hidden;
        }
        .agenda-card-img-style {
            background: white; border: 1px solid #f1f5f9; border-radius: 12px;
            padding: 12px; margin-bottom: 12px; display: flex; align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }
        .class-box-icon {
            width: 42px; height: 42px; border-radius: 8px; color: white;
            display: flex; align-items: center; justify-content: center;
            font-weight: 800; font-size: 1rem; margin-right: 15px; flex-shrink: 0;
        }
        .agenda-details-mid { flex-grow: 1; }
        .mapel-text { font-weight: 700; font-size: 0.95rem; color: #1e293b; line-height: 1.2; }
        .teacher-text { font-size: 0.8rem; color: #94a3b8; margin-top: 2px; }
        .status-badge-right {
            font-size: 0.65rem; font-weight: 700; padding: 4px 10px;
            border-radius: 20px; text-transform: capitalize; white-space: nowrap;
        }
        /* HEADER JURNAL KECIL (0.65rem) */
        .header-minimal th {
            font-size: 0.65rem !important;
            font-weight: 800 !important;
            text-transform: uppercase !important;
            color: #64748b;
            padding: 10px !important;
            border-bottom: 2px solid var(--border) !important;
        }

        .nav-pills-custom { background: #e2e8f0; padding: 4px; border-radius: 10px; display: inline-flex; }
        .nav-pills-custom .nav-link { border: none; border-radius: 8px; color: #64748b; font-weight: 700; font-size: 0.85rem; padding: 6px 20px; }
        .nav-pills-custom .nav-link.active { background: white; color: var(--primary); box-shadow: 0 2px 4px rgba(0,0,0,0.1); }

        .matrix-table { width: 100%; border-collapse: separate; border-spacing: 0; border: 1px solid var(--border); border-radius: 12px; overflow: hidden; background: white; }
        .matrix-table th { background: #f1f5f9; padding: 12px; font-size: 0.75rem; font-weight: 800; text-align: center; border-bottom: 2px solid var(--border); border-right: 1px solid var(--border); }
        .matrix-table td { padding: 12px 8px; border-right: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; text-align: center; vertical-align: middle; }
        .matrix-label { background: #f8fafc !important; font-weight: 800; color: var(--primary); font-size: 0.8rem; border-right: 2px solid var(--border) !important; }
        .cell-mapel { font-weight: 700; font-size: 0.85rem; color: var(--primary); }
        .cell-guru { font-size: 0.75rem; color: var(--accent); font-weight: 700; margin-top: 2px; }
        .cell-materi { font-size: 0.6rem; color: #94a3b8; font-style: italic; margin-top: 4px; }
        .today-head { background: var(--primary) !important; color: white !important; }

        .log-table { width: 100%; border-collapse: separate; border-spacing: 0 8px; }
        .log-table td { background: white; padding: 15px; border-top: 1px solid var(--border); border-bottom: 1px solid var(--border); vertical-align: middle; }
        .log-table tr td:first-child { border-left: 1px solid var(--border); border-radius: 10px 0 0 10px; }
        .log-table tr td:last-child { border-right: 1px solid var(--border); border-radius: 0 10px 10px 0; }
        .mapel-title-jurnal { font-weight: 700; font-size: 0.95rem; color: var(--primary); }
        .materi-box-jurnal { font-size: 0.85rem; color: #475569; margin-top: 5px; padding-left: 10px; border-left: 3px solid var(--accent); }
        .status-pill { padding: 4px 12px; border-radius: 6px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; display: inline-block; min-width: 90px; }
        .st-hadir { background: #dce3fc; color: #091d8d; }
        .st-izin { background: #fec7c7; color: #900000; }
        .st-absen { background: #fee2e2; color: #b91c1c; }

        .print-header { display: none; text-align: center; font-weight: 800; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px; }

        @media print {
            .no-print { display: none !important; }
            .print-header { display: block !important; }
            .log-table td, .log-table th { border: 1px solid #000 !important; border-radius: 0 !important; padding: 8px !important; color: #000 !important; }
            .status-pill { background: transparent !important; border: 1px solid #000; color: #000 !important; }
        }
        /* Container pembungkus utama */
.header-wrapper {
    display: flex;
    flex-direction: column; /* Mengatur elemen ke bawah (vertikal) */
    align-items: center;    /* Mengetengahkan elemen */
    gap: 15px;              /* Jarak antara judul dan tab agar tidak berdempetan */
    margin-bottom: 25px;    /* Jarak ke konten di bawahnya */
    width: 100%;
}

/* Kotak Judul */
.judul-full {
    width: 100%;            /* Lebar penuh */
    background: white;
    border: 2px solid #64748b;
    border-radius: 12px;
    padding: 12px;
    font-weight: 800;
    font-size: 1.1rem;
    text-align: center;
    color: #1e293b;
    text-transform: uppercase;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
}

/* Styling Tab Navigasi agar rapi di bawah */
.nav-pills-custom {
    background: #e2e8f0;
    padding: 5px;
    border-radius: 10px;
    display: inline-flex;
    gap: 5px;
}

@media (max-width: 576px) {
    .judul-full {
        font-size: 0.9rem; /* Ukuran teks lebih kecil sedikit di HP */
    }
    
    .nav-pills-custom {
        width: 100%; /* Tab memenuhi lebar layar di HP */
    }
    
    .nav-pills-custom .nav-link {
        flex: 1; /* Tombol Putra & Putri membagi ruang sama rata */
    }
}
    </style>
</head>
<body>

<div class="container py-3">
    <nav class="header-nav no-print">
        <div class="fw-800 text-dark">MAKN MONITORING</div>
        <a href="login.php" class="btn btn-dark btn-sm rounded-pill px-4 fw-bold">MASUK</a>
    </nav>

    <header class="hero-section no-print mb-4">
        <h1>Sistem Informasi Kajian MAKN</h1>
        <div class="fw-700 text-muted">Jadwal dan Jurnal Kehadiran Guru Kajian Real-time.</div>
    </header>

    <div class="stats-row no-print mb-4">
        <div class="stat-card"><h3><?= $hadir ?></h3><p class="small fw-bold text-muted">Hadir</p></div>
        <div class="stat-card"><h3><?= $izin ?></h3><p class="small fw-bold text-muted">Izin/Sakit</p></div>
        <div class="stat-card"><h3><?= $pending ?></h3><p class="small fw-bold text-muted">Pending</p></div>
    </div>
<br><br>
    <div class="row g-3 mb-5 no-print">
        <div class="col-md-6">
            <div class="mb-3">
    <div class="fw-800 fw-bold border border-primary shadow-sm text-center d-flex align-items-center justify-content-center" 
         style="width: 160px; height: 35px; border-radius: 6px; font-size: 0.8rem; background-color: white; color: #0d6efd; text-transform: uppercase; letter-spacing: 0.5px; border-width: 1.5px !important;">
        AGENDA PUTRA
    </div>
</div>
            <?php renderAgendaCards($conn, 'L'); ?>
        </div>
        <div class="col-md-6">
            <div class="mb-3">
    <div class="fw-800 fw-bold border border-danger shadow-sm text-center d-flex align-items-center justify-content-center" 
         style="width: 160px; height: 35px; border-radius: 6px; font-size: 0.8rem; background-color: white; color: #dc3545; text-transform: uppercase; letter-spacing: 0.5px; border-width: 1.5px !important;">
        AGENDA PUTRI
    </div>
</div>
            <?php renderAgendaCards($conn, 'P'); ?>
        </div>
    </div>

    <div class="d-flex align-items-center justify-content-between mb-3 no-print">
        <div class="fw-800 m-0 fw-bold border border-secondary shadow-sm text-center d-flex align-items-center justify-content-center" 
         style="width:280px; height:50px; border-radius:10px; font-size:1.1rem; background-color: white; color: #343a40; text-transform: uppercase; letter-spacing: 0.5px;">
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

    <div class="d-flex align-items-center justify-content-between mb-3">
        <div class="fw-800 m-0 fw-bold border border-secondary shadow-sm text-center d-flex align-items-center justify-content-center" 
         style="width:280px; height:50px; border-radius:10px; font-size:1.1rem; background-color: white; color: #343a40; text-transform: uppercase; letter-spacing: 0.5px;">
        JURNAL KAJIAN HARIAN
    </div>
        <div class="d-flex gap-2 no-print">
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