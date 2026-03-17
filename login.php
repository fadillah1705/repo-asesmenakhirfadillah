<?php 
include 'koneksi.php'; 

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['id'])) {
    header("Location: " . ($_SESSION['role'] == 'pembina' ? 'dashboard.php' : 'dashboard_guru.php'));
    exit;
}

$swal_msg = null;

if (isset($_POST['login'])) {
    $u = mysqli_real_escape_string($conn, $_POST['username']);
    $p = md5($_POST['password']); 

    $query = "SELECT * FROM users WHERE username='$u' AND password='$p'";
    $res = mysqli_query($conn, $query);
    
    if (mysqli_num_rows($res) > 0) {
        $user = mysqli_fetch_assoc($res);
        $_SESSION['id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['nama'] = $user['nama_lengkap'];
        $_SESSION['role'] = $user['role']; 

        $swal_msg = [
            'icon' => 'success',
            'title' => 'Berhasil Masuk',
            'text' => 'Selamat datang di Panel E-Kajian',
            'redirect' => ($user['role'] == 'pembina' ? 'dashboard.php' : 'dashboard_guru.php')
        ];
    } else {
        $swal_msg = [
            'icon' => 'error',
            'title' => 'Akses Ditolak',
            'text' => 'Kombinasi user & password tidak ditemukan.'
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Panel | E-Kajian</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #f8fafc;
            background-image: radial-gradient(#065f4615 1px, transparent 1px);
            background-size: 30px 30px;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .auth-container {
            width: 100%;
            max-width: 450px;
            padding: 20px;
        }

        .auth-card {
            background: #ffffff;
            border-radius: 24px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.05);
            padding: 40px;
            text-align: center;
        }

        .brand-logo {
            width: 64px;
            height: 64px;
            background: #065f46;
            color: white;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin: 0 auto 20px;
            box-shadow: 0 10px 15px -3px rgba(6, 95, 70, 0.3);
        }

        .auth-header h1 {
            font-size: 24px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }

        .auth-header p {
            color: #64748b;
            font-size: 14px;
            margin-bottom: 32px;
        }

        .form-group {
            text-align: left;
            margin-bottom: 20px;
        }

        .form-label {
            font-weight: 600;
            font-size: 13px;
            color: #334155;
            margin-left: 4px;
        }

        .input-custom-group {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-custom-group i {
            position: absolute;
            left: 16px;
            color: #94a3b8;
            font-size: 18px;
        }

        .form-control-custom {
            width: 100%;
            padding: 14px 16px 14px 48px;
            background: #f1f5f9;
            border: 2px solid transparent;
            border-radius: 12px;
            font-weight: 500;
            font-size: 15px;
            transition: all 0.2s ease;
        }

        .form-control-custom:focus {
            outline: none;
            background: #fff;
            border-color: #065f46;
            box-shadow: 0 0 0 4px rgba(6, 95, 70, 0.1);
        }

        .btn-submit {
            width: 100%;
            padding: 14px;
            background: #065f46;
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 700;
            font-size: 16px;
            margin-top: 10px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-submit:hover {
            background: #047857;
            transform: translateY(-1px);
            box-shadow: 0 8px 15px -3px rgba(6, 95, 70, 0.3);
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            margin-top: 24px;
            color: #64748b;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: color 0.2s;
        }

        .back-link:hover {
            color: #065f46;
        }

        /* SweetAlert Custom Style */
        .swal2-popup {
            border-radius: 20px !important;
            font-family: 'Plus Jakarta Sans', sans-serif !important;
        }
    </style>
</head>
<body>

<div class="auth-container">
    <div class="auth-card">
        <div class="brand-logo">
            <i class="fas fa-mosque"></i>
        </div>
        <div class="auth-header">
            <h1>Selamat Datang</h1>
            <p>Silakan masuk ke Panel Manajemen E-Kajian</p>
        </div>

        <form method="POST">
            <div class="form-group">
                <label class="form-label">Username</label>
                <div class="input-custom-group">
                    <i class="far fa-user"></i>
                    <input type="text" name="username" class="form-control-custom" placeholder="Masukkan username" required autocomplete="off">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Password</label>
                <div class="input-custom-group">
                    <i class="far fa-eye-slash" id="togglePassword" style="cursor: pointer; pointer-events: auto;"></i>
                    <input type="password" name="password" id="password" class="form-control-custom" placeholder="••••••••" required>
                </div>
            </div>

            <button type="submit" name="login" class="btn-submit">
                Masuk ke Panel
            </button>
        </form>

        <a href="index.php" class="back-link">
            <i class="fas fa-arrow-left me-2"></i> Kembali ke Beranda
        </a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    // Fitur Show/Hide Password
    const togglePassword = document.querySelector('#togglePassword');
    const password = document.querySelector('#password');

    togglePassword.addEventListener('click', function (e) {
        const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
        password.setAttribute('type', type);
        this.classList.toggle('fa-eye');
        this.classList.toggle('fa-eye-slash');
    });

    // Handle SweetAlert
    <?php if ($swal_msg): ?>
    Swal.fire({
        icon: '<?= $swal_msg['icon'] ?>',
        title: '<?= $swal_msg['title'] ?>',
        text: '<?= $swal_msg['text'] ?>',
        showConfirmButton: <?= $swal_msg['icon'] == 'success' ? 'false' : 'true' ?>,
        timer: <?= $swal_msg['icon'] == 'success' ? '1800' : 'null' ?>,
        timerProgressBar: true
    }).then(() => {
        <?php if (isset($swal_msg['redirect'])): ?>
            window.location.href = '<?= $swal_msg['redirect'] ?>';
        <?php endif; ?>
    });
    <?php endif; ?>
</script>

</body>
</html>