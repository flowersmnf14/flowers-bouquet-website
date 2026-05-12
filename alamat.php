<?php
session_start();
include 'config.php';

if (!isset($_SESSION['customer_logged_in']) || !$_SESSION['customer_logged_in']) {
    header('Location: login_customer.php');
    exit();
}

$customer_id   = $_SESSION['customer_id'];
$customer_name = $_SESSION['customer_name'];

/* ===============================
   AMBIL NOMOR HP CUSTOMER
================================ */
$customer_phone = '';
try {
    $p = $conn->prepare("SELECT phone FROM customers WHERE customer_id = ? LIMIT 1");
    $p->bind_param('i', $customer_id);
    $p->execute();
    $rp = $p->get_result();
    if ($rp && $rp->num_rows > 0) {
        $customer_phone = $rp->fetch_assoc()['phone'];
    }
    $p->close();
} catch (Exception $e) {
    $customer_phone = '';
}

$error   = '';
$success = '';

/* ===============================
   HANDLE POST ACTION
================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* ===== DELETE ===== */
    if (!empty($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        try {
            $stmt = $conn->prepare(
                "DELETE FROM alamat_user 
                 WHERE id_alamat = ? AND customer_id = ?"
            );
            $stmt->bind_param('ii', $id, $customer_id);
            $stmt->execute();
            $stmt->close();
            $success = 'Alamat berhasil dihapus.';
        } catch (Exception $e) {
            $error = 'Gagal menghapus alamat.';
        }
    }

    /* ===== SET DEFAULT ===== */
    if (!empty($_POST['action']) && $_POST['action'] === 'set_default' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        try {
            // reset default
            $stmt0 = $conn->prepare(
                "UPDATE alamat_user SET is_default = 0 WHERE customer_id = ?"
            );
            $stmt0->bind_param('i', $customer_id);
            $stmt0->execute();
            $stmt0->close();

            // set new default
            $stmt1 = $conn->prepare(
                "UPDATE alamat_user 
                 SET is_default = 1 
                 WHERE id_alamat = ? AND customer_id = ?"
            );
            $stmt1->bind_param('ii', $id, $customer_id);
            $stmt1->execute();
            $stmt1->close();

            $success = 'Alamat telah dijadikan default.';
        } catch (Exception $e) {
            $error = 'Gagal menjadikan default.';
        }
    }

    /* ===== ADD / UPDATE ===== */
    if (!empty($_POST['action']) && in_array($_POST['action'], ['add','update'])) {

        $nama_penerima = $customer_name;
        $nomor_hp      = $customer_phone;

        $alamat_text = trim($_POST['alamat'] ?? '');
        $kecamatan   = trim($_POST['kecamatan'] ?? '');
        $kota        = trim($_POST['kota'] ?? '');
        $provinsi    = trim($_POST['provinsi'] ?? '');
        $negara      = trim($_POST['negara'] ?? 'Indonesia');

        $id = (int)($_POST['id'] ?? 0);

        if ($_POST['action'] === 'add') {
            try {
                $stmt = $conn->prepare(
                    "INSERT INTO alamat_user
                    (customer_id, nama_penerima, nomor_hp, alamat, kecamatan, kota, provinsi, negara, is_default)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)"
                );
                $stmt->bind_param(
                    'isssssss',
                    $customer_id,
                    $nama_penerima,
                    $nomor_hp,
                    $alamat_text,
                    $kecamatan,
                    $kota,
                    $provinsi,
                    $negara
                );
                $stmt->execute();
                $stmt->close();
                $success = 'Alamat berhasil ditambahkan.';
            } catch (Exception $e) {
                $error = 'Gagal menambahkan alamat.';
            }
        } else {
            try {
                $stmtUpd = $conn->prepare(
                    "UPDATE alamat_user SET
                        nama_penerima = ?,
                        nomor_hp      = ?,
                        alamat        = ?,
                        kecamatan     = ?,
                        kota          = ?,
                        provinsi      = ?,
                        negara        = ?
                     WHERE id_alamat = ? AND customer_id = ?"
                );
                $stmtUpd->bind_param(
                    'sssssssii',
                    $nama_penerima,
                    $nomor_hp,
                    $alamat_text,
                    $kecamatan,
                    $kota,
                    $provinsi,
                    $negara,
                    $id,
                    $customer_id
                );
                $stmtUpd->execute();
                $stmtUpd->close();

                $success = 'Alamat berhasil diperbarui.';

                if (!empty($_POST['save_and_select'])) {
                    header('Location: checkout.php?alamat_id=' . $id);
                    exit();
                }
            } catch (Exception $e) {
                $error = 'Gagal memperbarui alamat.';
            }
        }
    }
}

/* ===============================
   AMBIL ALAMAT (PER AKUN)
================================ */
$addresses = [];
try {
    $stmt = $conn->prepare(
        "SELECT 
            id_alamat AS id,
            nama_penerima,
            nomor_hp,
            alamat,
            kecamatan,
            kota,
            provinsi,
            negara,
            is_default
         FROM alamat_user
         WHERE customer_id = ?
         ORDER BY is_default DESC, id_alamat DESC"
    );
    $stmt->bind_param('i', $customer_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $addresses[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {}

/* ===============================
   EDIT MODE
================================ */
$editing = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    try {
        $s = $conn->prepare(
            "SELECT 
                id_alamat AS id,
                nama_penerima,
                nomor_hp,
                alamat,
                kecamatan,
                kota,
                provinsi,
                negara,
                is_default
             FROM alamat_user
             WHERE id_alamat = ? AND customer_id = ?
             LIMIT 1"
        );
        $s->bind_param('ii', $edit_id, $customer_id);
        $s->execute();
        $r = $s->get_result();
        if ($r && $r->num_rows > 0) {
            $editing = $r->fetch_assoc();
        }
        $s->close();
    } catch (Exception $e) {}
}

/* ===============================
   RETURN PARAM
================================ */
$return_q = isset($_GET['return']) ? '&return=' . urlencode($_GET['return']) : '';

?>


<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Daftar Alamat - <?php echo htmlspecialchars($customer_name); ?></title>
    <style>
    body { font-family: Arial, sans-serif; padding: 1rem; }
    .addr { border:1px solid #eee; padding:0.75rem; margin-bottom:0.75rem; border-radius:6px }
    .btn { display:inline-block; padding:0.4rem 0.6rem; margin-right:0.4rem; background:#FF1493; color:#fff; text-decoration:none; border-radius:4px }
    .btn.secondary { background:#777 }






/* BODY GRADIENT */
body {
    font-family: Arial, sans-serif;
    padding: 1rem;
    background: linear-gradient(135deg, #FFB6D9 0%, #FFF176 100%);
    color: #333;
}

/* FORM STYLE */
form label {
    display: block;
    margin-top: 0.8rem;
    font-weight: 600;
}
form input, form textarea {
    width: 100%;
    padding: 0.5rem;
    margin-top: 0.2rem;
    border: 1px solid #ccc;
    border-radius: 6px;
    font-size: 1rem;
    box-sizing: border-box;
}
form textarea {
    resize: vertical;
}

/* TOMBOL */
button, .btn {
    cursor: pointer;
    transition: all 0.2s ease-in-out;
}
button:hover, .btn:hover {
    opacity: 0.9;
}

/* BUTTON COLORS */
button.submit-btn, .btn.primary {
    background: linear-gradient(135deg, #FF1493 0%, #FFD700 100%);
    color: #fff;
    border: none;
}
button.submit-btn:hover, .btn.primary:hover {
    filter: brightness(1.1);
}
.btn.secondary {
    background: #777;
    color: #fff;
}
.btn.secondary:hover {
    background: #555;
}

/* ALAMAT LIST */
.addr {
    border:1px solid #eee; 
    padding:0.75rem; 
    margin-bottom:0.75rem; 
    border-radius:6px; 
    transition: all 0.2s;
    background: rgba(255,255,255,0.7);
}
.addr:hover {
    box-shadow: 0 2px 6px rgba(0,0,0,0.15);
}

/* FORM SECTION */
.form-group {
    margin-bottom: 1rem;
}

/* LABEL OPTION */
.label-options {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}
.label-option {
    border: 1px solid #ccc;
    padding: 0.5rem 0.8rem;
    border-radius: 6px;
    cursor: pointer;
    user-select: none;
    transition: all 0.2s;
    background: rgba(255,255,255,0.6);
}
.label-option.selected {
    background: linear-gradient(135deg, #FF1493 0%, #FFD700 100%);
    color: #fff;
    border-color: #FF1493;
}
.label-option:hover {
    background: rgba(255,182,217,0.7);
}

/* RINGKASAN PESANAN */
.order-summary {
    border: 1px solid #eee;
    padding: 1rem;
    border-radius: 6px;
    margin-top: 1rem;
    background: rgba(255,255,255,0.7);
}
.order-summary h3 {
    margin-top: 0;
    margin-bottom: 0.5rem;
}
.order-summary p {
    margin: 0.25rem 0;
}

/* ESTIMATE BANNER */
#estimateBanner {
    background: linear-gradient(135deg, #FFB6D9 0%, #FFF176 100%);
    padding: 1rem;
    border-radius: 6px;
    margin-top: 1rem;
    text-align: center;
    color: #333;
    font-weight: 600;
}




/* FORM TAMBAH ALAMAT */
form[action][method], form[action] {
    background: linear-gradient(135deg, #FFF176 0%, #FFF59D 100%);
    padding: 1rem 1.2rem;
    border-radius: 10px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    margin-bottom: 1.5rem;
    transition: all 0.3s ease;
}
form[action][method]:hover, form[action]:hover {
    box-shadow: 0 6px 16px rgba(0,0,0,0.25);
}

/* LABEL DAN INPUT */
form label {
    display: block;
    margin-top: 0.8rem;
    font-weight: 600;
    color: #333;
}
form input, form textarea {
    width: 100%;
    padding: 0.6rem;
    margin-top: 0.2rem;
    border: 1px solid #f1c40f;
    border-radius: 6px;
    font-size: 1rem;
    box-sizing: border-box;
    transition: all 0.2s;
}
form input:focus, form textarea:focus {
    border-color: #FF1493;
    outline: none;
    box-shadow: 0 0 6px rgba(255,20,147,0.3);
}

/* TOMBOL TAMBAH */
form button {
    background: linear-gradient(135deg, #FF1493 0%, #FFD700 100%);
    color: #fff;
    border: none;
    padding: 0.5rem 0.9rem;
    margin-top: 0.8rem;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.2s ease-in-out;
}
form button:hover {
    filter: brightness(1.1);
}






/* Tombol Kembali ke Checkout */
.btn {
    background: linear-gradient(90deg, #ff1493, #ffd700);
    color: #fff;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    transition: 0.3s;
}

.btn:hover {
    opacity: 0.9;
}

/* Tombol utama (Tambah Alamat, Kembali ke Checkout, Konfirmasi Pesanan) */
.btn, button.submit-btn {
    background: linear-gradient(90deg, #ff1493, #ffd700); /* Gradasi pink → kuning */
    color: #fff;
    border: none;
    padding: 0.6rem 1.2rem;       /* Samakan ukuran */
    border-radius: 6px;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    font-size: 1rem;              /* Ukuran font seragam */
    transition: 0.3s;
    min-width: 140px;             /* Pastikan tombol tidak terlalu kecil */
    text-align: center;
}

/* Hover efek untuk tombol utama */
.btn:hover, button.submit-btn:hover {
    opacity: 0.9;
}

/* Tombol edit, batal, hapus tetap pakai warnanya sendiri, tapi ukuran disamakan */
.btn.secondary, form button {
    padding: 0.6rem 1.2rem;
    border-radius: 6px;
    font-size: 1rem;
    min-width: 140px;
}
/* CSS untuk tombol */
.addr .btn,
.addr button {
    font-size: 14px;          /* ukuran font sama */
    padding: 0.5rem 0.8rem;   /* ukuran kotak sama */
    border-radius: 4px;       /* sudut sama */
    border: none;             /* hilangkan border default */
    cursor: pointer;
    display: inline-block;
    text-align: center;
    margin-right: 0.4rem;     /* jarak antar tombol */
}

/* Warna tombol */
.addr .btn { background: #ff1493, #ffd700; color: #fff; }
.addr .btn.secondary { background: #6c757d; color: #fff; }
.addr button { background: #28a745; color: #fff; } /* default */
.addr button.delete { background: #c00; color: #fff; }

/* Hover effect */
.addr .btn:hover,
.addr button:hover {
    opacity: 0.9;
}


    </style>
</head>
<body>
    <h1>Daftar Alamat</h1>
    <?php if ($error): ?><div style="color:#900"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <?php if ($success): ?><div style="color:green"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

    <p><a class="btn" href="checkout.php">Kembali ke Checkout</a></p>

    <?php if ($editing): ?>
        
        <h2>Edit Alamat</h2>
        <form method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?php echo (int)$editing['id']; ?>">
            <!-- Nama penerima & Nomor HP removed from edit form; account defaults are used -->
            <label>Alamat detail<br><textarea name="alamat" required><?php echo htmlspecialchars($editing['alamat']); ?></textarea></label><br>
            <label>Kecamatan<br><input name="kecamatan" value="<?php echo htmlspecialchars($editing['kecamatan']); ?>"></label><br>
            <label>Kota/Kabupaten<br><input name="kota" value="<?php echo htmlspecialchars($editing['kota']); ?>"></label><br>
            <label>Provinsi<br><input name="provinsi" value="<?php echo htmlspecialchars($editing['provinsi']); ?>"></label><br>
            <label>Negara<br><input name="negara" value="<?php echo htmlspecialchars($editing['negara']); ?>"></label><br>
            <button type="submit">Simpan Perubahan</button>
            <button type="submit" name="save_and_select" value="1" style="margin-left:0.5rem;background:#28a745;color:#fff;border:none;padding:0.45rem 0.7rem;border-radius:4px;">Simpan & Pilih untuk Checkout</button>
            <a class="btn secondary" href="alamat.php">Batal</a>
        </form>
    <?php endif; ?>

   <h2>Tambah Alamat Baru</h2>
<form method="POST" <?php if ($editing) echo 'style="display:none;"'; ?>>
    <input type="hidden" name="action" value="add">
    <label>Alamat lengkap<br><textarea name="alamat" placeholder="Jalan, nomor, patokan, rt/rw" required><?php echo isset($_POST['alamat']) ? htmlspecialchars($_POST['alamat']) : ''; ?></textarea></label><br>
    <label>Kecamatan<br><input name="kecamatan" value="<?php echo isset($_POST['kecamatan']) ? htmlspecialchars($_POST['kecamatan']) : ''; ?>"></label><br>
    <label>Kota/Kabupaten<br><input name="kota" value="<?php echo isset($_POST['kota']) ? htmlspecialchars($_POST['kota']) : ''; ?>"></label><br>
    <label>Provinsi<br><input name="provinsi" value="<?php echo isset($_POST['provinsi']) ? htmlspecialchars($_POST['provinsi']) : ''; ?>"></label><br>
    <label>Negara<br><input name="negara" value="<?php echo isset($_POST['negara']) ? htmlspecialchars($_POST['negara']) : 'Indonesia'; ?>"></label><br>
    <button type="submit">Tambah Alamat</button>
</form>

    <h2>Alamat yang Tersimpan</h2>
    <?php foreach ($addresses as $a): ?>
    <div class="addr">
        <div style="font-weight:700">Alamat <?php echo $a['is_default'] ? ' <small style="color:#0a0">(Default)</small>' : ''; ?></div>
        <div style="white-space:pre-wrap;margin-top:0.4rem;color:#333"><?php echo htmlspecialchars($a['alamat']); ?></div>
        <div style="margin-top:0.25rem;color:#333"><?php echo htmlspecialchars(implode(', ', array_filter([$a['kecamatan'], $a['kota'], $a['provinsi'], $a['negara']]))); ?></div>
        <div style="margin-top:0.5rem">
            <?php if ($a['is_default']): ?>
                <a class="btn" href="checkout.php?alamat_id=<?php echo (int)$a['id']; ?>">Pilih</a>
            <?php else: ?>
                <form method="POST" style="display:inline">
                    <input type="hidden" name="action" value="set_default">
                    <input type="hidden" name="id" value="<?php echo (int)$a['id']; ?>">
                    <button style="background:#28a745;color:#fff;border:none;padding:0.4rem 0.6rem;border-radius:4px;" type="submit">Jadikan Default</button>
                </form>
            <?php endif; ?>
            
            <a class="btn secondary" href="alamat.php?edit=<?php echo (int)$a['id'] . $return_q; ?>">Edit</a>

            <form method="POST" style="display:inline" onsubmit="return confirm('Hapus alamat ini?');">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?php echo (int)$a['id']; ?>">
                <button style="background:#c00;color:#fff;border:none;padding:0.4rem 0.6rem;border-radius:4px;" type="submit">Hapus</button>
            </form>
        </div>
    </div>
<?php endforeach; ?>

    <script>
// Interaksi klik label-option agar terlihat selected
document.querySelectorAll('.label-options').forEach(container=>{
    container.querySelectorAll('.label-option').forEach(option=>{
        option.addEventListener('click', ()=>{
            container.querySelectorAll('.label-option').forEach(o=>o.classList.remove('selected'));
            option.classList.add('selected');
            const input = option.querySelector('input');
            if(input) input.checked = true;
        });
    });
});

// Fokus & enter key
document.querySelectorAll('.label-option').forEach(opt=>{
    opt.addEventListener('keypress', e=>{
        if(e.key==='Enter' || e.key===' ') opt.click();
    });
});

// Optional: highlight default address
document.querySelectorAll('.addr').forEach(addr=>{
    if(addr.querySelector('small') && addr.querySelector('small').textContent.includes('Default')){
        addr.style.borderColor = '#28a745';
        addr.style.background = '#f0fff0';
    }
});
</script>

</body>
</html>
