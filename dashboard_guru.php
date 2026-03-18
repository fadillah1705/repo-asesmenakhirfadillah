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
        ? "UPDATE users SET nama_lengkap='$nama_baru', username='$user_baru', password='$pw_baru' WHERE id='$id_user'"
        : "UPDATE users SET nama_lengkap='$nama_baru', username='$user_baru' WHERE id='$id_user'";
    
    if (mysqli_query($conn, $sql_update)) {
        $swal_msg = ['icon' => 'success', 'title' => 'Berhasil!', 'text' => 'Profil diperbarui.'];
        echo "<script>setTimeout(() => { window.location.href='dashboard_guru.php'; }, 1200);</script>";
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
        $swal_msg = ['icon' => 'success', 'title' => 'Berhasil!', 'text' => 'Data telah diperbarui.'];
    }
}

// --- 5. DETEKSI JADWAL ---
$hari_list = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
$hari_ini = $hari_list[date('w')];
$q_skrg = mysqli_query($conn, "SELECT * FROM jadwal WHERE guru_id='$id_user' AND hari='$hari_ini' LIMIT 1");
$d_skrg = mysqli_fetch_assoc($q_skrg);

// --- 6. FUNGSI TABEL MATRIKS ---
function renderTabelMatriks($conn, $id_user, $g) {
    $haris = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    $kelass = ['X', 'XI', 'XII'];
    echo '<div class="table-responsive"><table class="table table-bordered text-center align-middle m-0">
            <thead style="background:#1e293b; color:white;"><tr><th class="py-3" style="width:100px;">KELAS</th>';
    foreach($haris as $h) echo "<th style='min-width:130px;'>$h</th>";
    echo '</tr></thead><tbody>';
    foreach($kelass as $k) {
        echo "<tr><td class='fw-bold text-primary bg-light'>$k</td>";
        foreach($haris as $h) {
            $q = mysqli_query($conn, "SELECT j.*, u.nama_lengkap FROM jadwal j LEFT JOIN users u ON j.guru_id=u.id WHERE j.hari='$h' AND j.kelas='$k' AND j.gender='$g'");
            $d = mysqli_fetch_assoc($q);
            echo "<td>";
            if($d) {
                $is_me = ($d['guru_id'] == $id_user);
                $bg = $is_me ? '#eef2ff' : '#ffffff';
                echo "<div class='mx-auto shadow-sm p-3' style='background:$bg; border:1px solid ".($is_me?'#4338ca':'#e2e8f0')."; border-radius:10px;'>
                        <div class='fw-bold mb-1' style='font-size:13px;'>{$d['mapel']}</div>
                        <div class='text-muted' style='font-size:11px;'><i class='fas fa-user-tie me-1'></i>Ust. ".explode(' ', $d['nama_lengkap'])[0]."</div>
                      </div>";
            } else { echo "<span class='text-muted opacity-25' style='font-size:11px;'>- Kosong -</span>"; }
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
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap');
        body { background: #f1f5f9; font-family: 'Plus Jakarta Sans', sans-serif; }
        .navbar { background: white; border-bottom: 1px solid #e2e8f0; padding: 12px 0; }
        .greeting-text { font-size: 0.85rem; color: #64748b; margin-right: 15px; }
        .btn-nav { font-size: 0.8rem; font-weight: 700; padding: 8px 16px; border-radius: 10px; transition: 0.3s; }
        .hero-info { background: linear-gradient(135deg, #4338ca 0%, #3730a3 100%); color: white; padding: 30px; border-radius: 16px; }
        .card-custom { border-radius: 16px; border: none; box-shadow: 0 4px 20px rgba(0,0,0,0.04); background: white; }
        .nav-tabs .nav-link { border:none; color: #64748b; font-weight: 600; padding: 10px 25px; }
        .nav-tabs .nav-link.active { border:none; border-bottom: 3px solid #4338ca; color: #4338ca; background: transparent; }
        /* Modal Style */
        .modal-content { border-radius: 20px; border: none; overflow: hidden; }
        .modal-header { background: #f8fafc; border-bottom: 1px solid #f1f5f9; padding: 20px; }
        .form-label-custom { font-size: 0.75rem; font-weight: 700; color: #475569; text-uppercase; letter-spacing: 0.5px; }
        .form-control-custom { border-radius: 10px; padding: 10px 15px; border: 1px solid #e2e8f0; font-size: 0.9rem; }
        .form-control-custom:focus { box-shadow: 0 0 0 3px rgba(67, 56, 202, 0.1); border-color: #4338ca; }
    </style>
</head>
<body>

<nav class="navbar sticky-top">
    <div class="container d-flex align-items-center">
        <span class="fw-bold text-primary me-auto" style="font-size: 1.1rem; letter-spacing:-0.5px;">DASHBOARD GURU</span>
        <div class="d-flex align-items-center">
             <span class="greeting-text d-none d-md-block">
                <i class="far fa-user-circle me-1"></i> (Guru) <?= $nama_user ?>
            </span>
            <div class="d-flex gap-2">
                <a href="index.php" class="btn btn-light btn-nav border"><i class="fas fa-home"></i></a>
                <button class="btn btn-light btn-nav border text-primary" data-bs-toggle="modal" data-bs-target="#modalProfil">Profil</button>
                <a href="javascript:void(0)" onclick="confirmLogout()" class="btn btn-danger btn-nav">Keluar</a>
            </div>
        </div>
    </div>
</nav>

<div class="container mt-4 pb-5">
    <div class="row g-4">
        <div class="col-md-5">
            <div class="hero-info shadow-sm h-100 text-center d-flex flex-column justify-content-center">
                <?php if($d_skrg): ?>
                    <h6 class="opacity-75 text-uppercase fw-bold small mb-2" style="letter-spacing:1px;">Jadwal Sekarang</h6>
                    <h2 class="fw-bold mb-3"><?= $d_skrg['mapel'] ?></h2>
                    <div>
                        <span class="badge bg-white text-primary px-3 py-2" style="font-size:13px; border-radius:8px;">
                            Kelas <?= $d_skrg['kelas'] ?> • <?= $d_skrg['gender']=='L'?'Putra':'Putri' ?>
                        </span>
                    </div>
                <?php else: ?>
                    <i class="fas fa-calendar-day fa-3x mb-3 opacity-25"></i>
                    <h5 class="fw-bold">Tidak Ada Jadwal</h5>
                    <p class="small opacity-75">Silakan beristirahat atau cek jadwal esok hari.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-md-7">
            <div class="card card-custom p-4 h-100">
                <?php 
                if($d_skrg): 
                    $tgl_cek = date('Y-m-d');
                    $sql_cek = mysqli_query($conn, "SELECT status_hadir, materi FROM jurnal WHERE jadwal_id='{$d_skrg['id']}' AND tanggal='$tgl_cek'");
                    $data_jurnal = mysqli_fetch_assoc($sql_cek);
                    
                    if(!$data_jurnal): ?>
                        <h6 class="fw-bold mb-4 text-dark"><i class="fas fa-fingerprint me-2 text-primary"></i>PRESENSI KEHADIRAN</h6>
                        <form method="POST">
                            <input type="hidden" name="jadwal_id" value="<?= $d_skrg['id'] ?>">
                            <div class="mb-3">
                                <label class="form-label-custom">STATUS MENGAJAR</label>
                                <select name="status" class="form-select form-control-custom" onchange="toggleIzin(this.value)">
                                    <option value="Hadir">Hadir Di Kelas</option>
                                    <option value="Izin">Izin / Berhalangan</option>
                                </select>
                            </div>
                            <div id="boxAlasan" class="mb-3 d-none">
                                <label class="form-label-custom">ALASAN IZIN</label>
                                <textarea name="alasan" class="form-control form-control-custom" rows="3" placeholder="Contoh: Sakit, Tugas Luar, dll..."></textarea>
                            </div>
                            <button type="submit" name="simpan_laporan" class="btn btn-primary w-100 fw-bold py-2 shadow-sm" style="border-radius:10px;">SIMPAN KEHADIRAN</button>
                        </form>

                    <?php elseif($data_jurnal['status_hadir'] == 'Hadir' && trim($data_jurnal['materi']) == ""): ?>
                        <div class="alert alert-warning border-0 shadow-sm mb-4 d-flex align-items-center" style="border-radius:12px;">
                            <i class="fas fa-info-circle fa-lg me-3 text-warning"></i>
                            <span class="small"><strong>Hampir Selesai!</strong> Anda sudah absen. Sekarang masukkan ringkasan materi kajian.</span>
                        </div>
                        <h6 class="fw-bold mb-3 text-dark small"><i class="fas fa-edit me-2 text-primary"></i>RINGKASAN MATERI</h6>
                        <form method="POST">
                            <input type="hidden" name="jadwal_id" value="<?= $d_skrg['id'] ?>">
                            <input type="hidden" name="status" value="Hadir">
                            <div class="mb-3">
                                <textarea name="jurnal" class="form-control form-control-custom" rows="5" placeholder="Tuliskan kitab & materi yang dibahas..." required></textarea>
                            </div>
                            <button type="submit" name="simpan_laporan" class="btn btn-success w-100 fw-bold py-2 shadow-sm" style="border-radius:10px;">SELESAIKAN LAPORAN</button>
                        </form>

                    <?php else: ?>
                        <div class="text-center my-auto py-4">
                            <div class="mb-3 shadow-sm d-inline-block p-3" style="background:#f0fdf4; border-radius:50%; color:#15803d;">
                                <i class="fas fa-check fa-3x"></i>
                            </div>
                            <h5 class="fw-bold">Laporan Selesai!</h5>
                            <p class="text-muted small">Data telah tersinkronisasi ke pusat.</p>
                            <div class="bg-light p-3 rounded-4 text-start mt-3" style="font-size:12.5px; border-left:4px solid #15803d;">
                                <div class="mb-1"><strong>Status:</strong> <span class="badge bg-success"><?= $data_jurnal['status_hadir'] ?></span></div>
                                <div><strong>Materi:</strong> <?= $data_jurnal['materi'] ?></div>
                            </div>
                           
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="text-center my-auto opacity-50 py-5">
                        <i class="fas fa-lock fa-3x mb-3"></i>
                        <p class="small fw-bold">Form Belum Terbuka</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="mt-5">
        <div class="d-flex align-items-center mb-3">
            <h6 class="fw-bold text-dark m-0 small text-uppercase" style="letter-spacing:1px;">Jadwal Kajian Seminggu</h6>
            <div class="ms-auto small text-muted"><i class="fas fa-info-circle me-1"></i> Geser tabel jika layar kecil</div>
        </div>
        <div class="card card-custom p-3">
            <nav><div class="nav nav-tabs justify-content-center border-0 mb-4">
                <button class="nav-link active border-0" data-bs-toggle="tab" data-bs-target="#tabL"><i class="fas fa-male me-2"></i>ASRAMA PUTRA</button>
                <button class="nav-link border-0 ms-3" data-bs-toggle="tab" data-bs-target="#tabP"><i class="fas fa-female me-2"></i>ASRAMA PUTRI</button>
            </div></nav>
            <div class="tab-content">
                <div class="tab-pane fade show active" id="tabL"><?php renderTabelMatriks($conn, $id_user, 'L'); ?></div>
                <div class="tab-pane fade" id="tabP"><?php renderTabelMatriks($conn, $id_user, 'P'); ?></div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalProfil" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg">
            <div class="modal-header border-0">
                <h5 class="fw-bold text-dark m-0"><i class="fas fa-user-cog me-2 text-primary"></i>Pengaturan Akun</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <div class="text-center mb-4">
                        <div class="bg-primary text-white d-inline-flex align-items-center justify-content-center shadow" style="width:70px; height:70px; border-radius:20px; font-size:1.5rem;">
                            <i class="fas fa-id-badge"></i>
                        </div>
                        <p class="small text-muted mt-2">Ubah informasi login Anda di sini</p>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label-custom">Nama Lengkap</label>
                        <input type="text" name="nama_lengkap" class="form-control form-control-custom" value="<?= $me['nama_lengkap'] ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom">Username</label>
                        <input type="text" name="username" class="form-control form-control-custom" value="<?= $me['username'] ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom">Password Baru</label>
                        <input type="password" name="password" class="form-control form-control-custom" placeholder="Biarkan kosong jika tetap">
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light fw-bold px-4" data-bs-dismiss="modal" style="border-radius:10px;">Batal</button>
                    <button type="submit" name="update_profil" class="btn btn-primary fw-bold px-4" style="border-radius:10px;">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function toggleIzin(v) {
        document.getElementById('boxAlasan').classList.toggle('d-none', v == 'Hadir');
    }
    function confirmLogout() {
        Swal.fire({ 
            title: 'Ingin Keluar?', 
            text: "Pastikan semua laporan hari ini sudah terisi.",
            icon: 'question', 
            showCancelButton: true, 
            confirmButtonColor: '#4338ca',
            confirmButtonText: 'Ya, Keluar' 
        }).then(r => { if(r.isConfirmed) window.location.href='logout.php'; });
    }
</script>
<?php if($swal_msg): ?><script>Swal.fire({icon:'<?= $swal_msg['icon']?>', title:'<?= $swal_msg['title']?>', text:'<?= $swal_msg['text']?>'});</script><?php endif; ?>
</body>
</html>