<?php
session_start();
include 'config.php';

/* ===============================
   CEK LOGIN ADMIN
================================ */
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit();
}

/* ===============================
   UPDATE STATUS ORDER (TAMBAHAN SAJA)
================================ */
if (isset($_POST['update_status'])) {

    $order_id = (int)$_POST['order_id'];
    $status   = $_POST['status'];

    if ($order_id > 0 && !empty($status)) {
        $stmt = $conn->prepare("
            UPDATE orders 
            SET status = ?
            WHERE id_orders = ?
        ");
        $stmt->bind_param("si", $status, $order_id);
        $stmt->execute();

        header("Location: manage_orders.php");
        exit();
    }
}

$filter_status = $_GET['status'] ?? '';
$search_name   = $_GET['search'] ?? '';

$orders = [];
$grouped_orders = [];
$error  = '';

try {

    /* ===============================
       AMBIL ORDER + CUSTOMER + PEMBAYARAN
    ================================ */
    $sql = "
        SELECT 
            o.id_orders,
            o.customer_id,
            o.order_date,
            o.status,

            c.name  AS customer_name,
            c.email AS customer_email,
            c.phone AS customer_phone,

            pay.metode_bayar,
            pay.buktiTransfer,
            pay.statusBayar

        FROM orders o
        JOIN customers c ON c.customer_id = o.customer_id
        LEFT JOIN pembayaran pay ON pay.id_orders = o.id_orders
        WHERE 1=1
    ";

    if (!empty($filter_status)) {
        $sql .= " AND o.status = '" . $conn->real_escape_string($filter_status) . "'";
    }

    if (!empty($search_name)) {
        $sql .= " AND c.name LIKE '%" . $conn->real_escape_string($search_name) . "%'";
    }

    $sql .= " ORDER BY o.order_date DESC";

    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $orders[] = $row;
        }
    }

    /* ===============================
       GABUNGKAN PER ORDER
    ================================ */
    foreach ($orders as $order) {
        $order_id = (int)$order['id_orders'];

        if (!isset($grouped_orders[$order_id])) {

            $addr = [
                'alamat' => '-',
                'kecamatan' => '',
                'kota' => '',
                'provinsi' => '',
                'negara' => '',
                'nama_penerima' => '',
                'alamat_hp' => ''
            ];

            $addr_res = $conn->query("
                SELECT * FROM alamat_user
                WHERE customer_id = {$order['customer_id']}
                ORDER BY is_default DESC
                LIMIT 1
            ");

            if ($addr_res && $addr_row = $addr_res->fetch_assoc()) {
                $addr = [
                    'alamat' => $addr_row['alamat'] ?? '-',
                    'kecamatan' => $addr_row['kecamatan'] ?? '',
                    'kota' => $addr_row['kota'] ?? '',
                    'provinsi' => $addr_row['provinsi'] ?? '',
                    'negara' => $addr_row['negara'] ?? '',
                    'nama_penerima' => $addr_row['nama_penerima'] ?? '',
                    'alamat_hp' => $addr_row['nomor_hp'] ?? ''
                ];
            }

            $grouped_orders[$order_id] = [
                'order_id' => $order_id,
                'order_date' => $order['order_date'],
                'status' => $order['status'],

                'customer_name' => $order['customer_name'],
                'customer_email' => $order['customer_email'],
                'customer_phone' => $order['customer_phone'],

                'alamat' => $addr['alamat'],
                'kecamatan' => $addr['kecamatan'],
                'kota' => $addr['kota'],
                'provinsi' => $addr['provinsi'],
                'negara' => $addr['negara'],
                'nama_penerima' => $addr['nama_penerima'],
                'alamat_hp' => $addr['alamat_hp'],

                'metode_bayar' => $order['metode_bayar'],
                'buktiTransfer' => $order['buktiTransfer'],
                'statusBayar' => $order['statusBayar'],

                'products' => [],
                'total_order' => 0
            ];

            $prod_res = $conn->query("
                SELECT 
                    p.name,
                    p.image,
                    dp.jumlah,
                    dp.subtotal
                FROM detailproduk dp
                JOIN products p ON p.product_id = dp.product_id
                WHERE dp.id_orders = $order_id
            ");

            if ($prod_res) {
                while ($prod = $prod_res->fetch_assoc()) {
                    $grouped_orders[$order_id]['products'][] = [
                        'name' => $prod['name'],
                        'image' => $prod['image'],
                        'jumlah' => (int)$prod['jumlah'],
                        'subtotal' => (int)$prod['subtotal']
                    ];

                    $grouped_orders[$order_id]['total_order'] += (int)$prod['subtotal'];
                }
            }
        }
    }

} catch (Exception $e) {
    $error = $e->getMessage();
}

/* ===============================
   LABEL STATUS
================================ */
function status_label($s) {
    return [
        'pending'   => 'Menunggu',
        'prepared'  => 'Dipersiapkan',
        'shipped'   => 'Dikirim',
        'completed' => 'Selesai',
        'rejected'  => 'Ditolak'
    ][$s] ?? $s;
}

function metode_bayar_label($m) {
    return [
        'pay_on_pickup' => 'Bayar di Toko',
        'bank_transfer' => 'Transfer Bank',
        'e_wallet'      => 'E-Wallet',
        'cod'           => 'COD'
    ][$m] ?? $m;
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Kelola Pesanan</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="style.css"><!-- CSS TIDAK DIUBAH -->


<style>
/* ===== TABEL PINK ADMIN ===== */
table {
    border-collapse: collapse;
    background: #fff;
    font-size: 14px;
}

table thead th {
    background: #ff69b4; /* pink */
    color: #fff;
    text-align: center;
    padding: 10px;
    border: 1px solid #f3a6c9;
}

table tbody td {
    padding: 10px;
    vertical-align: top;
    border: 1px solid #f0f0f0;
}

/* Zebra row */
table tbody tr:nth-child(even) {
    background: #fff0f6;
}

/* Hover efek */
table tbody tr:hover {
    background: #ffe4ef;
}

/* Tombol */
table button {
    background: #ff69b4; 
    border: none;
    color: white;
    padding: 6px 12px;
    border-radius: 4px;
    cursor: pointer;
}

table button:hover {
    background: #ff4fa3;
}

/* Select */
table select {
    padding: 5px;
    border-radius: 4px;
    border: 1px solid #ddd;
}

/* Link bukti */
table a {
    color: #ff1493;
    text-decoration: none;
    font-weight: 500;
}

table a:hover {
    text-decoration: underline;
}

/* JARAK TABEL KE SAMPING */
table {
    margin: 20px auto;      /* jarak luar kiri-kanan */
    width: 96%;             /* tidak mepet pinggir */
}

/* JARAK ISI DALAM SEL */
table th,
table td {
    padding: 12px 14px;     /* atas-bawah | kiri-kanan */
}

/* Biar container juga lega */
.container {
    padding: 20px;
}

body {
    margin: 0;
    padding: 0;
    background-color: #fff0c5ff;
}

/* === FORM CARI & FILTER TENGAH ELIPS === */
.filter-form {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 10px;

    background-color: #fff0c5ff; /* cream */
    padding: 14px 22px;
    border-radius: 999px; /* ELIPS */
    margin: 0 auto 25px auto;
    width: fit-content;

    box-shadow: 0 4px 10px rgba(0,0,0,0.08);
}

/* input & select */
.filter-form input[type="text"],
.filter-form select {
    padding: 8px 14px;
    border-radius: 999px;
    border: 1px solid #e2d3a2;
    outline: none;
}

/* tombol */
.filter-form button {
    padding: 8px 18px;
    border-radius: 999px;
    border: none;
    background: #ff69b4;
    color: #fff;
    cursor: pointer;
}

.filter-form button:hover {
    background: #ff4fa3;
}



/* HEADER BAR */
.header-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 30px;
    background: linear-gradient(90deg, #ff1493, #ffc107);
}

.header-bar h1 {
    margin: 0;
    color: #fff;
}

/* AREA KANAN (KEMBALI + FILTER) */
.header-actions {
    display: flex;
    align-items: center;
    gap: 12px; /* jarak dekat */
}

/* TOMBOL KEMBALI */
.btn-kembali {
    background: #ff1493;
    color: #fff;
    padding: 10px 20px;
    border-radius: 999px; /* ELIPS */
    text-decoration: none;
    font-weight: 600;
    white-space: nowrap;
}

.btn-kembali:hover {
    background: #ff0080;
}

/* FILTER FORM */
.filter-form {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #fff0c5ff;
    padding: 8px 14px;
    border-radius: 999px; /* ELIPS PANJANG */
}

.filter-form input,
.filter-form select {
    border-radius: 999px;
    border: 1px solid #ddd;
    padding: 6px 12px;
}

.filter-form button {
    background: #ff69b4;
    color: #fff;
    border: none;
    padding: 6px 16px;
    border-radius: 999px;
    cursor: pointer;
}

.filter-form button:hover {
    background: #ff4fa3;
}


/* Form filter (elips cream) */
.filter-form {
    display: flex;
    align-items: center;
    gap: 10px;

    background: #fff0c5;
    padding: 10px 18px;
    border-radius: 999px;

    /* INI KUNCINYA */
    position: relative;
    top: 12px;   /* ⬅️ turunin dikit */
}








/* Tombol kembali default */
.btn-kembali {
    display: inline-block;
    padding: 10px 22px;
    background-color:  #ff1493;   /* pink */
    color: #ffffff;              /* putih */
    border-radius: 999px;         /* elips */
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
}

/* Saat kursor diarahkan */
.btn-kembali:hover {
    background-color: #FFD700;    /* kuning */
    color:  #ff1493;               /* tulisan pink */
}


/* ulasan pelanggan */
.btn-admin-ulasan{
    display:inline-block;
    background: linear-gradient(135deg, #FF69B4, #FF1493);
    color:#fff;
    padding:5px 16px;
    border-radius:999px;
    text-decoration:none;
    font-weight:bold;
    font-size:14px;
    box-shadow:0 6px 12px rgba(255,20,147,.35);
    transition:.25s ease;
}

.btn-admin-ulasan:hover{
    transform:translateY(-2px);
    box-shadow:0 10px 18px rgba(255,20,147,.5);
    opacity:.95;
}

</style>


</head>

<body>

<header class="header-bar">
    <h1>Kelola Pesanan</h1>

    <div class="header-actions">
        
        <form method="get" class="filter-form">

                    <a href="ulasan.php" class="btn-admin-ulasan">
                    ⭐ Lihat Ulasan Pelanggan
                    </a>

            <input type="text" name="search" placeholder="Cari nama pelanggan..." value="<?= htmlspecialchars($search_name) ?>">
            <select name="status">
                <option value="">Semua Status</option>
                <?php foreach (['pending','prepared','shipped','completed','rejected'] as $s): ?>
                    <option value="<?= $s ?>" <?= ($filter_status==$s?'selected':'') ?>>
                        <?= status_label($s) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit">Filter</button>
        </form>
        <a href="berandaAdmin.php" class="btn-kembali">Kembali</a>
    </div>
</header>


<main class="container">

<?php if (!empty($error)): ?>
    <div class="alert error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if (empty($grouped_orders)): ?>
    <p>Tidak ada pesanan.</p>
<?php else: ?>

<table border="1" cellpadding="8" cellspacing="0" width="100%">
<thead>
<tr>
    <th>No</th>
    <th>Tanggal</th>
    <th>Pelanggan</th>
    <th>Alamat</th>
    <th>Produk</th>
    <th>Total</th>
    <th>Pembayaran</th>
    <th>Status</th>
    <th>Aksi</th>
</tr>
</thead>
<tbody>

<?php $no=1; foreach ($grouped_orders as $order): ?>
<tr>
    <td><?= $no++ ?></td>
    <td><?= htmlspecialchars($order['order_date']) ?></td>
    <td>
        <?= htmlspecialchars($order['customer_name']) ?><br>
        <?= htmlspecialchars($order['customer_email']) ?><br>
        <?= htmlspecialchars($order['customer_phone']) ?>
    </td>
    <td>
        <?= htmlspecialchars($order['alamat']) ?><br>
        <?= htmlspecialchars($order['kecamatan']) ?>,
        <?= htmlspecialchars($order['kota']) ?>,
        <?= htmlspecialchars($order['provinsi']) ?>
    </td>
    <td>
        <?php foreach ($order['products'] as $p): ?>
            <?= htmlspecialchars($p['name']) ?> x <?= $p['jumlah'] ?>
            (Rp <?= number_format($p['subtotal'],0,',','.') ?>)<br>
        <?php endforeach; ?>
    </td>
    <td>Rp <?= number_format($order['total_order'],0,',','.') ?></td>
    <td>
      
    <?= metode_bayar_label($order['metode_bayar'] ?? '-') ?><br>


        <?php if (!empty($order['buktiTransfer'])): ?>
    <a href="uploads/bukti/<?= htmlspecialchars($order['buktiTransfer']) ?>" target="_blank">
        Lihat Bukti
    </a>
<?php else: ?>
    <span>-</span>
<?php endif; ?>


    </td>
    <td><?= status_label($order['status']) ?></td>
    <td>
        <form method="post">
            <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
            <select name="status">
                <?php foreach (['pending','prepared','shipped','completed','rejected'] as $s): ?>
                    <option value="<?= $s ?>" <?= ($order['status']==$s?'selected':'') ?>>
                        <?= status_label($s) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" name="update_status">Update</button>
        </form>
    </td>
</tr>
<?php endforeach; ?>

</tbody>
</table>

<?php endif; ?>

</main>
</body>
</html>
