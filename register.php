<?php
session_start();
include 'config.php';

// Aktifkan error reporting MySQLi
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$register_error = null;

// Handle registration
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['name'], $_POST['email'], $_POST['password'], $_POST['phone'])
) {
    $name     = trim($_POST['name']);
    $email    = trim($_POST['email']);
    $password = $_POST['password'];
    $phone    = trim($_POST['phone']);

    // Validasi dasar
    if (empty($name) || empty($email) || empty($password)) {
        $register_error = "Semua field harus diisi.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $register_error = "Format email tidak valid.";
    } elseif (strlen($password) < 8) {
        $register_error = "Kata sandi minimal 8 karakter.";
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $register_error = "Kata sandi harus mengandung huruf besar.";
    } elseif (!preg_match('/[a-z]/', $password)) {
        $register_error = "Kata sandi harus mengandung huruf kecil.";
    } elseif (!preg_match('/[0-9]/', $password)) {
        $register_error = "Kata sandi harus mengandung angka.";
    } elseif (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $password)) {
        $register_error = "Kata sandi harus mengandung simbol.";
    }

    // Validasi nomor telepon
    if (!$register_error) {
        $digitsOnly = preg_replace('/[^0-9]/', '', $phone);
        if (empty($phone) || strlen($digitsOnly) < 6 || strlen($digitsOnly) > 20) {
            $register_error = "Nomor telepon tidak valid. Masukkan nomor 6–20 digit.";
        }
    }

    if (!$register_error) {
        // CEK EMAIL (FIX UTAMA DI SINI)
        $stmt = $conn->prepare("SELECT customer_id FROM customers WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $register_error = "Email sudah terdaftar. Silakan gunakan email lain.";
            $stmt->close();
        } else {
            $stmt->close();

            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Insert data customer
            $stmt = $conn->prepare(
                "INSERT INTO customers (name, email, `password`, phone) VALUES (?, ?, ?, ?)"
            );
            $stmt->bind_param("ssss", $name, $email, $hashed_password, $phone);

            if ($stmt->execute()) {
                header("Location: login_customer.php?registered=1");
                exit();
            } else {
                $register_error = "Terjadi kesalahan saat registrasi. Silakan coba lagi.";
            }
            $stmt->close();
        }
    }
}

// Jika sudah login, redirect
if (!empty($_SESSION['customer_logged_in'])) {
    header("Location: index_customer.php");
    exit();
}
?>


<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrasi Pelanggan - Flowers Bouquet</title>
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

        .register-container {
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

        .register-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .register-header h1 {
            color: #FF1493;
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }

        .register-header p {
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

        .register-btn {
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

        .register-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(255, 105, 180, 0.3);
        }

        .register-btn:active {
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
            .register-container {
                padding: 2rem 1.5rem;
            }

            .register-header h1 {
                font-size: 2rem;
            }

            .form-group input {
                padding: 0.8rem;
                font-size: 1rem;
            }

            .register-btn {
                padding: 0.9rem;
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <h1>🌹</h1>
            <h1>Flowers Bouquet</h1>
            <p>Registrasi Pelanggan</p>
        </div>

        <?php if ($register_error): ?>
        <div class="error-message">
            <strong>Error!</strong> <?php echo htmlspecialchars($register_error); ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="" autocomplete="off">
            <div class="form-group">
                <label for="name">Nama Lengkap:</label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    placeholder="Masukkan nama lengkap"
                    autocomplete="off"
                    required
                    autofocus>
            </div>

            <div class="form-group">
                <label for="email">Email:</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="Masukkan email"
                    autocomplete="off"
                    required>
            </div>

            <div class="form-group">
                <label for="phone">Nomor Telepon:</label>
                <input
                    type="tel"
                    id="phone"
                    name="phone"
                    placeholder="Contoh: 08123456789"
                    autocomplete="tel"
                    required>
            </div>

            <div class="form-group">
                <label for="password">Kata Sandi:</label>
              
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Masukkan kata sandi"
                    autocomplete="off"
                    required>
                                            <div class="password-requirements" style="font-size: 0.9rem; color: #666; margin-bottom: 0.5rem;">
                                        <strong>Ketentuan Kata Sandi :</strong><br>
                                        - Minimal 8 karakter.<br>
                                        - Kombinasi huruf besar & kecil.<br>
                                        - Tambahkan angka.<br>
                                        - Tambahkan simbol (misal: @ # $ % !).<br>
                                </div>
            </div>

            <button type="submit" class="register-btn">Registrasi</button>
        </form>

        <div class="back-link">
            <a href="login_customer.php">Sudah punya akun? Login di sini</a><br> <br>
            <a href="berandaAdmin.php">← Kembali ke Beranda</a>
        </div>
    </div>

    <script>
        // Clear registration fields immediately on page load
        function clearRegisterFields() {
            document.getElementById('name').value = '';
            document.getElementById('email').value = '';
            document.getElementById('password').value = '';
            var ph = document.getElementById('phone'); if (ph) ph.value = '';
        }

        // Clear fields on page load
        window.addEventListener('load', function() {
            clearRegisterFields();
        });

        // Clear fields on page show (back button)
        window.addEventListener('pageshow', function(event) {
            if (event.persisted) {
                clearRegisterFields();
            }
        });

        // Clear fields on visibility change (tab switching)
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) {
                setTimeout(clearRegisterFields, 100);
            }
        });

        // Prevent auto-fill by clearing on focus
        document.getElementById('name').addEventListener('focus', function() {
            setTimeout(() => {
                if (this.value !== '') {
                    this.value = '';
                }
            }, 50);
        });

        document.getElementById('email').addEventListener('focus', function() {
            setTimeout(() => {
                if (this.value !== '') {
                    this.value = '';
                }
            }, 50);
        });

        document.getElementById('phone').addEventListener('focus', function() {
            setTimeout(() => {
                if (this.value !== '') {
                    this.value = '';
                }
            }, 50);
        });

        document.getElementById('password').addEventListener('focus', function() {
            setTimeout(() => {
                if (this.value !== '') {
                    this.value = '';
                }
            }, 50);
        });

        // Prevent auto-fill by clearing on input
        document.getElementById('name').addEventListener('input', function() {
            // Allow user to type but clear auto-filled values
        });

        document.getElementById('email').addEventListener('input', function() {
            // Allow user to type but clear auto-filled values
        });

        document.getElementById('password').addEventListener('input', function() {
            // Allow user to type but clear auto-filled values
        });

        document.getElementById('phone').addEventListener('input', function() {
            // Allow user to type but clear auto-filled values
        });

        // Clear fields before page unload
        window.addEventListener('beforeunload', function() {
            clearRegisterFields();
        });

        // Aggressive auto-fill prevention
        setTimeout(function() {
            clearRegisterFields();
        }, 100);

        setTimeout(function() {
            clearRegisterFields();
        }, 300);

        setTimeout(function() {
            clearRegisterFields();
        }, 500);

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const name = document.getElementById('name').value.trim();
            const email = document.getElementById('email').value.trim();
            const phone = (document.getElementById('phone') ? document.getElementById('phone').value.trim() : '');
            const password = document.getElementById('password').value.trim();

            if (name === '') {
                e.preventDefault();
                alert('❌ Nama tidak boleh kosong!');
                document.getElementById('name').focus();
                return false;
            }

            if (email === '') {
                e.preventDefault();
                alert('❌ Email tidak boleh kosong!');
                document.getElementById('email').focus();
                return false;
            }

            if (phone === '') {
                e.preventDefault();
                alert('❌ Nomor telepon tidak boleh kosong!');
                document.getElementById('phone').focus();
                return false;
            }

            if (password === '') {
                e.preventDefault();
                alert('❌ Kata sandi tidak boleh kosong!');
                document.getElementById('password').focus();
                return false;
            }

            if (name.length < 2) {
                e.preventDefault();
                alert('❌ Nama minimal 2 karakter!');
                document.getElementById('name').focus();
                return false;
            }

            if (password.length < 8) {
                e.preventDefault();
                alert('❌ Kata sandi minimal 8 karakter!');
                document.getElementById('password').focus();
                return false;
            }

            if (!/[A-Z]/.test(password)) {
                e.preventDefault();
                alert('❌ Kata sandi harus mengandung huruf besar!');
                document.getElementById('password').focus();
                return false;
            }

            if (!/[a-z]/.test(password)) {
                e.preventDefault();
                alert('❌ Kata sandi harus mengandung huruf kecil!');
                document.getElementById('password').focus();
                return false;
            }

            if (!/[0-9]/.test(password)) {
                e.preventDefault();
                alert('❌ Kata sandi harus mengandung angka!');
                document.getElementById('password').focus();
                return false;
            }

            if (!/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/.test(password)) {
                e.preventDefault();
                alert('❌ Kata sandi harus mengandung simbol!');
                document.getElementById('password').focus();
                return false;
            }

            // No blacklist of common passwords per new policy — only enforce the required composition rules above

            // Basic email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                e.preventDefault();
                alert('❌ Format email tidak valid!');
                document.getElementById('email').focus();
                return false;
            }

            // Phone basic validation: digits length 6-20
            const digits = phone.replace(/[^0-9]/g, '');
            if (digits.length < 6 || digits.length > 20) {
                e.preventDefault();
                alert('❌ Nomor telepon tidak valid. Masukkan 6-20 digit.');
                document.getElementById('phone').focus();
                return false;
            }
        });
    </script>
</body>
</html>
