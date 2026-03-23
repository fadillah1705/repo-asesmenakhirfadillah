<?php 
include 'koneksi.php'; 
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// --- 1. PROTEKSI ---
if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'guru') { 
    header("Location: login.php"); exit; 
}

$id_user = $_SESSION['id'];
$swal_msg = null;

// --- 2. DATA USER ---
$q_me = mysqli_query($conn, "SELECT * FROM users WHERE id='$id_user'");
$me = mysqli_fetch_assoc($q_me);
$nama_user = $me['nama_lengkap'] ?? 'Guru';

// --- 3. LOGIKA UPDATE PROFIL ---
if (isset($_POST['update_profil'])) {
    $nama_baru = mysqli_real_escape_string($conn, $_POST['nama_lengkap']);
    $user_baru = mysqli_real_escape_string($conn, $_POST['username']);
    $pw_baru = $_POST['password'];
    $sql_update = !empty($pw_baru) 
        ? "UPDATE users SET nama_lengkap='$nama_baru', username='$user_baru', password='".md5($pw_baru)."' WHERE id='$id_user'"
        : "UPDATE users SET nama_lengkap='$nama_baru', username='$user_baru' WHERE id='$id_user'";
    
    if (mysqli_query($conn, $sql_update)) {
        $_SESSION['nama_lengkap'] = $nama_baru;
        $swal_msg = ['icon' => 'success', 'title' => 'Berhasil!', 'text' => 'Profil diperbarui.'];
    }
}

// --- 4. LOGIKA SIMPAN (KEHADIRAN & JURNAL) ---
if (isset($_POST['simpan_laporan'])) {
    $jadwal_id = $_POST['jadwal_id'];
    $status = $_POST['status']; 
    $materi = isset($_POST['jurnal']) ? mysqli_real_escape_string($conn, $_POST['jurnal']) : '';
    $alasan = isset($_POST['alasan']) ? mysqli_real_escape_string($conn, $_POST['alasan']) : '';
    $tgl = date('Y-m-d');

    if($status == 'Izin') { $materi = $alasan; }
    
    $cek_query = mysqli_query($conn, "SELECT id FROM jurnal WHERE jadwal_id='$jadwal_id' AND tanggal='$tgl'");
    
    if(mysqli_num_rows($cek_query) > 0) {
        $sql_aksi = "UPDATE jurnal SET status_hadir='$status', materi='$materi' WHERE jadwal_id='$jadwal_id' AND tanggal='$tgl'";
    } else {
        $sql_aksi = "INSERT INTO jurnal (jadwal_id, tanggal, status_hadir, materi) VALUES ('$jadwal_id', '$tgl', '$status', '$materi')";
    }

    if(mysqli_query($conn, $sql_aksi)) {
        $swal_msg = ['icon' => 'success', 'title' => 'Berhasil!', 'text' => 'Laporan telah disimpan.'];
    }
}

// --- 5. DETEKSI JADWAL HARI INI ---
$hari_list = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
$hari_ini = $hari_list[date('w')];
$q_skrg = mysqli_query($conn, "SELECT * FROM jadwal WHERE guru_id='$id_user' AND hari='$hari_ini' LIMIT 1");
$d_skrg = mysqli_fetch_assoc($q_skrg);

// --- 6. FUNGSI RENDER TABEL (GAYA DASHBOARD.PHP TANPA AKSI) ---
function renderTabelMatriks($conn, $id_user, $g) {
    $haris = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu']; 
    $kelass = ['X', 'XI', 'XII'];
    
    echo '<div class="table-responsive border shadow-sm rounded-4">
            <table class="table table-bordered align-middle mb-0 text-center custom-table">
            <thead>
                <tr>
                    <th class="sticky-col header-col" style="width:80px;">KELAS</th>';
    foreach($haris as $h) echo "<th>$h</th>";
    echo '</tr></thead><tbody class="bg-white">';

    foreach($kelass as $k) {
        echo "<tr><td class='sticky-col fw-bold text-primary bg-light'>$k</td>";
        foreach($haris as $h) {
            $q = mysqli_query($conn, "SELECT j.*, u.nama_lengkap FROM jadwal j LEFT JOIN users u ON j.guru_id=u.id WHERE j.hari='$h' AND j.kelas='$k' AND j.gender='$g'");
            $d = mysqli_fetch_assoc($q);
            
            echo "<td class='p-2'>";
            if($d) {
                $is_me = ($d['guru_id'] == $id_user);
                $class_me = $is_me ? 'is-me' : '';
                $nama_pendek = explode(' ', $d['nama_lengkap'])[0];
                
                echo "<div class='schedule-card $class_me'>
                        <div class='fw-bold text-dark mb-1' style='font-size:11.5px;'>{$d['mapel']}</div>
                        <div class='text-primary' style='font-size:10.5px;'>
                            <i class='fas fa-user-tie me-1'></i>Ust. $nama_pendek
                        </div>
                      </div>";
            } else { 
                echo "<span class='text-muted opacity-25 small'>-</span>"; 
            }
            echo "</td>";
        }
        echo "</tr>";
    }
    echo '</tbody></table></div>';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Guru | MAKN</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --accent: #4338ca; --dark: #0f172a; --bg: #f1f5f9; }
        body { background: var(--bg); font-family: 'Plus Jakarta Sans', sans-serif; color: #334155; }
        
        .navbar { background: white; border-bottom: 1px solid #e2e8f0; padding: 12px 0; }
        .hero-info { background: linear-gradient(135deg, #4338ca 0%, #3730a3 100%); color: white; padding: 30px; border-radius: 16px; }
        .card-custom { border-radius: 16px; border: none; box-shadow: 0 4px 20px rgba(0,0,0,0.04); background: white; }
        
        /* Table Styling from dashboard.php */
        .table-responsive { overflow-x: auto; }
        .custom-table { min-width: 1100px; border-collapse: separate; border-spacing: 0; }
        .custom-table thead th { background: var(--dark); color: white; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; border: none; padding: 15px; }
        
        .sticky-col { position: sticky; left: 0; z-index: 5; background: #f8fafc !important; border-right: 2px solid #e2e8f0 !important; }
        .header-col { background: var(--dark) !important; }

        .schedule-card { background: #fff; border-radius: 10px; padding: 12px; transition: 0.2s; border: 1px solid #e2e8f0; text-align: center; }
        .schedule-card.is-me { background: #eef2ff; border: 1.5px solid var(--accent) !important; box-shadow: 0 4px 6px -1px rgba(67, 56, 202, 0.1); }
        
        .nav-tabs .nav-link { border:none; color: #64748b; font-weight: 700; font-size: 13px; padding: 12px 25px; }
        .nav-tabs .nav-link.active { border:none; border-bottom: 3px solid var(--accent); color: var(--accent); background: transparent; }
        
        .form-control-custom { border-radius: 10px; padding: 10px 15px; border: 1px solid #e2e8f0; font-size: 0.9rem; }
        .btn-nav { font-size: 0.8rem; font-weight: 700; padding: 8px 16px; border-radius: 10px; }
    </style>
</head>
<body>

<nav class="navbar sticky-top">
    <div class="container d-flex align-items-center">
        <span class="fw-bold text-primary me-auto" style="font-size: 1.1rem; letter-spacing:-0.5px;">DASHBOARD GURU</span>
        <div class="d-flex gap-2">
            <a href="index.php" class="btn btn-light btn-nav border"><i class="fas fa-home"></i></a>
            <button class="btn btn-light btn-nav border text-primary" data-bs-toggle="modal" data-bs-target="#modalProfil">Profil</button>
            <a href="javascript:void(0)" onclick="confirmLogout()" class="btn btn-danger btn-nav">Keluar</a>
        </div>
    </div>
</nav>

<div class="container mt-4 pb-5">
    <div class="row g-4 mb-5">
        <div class="col-md-5">
            <div class="hero-info shadow-sm h-100 text-center d-flex flex-column justify-content-center">
                <?php if($d_skrg): ?>
                    <h6 class="opacity-75 text-uppercase fw-bold small mb-2">Jadwal Sekarang</h6>
                    <h2 class="fw-bold mb-3"><?= $d_skrg['mapel'] ?></h2>
                    <div><span class="badge bg-white text-primary px-3 py-2" style="border-radius:8px;">Kelas <?= $d_skrg['kelas'] ?> • <?= $d_skrg['gender']=='L'?'Putra':'Putri' ?></span></div>
                <?php else: ?>
                    <i class="fas fa-calendar-check fa-3x mb-3 opacity-25"></i>
                    <h5 class="fw-bold">Tidak Ada Jadwal Mengajar</h5>
                    <p class="small opacity-75">Silahkan cek kembali jadwal esok hari.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-md-7">
            <div class="card card-custom p-4 h-100">
                <?php if($d_skrg): 
                    $tgl_cek = date('Y-m-d');
                    $sql_cek = mysqli_query($conn, "SELECT status_hadir, materi FROM jurnal WHERE jadwal_id='{$d_skrg['id']}' AND tanggal='$tgl_cek'");
                    $data_jurnal = mysqli_fetch_assoc($sql_cek);
                    
                    if(!$data_jurnal): ?>
                        <h6 class="fw-bold mb-4"><i class="fas fa-fingerprint me-2 text-primary"></i>PRESENSI KEHADIRAN</h6>
                        <form method="POST">
                            <input type="hidden" name="jadwal_id" value="<?= $d_skrg['id'] ?>">
                            <div class="mb-3">
                                <label class="small fw-bold text-muted mb-1">STATUS</label>
                                <select name="status" class="form-select form-control-custom" onchange="toggleIzin(this.value)">
                                    <option value="Hadir">Hadir Di Kelas</option>
                                    <option value="Izin">Izin / Berhalangan</option>
                                </select>
                            </div>
                            <div id="boxAlasan" class="mb-3 d-none">
                                <label class="small fw-bold text-muted mb-1">ALASAN</label>
                                <textarea name="alasan" class="form-control form-control-custom" rows="2"></textarea>
                            </div>
                            <button type="submit" name="simpan_laporan" class="btn btn-primary w-100 fw-bold py-2">SIMPAN KEHADIRAN</button>
                        </form>
                    <?php elseif($data_jurnal['status_hadir'] == 'Hadir' && empty($data_jurnal['materi'])): ?>
                        <h6 class="fw-bold mb-3"><i class="fas fa-edit me-2 text-primary"></i>RINGKASAN MATERI</h6>
                        <form method="POST">
                            <input type="hidden" name="jadwal_id" value="<?= $d_skrg['id'] ?>">
                            <input type="hidden" name="status" value="Hadir">
                            <textarea name="jurnal" class="form-control form-control-custom mb-3" rows="4" placeholder="Tuliskan kitab & materi..." required></textarea>
                            <button type="submit" name="simpan_laporan" class="btn btn-success w-100 fw-bold py-2">KIRIM JURNAL</button>
                        </form>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <div class="mb-3 d-inline-block p-3 bg-success-subtle rounded-circle text-success"><i class="fas fa-check fa-3x"></i></div>
                            <h5 class="fw-bold">Laporan Terkirim!</h5>
                            <p class="text-muted small">Terima kasih atas kedisiplinan Anda hari ini.</p>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="text-center my-auto opacity-50 py-5"><i class="fas fa-lock fa-3x mb-3"></i><p class="fw-bold">Form Jurnal Belum Terbuka</p></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="mt-4">
        <div class="d-flex align-items-center mb-3">
            <h6 class="fw-bold text-dark m-0 small text-uppercase" style="letter-spacing:1px;">Jadwal Kajian Seminggu</h6>
        </div>
        <div class="card card-custom p-3">
            <nav>
                <div class="nav nav-tabs justify-content-center border-0 mb-4">
                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabL">ASRAMA PUTRA</button>
                    <button class="nav-link ms-3" data-bs-toggle="tab" data-bs-target="#tabP">ASRAMA PUTRI</button>
                </div>
            </nav>
            <div class="tab-content">
                <div class="tab-pane fade show active" id="tabL"><?php renderTabelMatriks($conn, $id_user, 'L'); ?></div>
                <div class="tab-pane fade" id="tabP"><?php renderTabelMatriks($conn, $id_user, 'P'); ?></div>
            </div>
            <div class="mt-3 text-center small text-muted"><i class="fas fa-info-circle me-1"></i> Kotak biru adalah jadwal mengajar Anda.</div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalProfil" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <form method="POST" class="modal-content border-0 shadow-lg" style="border-radius:20px;">
            <div class="modal-body p-4 text-center">
                <h5 class="fw-bold mb-4">Edit Profil</h5>
                <div class="text-start mb-3">
                    <label class="small fw-bold text-muted">Nama Lengkap</label>
                    <input type="text" name="nama_lengkap" class="form-control form-control-custom" value="<?= $me['nama_lengkap'] ?>" required>
                </div>
                <div class="text-start mb-3">
                    <label class="small fw-bold text-muted">Username</label>
                    <input type="text" name="username" class="form-control form-control-custom" value="<?= $me['username'] ?>" required>
                </div>
                <div class="text-start mb-4">
                    <label class="small fw-bold text-muted">Password Baru</label>
                    <input type="password" name="password" class="form-control form-control-custom" placeholder="Kosongkan jika tetap">
                </div>
                <button type="submit" name="update_profil" class="btn btn-primary w-100 py-2 mb-2">Simpan</button>
                <button type="button" class="btn btn-light w-100" data-bs-dismiss="modal">Batal</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function toggleIzin(v) { document.getElementById('boxAlasan').classList.toggle('d-none', v == 'Hadir'); }
    function confirmLogout() {
        Swal.fire({ 
            title: 'Ingin Keluar?', icon: 'question', showCancelButton: true, confirmButtonColor: '#4338ca', confirmButtonText: 'Ya, Keluar' 
        }).then(r => { if(r.isConfirmed) window.location.href='logout.php'; });
    }
</script>
<?php if($swal_msg): ?>
<script>Swal.fire({icon:'<?= $swal_msg['icon']?>', title:'<?= $swal_msg['title']?>', text:'<?= $swal_msg['text']?>'});</script>
<?php endif; ?>
</body>
</html>