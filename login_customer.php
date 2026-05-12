<?php
session_start();
include 'config.php';

// Set error reporting
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$login_error = '';
$success_message = isset($_GET['registered']) ? 'Registrasi berhasil! Silakan login.' : '';

// Jika sudah login, langsung redirect
if (isset($_SESSION['customer_logged_in']) && $_SESSION['customer_logged_in'] === true) {
    header("Location: index_customer.php");
    exit();
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'], $_POST['password'])) {

    $email = trim($_POST['email']);
    $password = $_POST['password'];

    /*
      STRUKTUR customers (DB kamu):
      - customer_id
      - name
      - email
      - password
      - status
    */

    $stmt = $conn->prepare("
        SELECT customer_id, name, password, status
        FROM customers
        WHERE email = ?
        LIMIT 1
    ");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {

        if (!password_verify($password, $row['password'])) {
            $login_error = "Kredensial tidak valid. Silakan coba lagi.";

        } elseif (strtolower($row['status']) !== 'aktif') {
            $login_error = "Akun Anda diblokir. Silakan hubungi administrator.";

        } else {
            // LOGIN BERHASIL
            $_SESSION['customer_logged_in'] = true;
            $_SESSION['customer_id'] = $row['customer_id'];
            $_SESSION['customer_name'] = $row['name'];

            header("Location: index_customer.php");
            exit();
        }

    } else {
        $login_error = "Akun belum terdaftar. Silakan registrasi terlebih dahulu.";
    }

    $stmt->close();
}

// Pesan jika akun diblokir via redirect
if (isset($_GET['blocked'])) {
    $login_error = 'Akun Anda saat ini diblokir. Hubungi admin untuk bantuan.';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Pelanggan - Flowers Bouquet</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #FFC0CB 0%, #FFB6D9 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 1rem;
        }
        .login-container {
            background: white;
            padding: 3rem;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 400px;
        }
        .login-header { text-align: center; margin-bottom: 2rem; }
        .login-header h1 { color: #FF1493; font-size: 2.5rem; }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; margin-bottom: .5rem; font-weight: bold; }
        .form-group input {
            width: 100%;
            padding: .9rem;
            border: 2px solid #FFB6D9;
            border-radius: 8px;
        }
        .login-btn {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #FFC0CB 0%, #FF69B4 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
        }
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        .back-link { text-align: center; margin-top: 1.5rem; }
        .back-link a { color: #FF69B4; text-decoration: none; font-weight: bold; }
    </style>
</head>
<body>

<div class="login-container">
    <div class="login-header">
        <h1>🌹 Flowers Bouquet</h1>
        <p>Login Pelanggan</p>
    </div>

    <?php if ($success_message): ?>
        <div class="success-message"><?= htmlspecialchars($success_message) ?></div>
    <?php endif; ?>

    <?php if ($login_error): ?>
        <div class="error-message"><?= htmlspecialchars($login_error) ?></div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" required>
        </div>

        <div class="form-group">
            <label>Kata Sandi</label>
            <input type="password" name="password" required>
        </div>

        <button type="submit" class="login-btn">Login</button>
    </form>

    <div class="back-link">
        <a href="register.php">Belum punya akun? Registrasi</a>
        <br><br>
        <a href="berandaAdmin.php">← Kembali ke Beranda</a>
    </div>
</div>

</body>
</html>
