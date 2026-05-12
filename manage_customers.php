<?php
session_start();
include 'config.php';

if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: login.php');
    exit();
}

// HANDLE AKSI
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['customer_id'])) {
    $action = $_POST['action'];
    $customer_id = (int) $_POST['customer_id'];

    if ($customer_id > 0) {
        if ($action === 'set_aktif') {
            $conn->query("UPDATE customers SET status='aktif' WHERE customer_id=$customer_id");
            $_SESSION['manage_customers_msg'] = "Akun berhasil diaktifkan.";
        } elseif ($action === 'set_nonaktif') {
            $conn->query("UPDATE customers SET status='nonaktif' WHERE customer_id=$customer_id");
            $_SESSION['manage_customers_msg'] = "Akun berhasil dinonaktifkan.";
        } elseif ($action === 'set_hapus') {
            $conn->query("UPDATE customers SET status='hapus' WHERE customer_id=$customer_id");
            $_SESSION['manage_customers_msg'] = "Akun berhasil dikunci.";
        }
    }
    header("Location: manage_customers.php");
    exit();
}

// DATA CUSTOMER
$customers = [];
$res = $conn->query("SELECT customer_id,name,email,phone,status,created_at FROM customers ORDER BY created_at DESC");
while ($res && $row = $res->fetch_assoc()) $customers[] = $row;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Kelola Akun Pelanggan</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<style>
*{box-sizing:border-box}
body{
    margin:0;
    font-family:Arial, sans-serif;
    background: #ffffff;
}

/* ===== NAVBAR =====
.navbar{
    background:linear-gradient(135deg, #FF1493, #FFD700);
    padding:1.2rem 1.8rem;
    display:flex;
    justify-content:space-between;
    align-items:center;
    box-shadow:0 4px 18px rgba(0,0,0,.18);
} */

.navbar-title{
    font-size: 1.8rem;              /* ⬅ diperbesar */
    font-weight:800;
    color: #ffffffff;                 /* ⬅ jadi kuning */
    letter-spacing:.5px;
}

.btn-back{
    background: #FF1493;            /* ⬅ kuning */
    color: #ffffffff;
    padding:.55rem 1.4rem;
    border-radius:999px;
    text-decoration:none;
    font-weight:700;
    transition:.2s;
}
.btn-back:hover{
    background:#fff1a8;
}

/* ===== CONTAINER ===== */
.container{
    max-width:1200px;
    margin:auto;
    padding:2rem 1.5rem;
}

/* ===== MESSAGE ===== */
.msg{
    background: rgba(181, 250, 140, 1);
    border:1px solid #1fd819ff;
    padding:.8rem 1rem;
    border-radius:8px;
    margin-bottom:1rem;
    color: #107003ff;
}

/* ===== TABLE WRAPPER ===== */
.table-wrapper{
    background: #fff0c5ff;            /* ⬅ background belakang tabel */
    padding:1rem;
    border-radius:14px;
}

/* ===== TABLE ===== */
.customers-table{
    width:100%;
    border-collapse:collapse;
    background:#fff;
    border-radius:12px;
    overflow:hidden;
}

.customers-table th,
.customers-table td{
    padding:.85rem;
    border-bottom:1px solid #f1f1f1;
}

.customers-table th{
    background: #FF1493;            /* ⬅ pink */
    color: #fff;
    font-weight:700;
}

.customers-table tr:hover{
    background: #fff5fb;
}

/* ===== STATUS ===== */
.status-aktif{color: #2ecc71;font-weight:700}
.status-nonaktif{color: #db2c19ff;font-weight:700}
.status-hapus{color: #c0392b;font-weight:700}

/* ===== ACTION BUTTON ===== */
.actions{display:flex;gap:.45rem}

.icon-btn{
    width:36px;
    height:36px;
    border-radius:50%;
    border:none;
    cursor:pointer;
    font-size:16px;
    display:flex;
    align-items:center;
    justify-content:center;
    transition:.15s;
}

.btn-aktif{background: #eafff1;color: #1e8449}
.btn-nonaktif{background: #ffecec; color: #db2c19ff}
.btn-hapus{background: #ffdede; color: #c0392b}

.icon-btn:hover{
    transform:scale(1.1);
    box-shadow:0 4px 12px rgba(0,0,0,.18);
}


/* ===== BODY ===== */
body {
    margin: 0;
    font-family: Arial, sans-serif;
    background-color: #fff0c5ff; /* background body */
}











/* HEADER ADMIN – SAMA DENGAN KELOLA PESANAN */
.admin-header {
    background: linear-gradient(135deg, #FF1493, #FFD700);
    padding: 33px 30px;          /* tinggi header */
    border-bottom: 2px solid #ffd6e8;
}

/* ISI HEADER */
.navbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

/* JUDUL */
.navbar-title {
    font-size: 32px;
    font-weight: bold;
    color: #ffffffff;
}

/* TOMBOL KEMBALI */
.btn-back {
    background: #ff1493;         /* pink */
    color: #fff;
    padding: 10px 22px;
    border-radius: 999px;        /* elips */
    text-decoration: none;
    font-weight: 800;
    transition: all 0.25s ease;
}

/* HOVER: KUNING + TEKS PINK */
.btn-back:hover {
    background: #ffd700;         /* kuning */
    color: #ff1493;              /* pink */
}

</style>
</head>

<body>

<header class="admin-header">
    <div class="navbar">
        <div class="navbar-title">Kelola Akun Pelanggan</div>
        <a href="berandaAdmin.php" class="btn-back">Kembali</a>
    </div>
</header>


<div class="container">

<?php if (!empty($_SESSION['manage_customers_msg'])): ?>
<div class="msg"><?= htmlspecialchars($_SESSION['manage_customers_msg']) ?></div>
<?php unset($_SESSION['manage_customers_msg']); endif; ?>

<div class="table-wrapper">
<table class="customers-table">
<thead>
<tr>
    <th>Nama</th>
    <th>Email</th>
    <th>Telepon</th>
    <th>Status</th>
    <th>Tanggal Daftar</th>
    <th>Aksi</th>
</tr>
</thead>
<tbody>

<?php if (empty($customers)): ?>
<tr><td colspan="6" align="center">Belum ada data pelanggan</td></tr>
<?php else: foreach($customers as $c): ?>
<tr>
    <td><?= htmlspecialchars($c['name']) ?></td>
    <td><?= htmlspecialchars($c['email']) ?></td>
    <td><?= htmlspecialchars($c['phone']) ?></td>
    <td class="status-<?= $c['status'] ?>"><?= ucfirst($c['status']) ?></td>
    <td><?= $c['created_at'] ?></td>
    <td>
        <div class="actions">
            <form method="post">
                <input type="hidden" name="customer_id" value="<?= $c['customer_id'] ?>">
                <input type="hidden" name="action" value="set_aktif">
                <button class="icon-btn btn-aktif">✔</button>
            </form>
            <form method="post">
                <input type="hidden" name="customer_id" value="<?= $c['customer_id'] ?>">
                <input type="hidden" name="action" value="set_nonaktif">
                <button class="icon-btn btn-nonaktif">✖</button>
            </form>
            <form method="post" onsubmit="return confirm('Kunci akun ini?')">
                <input type="hidden" name="customer_id" value="<?= $c['customer_id'] ?>">
                <input type="hidden" name="action" value="set_hapus">
                <button class="icon-btn btn-hapus">🚫</button>
            </form>
        </div>
    </td>
</tr>
<?php endforeach; endif; ?>

</tbody>
</table>
</div>

</div>
</body>
</html>
