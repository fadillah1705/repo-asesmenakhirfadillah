<?php 
include 'koneksi.php'; 

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['id'])) { header("Location: login.php"); exit; }

$id_user = $_SESSION['id'];
$nama_user = $_SESSION['nama_lengkap'] ?? $_SESSION['nama'];
$swal_msg = $_SESSION['swal_msg'] ?? null;
unset($_SESSION['swal_msg']);

// Pastikan urutan hari lengkap
$days = ['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'];

// --- 1. PROSES UPDATE PROFIL ---
if (isset($_POST['update_profil'])) {
    $nama_baru = mysqli_real_escape_string($conn, $_POST['nama_profil']);
    $user_baru = mysqli_real_escape_string($conn, $_POST['user_profil']);
    $pass_baru = $_POST['pass_profil'];
    $sql = (!empty($pass_baru)) ? 
        "UPDATE users SET nama_lengkap='$nama_baru', username='$user_baru', password='".md5($pass_baru)."' WHERE id='$id_user'" :
        "UPDATE users SET nama_lengkap='$nama_baru', username='$user_baru' WHERE id='$id_user'";
    if (mysqli_query($conn, $sql)) {
        $_SESSION['nama_lengkap'] = $nama_baru;
        $_SESSION['swal_msg'] = ['icon' => 'success', 'title' => 'Berhasil!', 'text' => 'Profil diperbarui.'];
        header("Location: dashboard.php"); exit;
    }
}

// --- 2. PROSES TAMBAH GURU ---
if (isset($_POST['tambah_guru'])) {
    $nama = mysqli_real_escape_string($conn, $_POST['nama_lengkap']);
    $user = mysqli_real_escape_string($conn, $_POST['username']);
    $pass = md5($_POST['password']);
    if (mysqli_query($conn, "INSERT INTO users (nama_lengkap, username, password, role) VALUES ('$nama', '$user', '$pass', 'guru')")) {
        $_SESSION['swal_msg'] = ['icon' => 'success', 'title' => 'Berhasil!', 'text' => 'Akun guru ditambahkan.'];
        header("Location: dashboard.php"); exit;
    }
}

// --- 3. PROSES HAPUS GURU ---
if (isset($_GET['hapus_guru'])) {
    $id_g = $_GET['hapus_guru'];
    if ($id_g != $id_user) {
        mysqli_query($conn, "DELETE FROM jurnal WHERE jadwal_id IN (SELECT id FROM jadwal WHERE guru_id='$id_g')");
        mysqli_query($conn, "DELETE FROM jadwal WHERE guru_id='$id_g'");
        mysqli_query($conn, "DELETE FROM users WHERE id='$id_g'");
        $_SESSION['swal_msg'] = ['icon' => 'warning', 'title' => 'Dihapus!', 'text' => 'Akun guru dihapus.'];
    }
    header("Location: dashboard.php"); exit;
}

// --- 4. PROSES TAMBAH JADWAL ---
// --- 4. PROSES TAMBAH JADWAL (CEK VALIDASI) ---
if (isset($_POST['tambah_jadwal'])) {
    $hari = $_POST['hari'];
    $gender = $_POST['gender'];
    $sudah_ada = [];

    foreach(['x','xi','xii'] as $k) {
        $kelas = strtoupper($k);
        $mapel = mysqli_real_escape_string($conn, $_POST["mapel_$k"]);
        $guru_id = $_POST["guru_$k"];

        if (!empty($mapel) && !empty($guru_id)) {
            // CEK: Apakah jadwal sudah ada?
            $cek = mysqli_query($conn, "SELECT id FROM jadwal WHERE hari='$hari' AND kelas='$kelas' AND gender='$gender'");
            
            if (mysqli_num_rows($cek) > 0) {
                // Jika ditemukan jadwal lama, catat kelasnya
                $sudah_ada[] = $kelas;
            } else {
                // Jika kosong, baru boleh simpan
                mysqli_query($conn, "INSERT INTO jadwal (hari, kelas, gender, mapel, guru_id) VALUES ('$hari', '$kelas', '$gender', '$mapel', '$guru_id')");
            }
        }
    }

    if (!empty($sudah_ada)) {
        $list_kelas = implode(", ", $sudah_ada);
        $_SESSION['swal_msg'] = [
            'icon' => 'error', 
            'title' => 'Gagal Simpan!', 
            'text' => "Jadwal Kelas $list_kelas pada hari $hari sudah terisi. Hapus dulu jadwal lama jika ingin mengganti!"
        ];
    } else {
        $_SESSION['swal_msg'] = [
            'icon' => 'success', 
            'title' => 'Berhasil!', 
            'text' => 'Jadwal baru berhasil disimpan.'
        ];
    }
    header("Location: dashboard.php"); exit;
}

// --- 5. PROSES UPDATE JADWAL ---
if (isset($_POST['update_jadwal'])) {
    $id_j = $_POST['id_jadwal_edit'];
    $m_baru = mysqli_real_escape_string($conn, $_POST['mapel_edit']);
    $g_baru = $_POST['guru_edit'];
    if (mysqli_query($conn, "UPDATE jadwal SET mapel='$m_baru', guru_id='$g_baru' WHERE id='$id_j'")) {
        $_SESSION['swal_msg'] = ['icon' => 'success', 'title' => 'Berhasil!', 'text' => 'Jadwal diperbarui.'];
    }
    header("Location: dashboard.php"); exit;
}

// --- 6. PROSES HAPUS JADWAL ---
if (isset($_GET['hapus_jadwal'])) {
    $id_h = $_GET['hapus_jadwal'];
    mysqli_query($conn, "DELETE FROM jurnal WHERE jadwal_id='$id_h'");
    mysqli_query($conn, "DELETE FROM jadwal WHERE id='$id_h'");
    $_SESSION['swal_msg'] = ['icon' => 'warning', 'title' => 'Dihapus!', 'text' => 'Jadwal dihapus.'];
    header("Location: dashboard.php"); exit;
}

$sql_guru = "SELECT u.id, u.nama_lengkap, 
    (SELECT GROUP_CONCAT(DISTINCT mapel SEPARATOR ', ') FROM jadwal WHERE guru_id = u.id) as list_mapel,
    (SELECT COUNT(*) FROM jurnal jr JOIN jadwal jd ON jr.jadwal_id = jd.id WHERE jd.guru_id = u.id AND jr.status_hadir = 'Hadir') as jml_hadir,
    (SELECT COUNT(*) FROM jurnal jr JOIN jadwal jd ON jr.jadwal_id = jd.id WHERE jd.guru_id = u.id AND jr.status_hadir = 'Izin') as jml_izin
    FROM users u WHERE u.role='guru' ORDER BY u.nama_lengkap ASC";
$gl = mysqli_query($conn, $sql_guru);

function renderMatrix($conn, $gender, $days) {
    $levels = ['X','XI','XII'];
    echo "<div class='table-responsive rounded-3 shadow-sm border'>
            <table class='table table-bordered align-middle mb-0 text-center custom-table'>
            <thead>
                <tr class='bg-dark text-white'>
                    <th class='py-3 sticky-col header-col' style='width:80px;'>KELAS</th>";
    foreach($days as $h) echo "<th style='min-width:150px;'>$h</th>";
    echo "</tr></thead><tbody class='bg-white'>";
    foreach($levels as $k) {
        echo "<tr><td class='fw-bold text-primary bg-light sticky-col'>$k</td>";
        foreach($days as $h) {
            $q = mysqli_query($conn, "SELECT j.*, u.nama_lengkap FROM jadwal j LEFT JOIN users u ON j.guru_id = u.id WHERE j.hari='$h' AND j.kelas='$k' AND j.gender='$gender'");
            echo "<td class='p-2'>";
            if($d = mysqli_fetch_assoc($q)) {
                $np = explode(' ', $d['nama_lengkap'])[0];
                echo "<div class='schedule-card p-2 border shadow-sm'>
                        <div class='fw-bold text-dark mb-1' style='font-size:11.5px;'>{$d['mapel']}</div>
                        <div class='text-primary mb-2' style='font-size:10.5px;'><i class='fas fa-user-tie me-1'></i>Ust. $np</div>
                        <div class='d-flex justify-content-center gap-2 border-top pt-2'>
                            <button class='btn-action-sm text-primary' onclick='openEdit(\"{$d['id']}\", \"".addslashes($d['mapel'])."\", \"{$d['guru_id']}\")'><i class='fas fa-pencil-alt'></i></button>
                            <button class='btn-action-sm text-danger' onclick='confirmDelete(\"{$d['id']}\")'><i class='fas fa-trash-alt'></i></button>
                        </div>
                      </div>";
            } else { echo "<span class='text-muted opacity-25 small'>-</span>"; }
            echo "</td>";
        }
        echo "</tr>";
    }
    echo "</tbody></table></div>";
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Pembina | MAKN</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --accent: #4338ca; --dark: #0f172a; --bg: #f1f5f9; }
        body { background-color: var(--bg); font-family: 'Plus Jakarta Sans', sans-serif; color: #334155; font-size: 14px; }
        
        .navbar { background: white; border-bottom: 1px solid #e2e8f0; padding: 12px 0; }
        .greeting-text { font-weight: 600; font-size: 0.9rem; color: #64748b; margin-right: 15px; }
        
        .glass-card { background: white; border-radius: 16px; border: none; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .border-top-accent { border-top: 4px solid var(--accent) !important; }
        
        .form-input-custom { border-radius: 10px; border: 1.5px solid #e2e8f0; padding: 10px 14px; font-size: 13.5px; transition: 0.2s; }
        .label-minimal { font-size: 10.5px; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; display: block; }
        
        /* Tabel & Matrix Styling */
        .table-responsive { overflow-x: auto; }
        .custom-table { min-width: 1100px; border-collapse: separate; border-spacing: 0; }
        .custom-table thead th { background: var(--dark); color: white; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; border: none; }
        
        .sticky-col { position: sticky; left: 0; z-index: 5; background: #f8fafc !important; border-right: 2px solid #e2e8f0 !important; }
        .header-col { background: var(--dark) !important; }

        .schedule-card { background: #fff; border-radius: 10px; transition: 0.2s; }
        .schedule-card:hover { transform: scale(1.02); border-color: var(--accent) !important; }
        
        .guru-scroll { max-height: 380px; overflow-y: auto; }
        .guru-item { background: #f8fafc; border-radius: 12px; padding: 12px; margin-bottom: 10px; border: 1px solid #f1f5f9; cursor: pointer; transition: 0.2s; }
        .guru-item:hover { border-color: var(--accent); background: white; }

        .btn-accent { background: var(--accent); color: white; border-radius: 12px; font-weight: 700; padding: 12px; border: none; transition: 0.3s; }
        .btn-action-sm { background: none; border: none; font-size: 13px; padding: 2px 8px; border-radius: 5px; }
        .btn-action-sm:hover { background: #f1f5f9; }

        .nav-pills-custom .nav-link { border-radius: 8px; font-weight: 700; font-size: 12px; color: #64748b; padding: 6px 20px; }
        .nav-pills-custom .nav-link.active { background: var(--accent); color: white; }
    </style>
</head>
<body>

<nav class="navbar sticky-top">
    <div class="container d-flex align-items-center">
        <span class="fw-bold text-primary me-auto" style="font-size: 1.1rem; letter-spacing: -0.5px;">DASHBOARD PEMBINA</span>
        <div class="d-flex align-items-center">
             <span class="greeting-text d-none d-md-block"><i class="far fa-user-circle me-1"></i> (Pembina) <?= $nama_user ?></span>
            <div class="d-flex gap-2">
                <a href="index.php" class="btn btn-light border px-3" style="border-radius:10px;"><i class="fas fa-home"></i></a>
                <button class="btn btn-light border text-primary px-3" style="border-radius:10px; font-weight:700;" data-bs-toggle="modal" data-bs-target="#modalProfil">Edit Profil</button>
                <a href="javascript:void(0)" onclick="confirmLogout()" class="btn btn-danger px-3 shadow-sm" style="border-radius:10px; font-weight:700;">Keluar</a>
            </div>
        </div>
    </div>
</nav>

<div class="container mt-4 pb-5">
    <div class="row g-4">
        <div class="col-lg-3">
            <div class="glass-card p-4 mb-4">
                <h6 class="fw-bold mb-3"><i class="fas fa-users-cog me-2 text-primary"></i>Daftar Guru</h6>
                <div class="guru-scroll">
                    <?php mysqli_data_seek($gl, 0); while($g = mysqli_fetch_assoc($gl)): ?>
                        <div class="guru-item d-flex justify-content-between align-items-center">
                            <div class="text-truncate" onclick="showDetail('<?= addslashes($g['nama_lengkap']) ?>', '<?= addslashes($g['list_mapel'] ?? '-') ?>', '<?= $g['jml_hadir'] ?>', '<?= $g['jml_izin'] ?>')" style="flex:1;">
                                <span class="fw-bold d-block text-dark text-truncate" style="font-size:13px;"><?= $g['nama_lengkap'] ?></span>
                                <small class="text-primary" style="font-size:10px;">Lihat Statistik <i class="fas fa-arrow-right ms-1"></i></small>
                            </div>
                            <button onclick="confirmDeleteGuru('<?= $g['id'] ?>', '<?= addslashes($g['nama_lengkap']) ?>')" class="btn-action-sm text-danger opacity-50"><i class="fas fa-trash"></i></button>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
<br>
            <div class="glass-card p-4">
                <h6 class="fw-bold mb-3"><i class="fas fa-user-plus me-2 text-primary"></i>Tambah Guru</h6>
                <form method="POST">
                    <div class="mb-2"><label class="label-minimal">Nama Lengkap</label><input type="text" name="nama_lengkap" class="form-control form-input-custom" required></div>
                    <div class="mb-2"><label class="label-minimal">Username</label><input type="text" name="username" class="form-control form-input-custom" required></div>
                    <div class="mb-3"><label class="label-minimal">Password</label><input type="password" name="password" class="form-control form-input-custom" required></div>
                    <button type="submit" name="tambah_guru" class="btn btn-accent w-100">SIMPAN GURU</button>
                </form>
            </div>
        </div>

        <div class="col-lg-9">
            <div class="glass-card p-4 mb-4 border-top-accent">
                <h5 class="fw-bold mb-4 text-dark"><i class="fas fa-calendar-plus me-2 text-primary"></i>Input Jadwal Kajian</h5>
                <form method="POST">
                    <div class="row g-3 mb-4 p-3 bg-light rounded-4">
                        <div class="col-md-6"><label class="label-minimal">Hari</label><select name="hari" class="form-select form-input-custom"><?php foreach($days as $h) echo "<option>$h</option>"; ?></select></div>
                        <div class="col-md-6"><label class="label-minimal">Asrama</label><select name="gender" class="form-select form-input-custom"><option value="L">PUTRA (L)</option><option value="P">PUTRI (P)</option></select></div>
                    </div>
                    <div class="row g-3">
                        <?php foreach(['X','XI','XII'] as $k): ?>
                        <div class="col-md-4">
                            <div class="p-3 border rounded-4 bg-white">
                                <div class="badge bg-primary text-white mb-3">KELAS <?= $k ?></div>
                                <div class="mb-2"><label class="label-minimal">Kitab</label><input type="text" name="mapel_<?= strtolower($k) ?>" class="form-control form-input-custom" placeholder="..."></div>
                                <div><label class="label-minimal">Guru</label>
                                    <select name="guru_<?= strtolower($k) ?>" class="form-select form-input-custom">
                                        <option value="">-- Pilih --</option>
                                        <?php mysqli_data_seek($gl, 0); while($g = mysqli_fetch_assoc($gl)) echo "<option value='{$g['id']}'>{$g['nama_lengkap']}</option>"; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="submit" name="tambah_jadwal" class="btn btn-accent w-100 mt-4 shadow-sm">SIMPAN JADWAL</button>
                </form>
            </div>

            <div class="glass-card p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h6 class="fw-bold text-dark m-0"><i class="fas fa-th-large me-2 text-primary"></i>Edit Jadwal Kajian Seminggu</h6>
                    <ul class="nav nav-pills nav-pills-custom bg-light p-1 rounded-3">
                        <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#t-L">PUTRA</button></li>
                        <li class="nav-item"><button class="nav-link ms-1" data-bs-toggle="pill" data-bs-target="#t-P">PUTRI</button></li>
                    </ul>
                </div>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="t-L"><?php renderMatrix($conn, 'L', $days); ?></div>
                    <div class="tab-pane fade" id="t-P"><?php renderMatrix($conn, 'P', $days); ?></div>
                </div>
                <div class="mt-3 small text-muted text-center"><i class="fas fa-info-circle me-1"></i> Geser tabel ke samping untuk melihat seluruh hari</div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalProfil" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <form method="POST" class="modal-content border-0 shadow-lg" style="border-radius: 24px;">
            <div class="modal-body p-4">
                <div class="text-center mb-4">
                    <div class="position-relative d-inline-block">
                        <div class="bg-primary-subtle rounded-circle d-flex align-items-center justify-content-center mx-auto shadow-sm" style="width: 80px; height: 80px;">
                            <i class="fas fa-user-shield fa-2xl text-primary"></i>
                        </div>
                        <span class="position-absolute bottom-0 end-0 badge rounded-pill bg-success border border-2 border-white p-2">
                            <span class="visually-hidden">Online</span>
                        </span>
                    </div>
                    <h5 class="fw-800 mt-3 mb-0 text-dark" style="letter-spacing: -0.5px;"><?= $nama_user ?></h5>
                    <span class="badge bg-primary-subtle text-primary px-3 py-2 rounded-pill fw-bold mt-2" style="font-size: 10px; letter-spacing: 1px;">
                        <i class="fas fa-crown me-1"></i> PEMBINA ASRAMA
                    </span>
                </div>

                <hr class="opacity-50 mb-4">

                <?php 
                $u_profil = mysqli_query($conn, "SELECT * FROM users WHERE id='$id_user'");
                $dp = mysqli_fetch_assoc($u_profil);
                ?>

                <div class="mb-3">
                    <label class="label-minimal text-primary"><i class="fas fa-signature me-1"></i> Nama Lengkap</label>
                    <input type="text" name="nama_profil" class="form-control form-input-custom bg-light" value="<?= $dp['nama_lengkap'] ?>" required>
                </div>

                <div class="mb-3">
                    <label class="label-minimal text-primary"><i class="fas fa-at me-1"></i> Username</label>
                    <input type="text" name="user_profil" class="form-control form-input-custom bg-light" value="<?= $dp['username'] ?>" required>
                </div>

                <div class="mb-4">
                    <label class="label-minimal text-danger"><i class="fas fa-key me-1"></i> Ganti Password</label>
                    <input type="password" name="pass_profil" class="form-control form-input-custom" placeholder="Isi jika ingin ganti">
                    <small class="text-muted mt-1 d-block" style="font-size: 10px;">*Kosongkan jika tidak ingin mengubah password</small>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" name="update_profil" class="btn btn-accent py-3 shadow-sm">
                        <i class="fas fa-check-circle me-2"></i>Simpan Perubahan
                    </button>
                    <button type="button" class="btn btn-light py-2 fw-bold text-muted" data-bs-dismiss="modal" style="border-radius: 12px;">Batal</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalEditJadwal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <form method="POST" class="modal-content shadow-lg border-0" style="border-radius:20px;">
            <div class="modal-body p-4">
                <h6 class="fw-bold text-dark mb-4"><i class="fas fa-edit me-2 text-primary"></i>Ubah Jadwal</h6>
                <input type="hidden" name="id_jadwal_edit" id="id_jadwal_edit">
                <div class="mb-3"><label class="label-minimal">Nama Kitab</label><input type="text" name="mapel_edit" id="mapel_edit" class="form-control form-input-custom" required></div>
                <div class="mb-4"><label class="label-minimal">Guru</label>
                    <select name="guru_edit" id="guru_edit" class="form-select form-input-custom" required>
                        <?php mysqli_data_seek($gl, 0); while($g = mysqli_fetch_assoc($gl)) echo "<option value='{$g['id']}'>{$g['nama_lengkap']}</option>"; ?>
                    </select>
                </div>
                <button type="submit" name="update_jadwal" class="btn btn-accent w-100 mb-2">Update</button>
                <button type="button" class="btn btn-light w-100 fw-bold" data-bs-dismiss="modal">Tutup</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function confirmLogout() {
        Swal.fire({ title: 'Keluar aplikasi?', icon: 'question', showCancelButton: true, confirmButtonColor: '#4338ca', confirmButtonText: 'Ya, Keluar' })
        .then((result) => { if (result.isConfirmed) window.location.href = 'logout.php'; });
    }
    function confirmDelete(id) {
        Swal.fire({ title: 'Hapus jadwal ini?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Ya, Hapus' })
        .then((result) => { if (result.isConfirmed) window.location.href = `dashboard.php?hapus_jadwal=${id}`; });
    }
    function confirmDeleteGuru(id, nama) {
        Swal.fire({ title: 'Hapus Guru?', text: `Ust. ${nama} dan jadwalnya akan hilang!`, icon: 'error', showCancelButton: true, confirmButtonText: 'Hapus' })
        .then((result) => { if (result.isConfirmed) window.location.href = `dashboard.php?hapus_guru=${id}`; });
    }
    function openEdit(id, mapel, guru_id) {
        document.getElementById('id_jadwal_edit').value = id;
        document.getElementById('mapel_edit').value = mapel;
        document.getElementById('guru_edit').value = guru_id;
        new bootstrap.Modal(document.getElementById('modalEditJadwal')).show();
    }
    function showDetail(nama, mapel, hadir, izin) {
        const total = parseInt(hadir) + parseInt(izin);
        const persen = total > 0 ? Math.round((hadir / total) * 100) : 0;
        Swal.fire({
            title: '<small class="text-muted fw-bold">STATISTIK GURU</small>',
            html: `<h4 class="fw-bold mt-2">${nama}</h4><hr>
                <div class="text-start mb-3"><label class="label-minimal">Kitab</label><div class="fw-bold">${mapel}</div></div>
                <div class="row g-2 mb-3">
                    <div class="col-6"><div class="p-2 rounded bg-success text-white">Hadir<br><b>${hadir}</b></div></div>
                    <div class="col-6"><div class="p-2 rounded bg-warning text-dark">Izin<br><b>${izin}</b></div></div>
                </div>
                <div class="text-start">
                    <div class="d-flex justify-content-between small fw-bold mb-1"><span>Kehadiran</span><span>${persen}%</span></div>
                    <div class="progress" style="height:10px;"><div class="progress-bar bg-primary" style="width:${persen}%"></div></div>
                </div>`,
            showConfirmButton: false, showCloseButton: true
        });
    }
</script>
<?php if ($swal_msg): ?>
<script>Swal.fire({ icon: '<?= $swal_msg['icon'] ?>', title: '<?= $swal_msg['title'] ?>', text: '<?= $swal_msg['text'] ?>', timer: 1500, showConfirmButton: false });</script>
<?php endif; ?>
<?php if ($swal_msg): ?>
<script>
    Swal.fire({ 
        icon: '<?= $swal_msg['icon'] ?>', 
        title: '<?= $swal_msg['title'] ?>', 
        text: '<?= $swal_msg['text'] ?>',
        confirmButtonColor: '#4338ca'
    });
</script>
<?php endif; ?>
</body>
</html>