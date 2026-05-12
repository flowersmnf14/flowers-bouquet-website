<?php


session_start();
include 'config.php';

// Set error reporting
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$login_error = '';

// Handle admin login here: accept only username 'Flowers' with password 'Bunga14@'
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['username']) && isset($_POST['password'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    if ($username === 'Flowers' && $password === 'Bunga14@') {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;
        header("Location: berandaAdmin.php");
        exit();
    } else {
        $login_error = 'Anda bukan admin.';
    }
}

// If already logged in as admin, redirect to admin page
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in']) {
    header("Location: berandaAdmin.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Pelanggan - Flowers Bouquet</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
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
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 400px;
            animation: slideIn 0.5s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-header h1 {
            color: #FF1493;
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }

        .login-header p {
            color: #666;
            font-size: 1rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: bold;
            color: #333;
            font-size: 1rem;
        }

        .form-group input {
            width: 100%;
            padding: 0.9rem;
            border: 2px solid #FFB6D9;
            border-radius: 8px;
            font-size: 1rem;
            font-family: 'Arial', sans-serif;
            transition: all 0.3s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: #FF1493;
            box-shadow: 0 0 10px rgba(255, 20, 147, 0.3);
            background-color: #FFF0F5;
        }

        .form-group input::placeholder {
            color: #999;
        }

        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            border: 1px solid #c3e6cb;
        }

        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            border: 1px solid #f5c6cb;
            animation: shake 0.5s ease;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
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
            transition: all 0.3s ease;
            box-shadow: 0 4px 8px rgba(255, 105, 180, 0.2);
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(255, 105, 180, 0.3);
        }

        .login-btn:active {
            transform: translateY(0);
        }

        .back-link {
            text-align: center;
            margin-top: 1.5rem;
        }

        .back-link a {
            color: #FF69B4;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s ease;
        }

        .back-link a:hover {
            color: #FF1493;
            text-decoration: underline;
        }

        @media (max-width: 480px) {
            .login-container {
                padding: 2rem 1.5rem;
            }

            .login-header h1 {
                font-size: 2rem;
            }

            .form-group input {
                padding: 0.8rem;
                font-size: 1rem;
            }

            .login-btn {
                padding: 0.9rem;
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>🌹</h1>
            <h1>Flowers Bouquet</h1>
            <p>Login Admin</p>
        </div>

        <?php if ($login_error): ?>
        <div class="error-message">
            <strong>Error!</strong> <?php echo htmlspecialchars($login_error); ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="" autocomplete="off" id="adminForm">
            <div class="form-group">
                <label for="username">Nama Pengguna:</label>
                <input type="text" id="username" name="username" placeholder="Masukkan nama pengguna" autocomplete="off" required autofocus>
            </div>

            <div class="form-group">
                <label for="password">Kata Sandi:</label>
                <input type="password" id="password" name="password" placeholder="Masukkan kata sandi" autocomplete="off" required>
            </div>

            <button type="submit" class="login-btn">Login</button>
        </form>

        <div class="back-link">
            <a href="berandaAdmin.php">← Kembali ke Beranda</a>
        </div>
    </div>

    <script>
        // Clear login fields immediately on page load
        function clearLoginFields() {
            const u = document.getElementById('username');
            const p = document.getElementById('password');
            if (u) u.value = '';
            if (p) p.value = '';
        }

        // Clear fields on page load
        window.addEventListener('load', function() { clearLoginFields(); });

        // Clear fields on page show (back button)
        window.addEventListener('pageshow', function(event) { if (event.persisted) { clearLoginFields(); } });

        // Clear fields on visibility change (tab switching)
        document.addEventListener('visibilitychange', function() { if (!document.hidden) { setTimeout(clearLoginFields, 100); } });

        // Prevent auto-fill by clearing on focus
        document.getElementById('username').addEventListener('focus', function() { setTimeout(() => { if (this.value !== '') this.value = ''; }, 50); });
        document.getElementById('password').addEventListener('focus', function() { setTimeout(() => { if (this.value !== '') this.value = ''; }, 50); });

        // Prevent auto-fill by clearing on input (placeholders for logic)
        document.getElementById('username').addEventListener('input', function() {});
        document.getElementById('password').addEventListener('input', function() {});

        // Clear fields before page unload
        window.addEventListener('beforeunload', function() { clearLoginFields(); });

        // Aggressive auto-fill prevention
        setTimeout(clearLoginFields, 100);
        setTimeout(clearLoginFields, 300);
        setTimeout(clearLoginFields, 500);

        // Form validation
        document.getElementById('adminForm').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value.trim();

            if (username === '') { e.preventDefault(); alert('❌ Nama pengguna tidak boleh kosong!'); document.getElementById('username').focus(); return false; }
            if (password === '') { e.preventDefault(); alert('❌ Kata sandi tidak boleh kosong!'); document.getElementById('password').focus(); return false; }
            if (username.length < 3) { e.preventDefault(); alert('❌ Nama pengguna minimal 3 karakter!'); document.getElementById('username').focus(); return false; }
            if (password.length < 3) { e.preventDefault(); alert('❌ Kata sandi minimal 3 karakter!'); document.getElementById('password').focus(); return false; }
        });
    </script>
</body>
</html>
