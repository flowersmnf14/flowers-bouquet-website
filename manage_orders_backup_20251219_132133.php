<?php
// Clean single-file manage_orders.php implementation using brace-style PHP blocks
session_start();
include 'config.php';

// Only allow admins
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: login.php');
    exit();
}

$orders = [];
$error = '';
try {
    $sql = "SELECT o.*, p.name AS product_name, p.price AS product_price, c.name AS customer_name, c.email AS customer_email, c.phone AS customer_phone, c.address AS customer_address
            FROM orders o
            LEFT JOIN products p ON o.product_id = p.id
            LEFT JOIN customers c ON o.customer_id = c.id
            ORDER BY CASE WHEN o.status = 'pending' THEN 1 ELSE 2 END, o.order_date DESC";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
    }
} catch (Exception $e) {
    $error = 'Kesalahan saat mengambil pesanan: ' . $e->getMessage();
}

// Load reviews map
$reviews_map = [];
<?php
// Minimal, clean manage_orders.php
session_start();
include 'config.php';

// Admin guard
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit();
}

$orders = [];
$error = '';
// Fetch orders
try {
    $sql = "SELECT o.*, p.name AS product_name, p.price AS product_price, c.name AS customer_name, c.email AS customer_email, c.phone AS customer_phone, c.address AS customer_address
            FROM orders o
            LEFT JOIN products p ON o.product_id = p.id
            LEFT JOIN customers c ON o.customer_id = c.id
            ORDER BY CASE WHEN o.status = 'pending' THEN 1 ELSE 2 END, o.order_date DESC";
    $res = $conn->query($sql);
    if ($res) {
        while ($r = $res->fetch_assoc()) { $orders[] = $r; }
    }
} catch (Exception $e) {
    $error = $e->getMessage();
}

// Load reviews map
$reviews_map = [];
if (!empty($orders)) {
    $ids = implode(',', array_map('intval', array_column($orders, 'id')));
    $rq = $conn->query("SHOW TABLES LIKE 'reviews'");
    if ($rq && $rq->num_rows > 0) {
        $rr = $conn->query("SELECT * FROM reviews WHERE order_id IN ($ids)");
        if ($rr) {
            while ($rv = $rr->fetch_assoc()) { $reviews_map[$rv['order_id']] = $rv; }
        }
    }
}

function status_label($s) {
    $map = ['pending'=>'Menunggu','processing'=>'Diproses','shipping'=>'Dikirim','completed'=>'Selesai','cancelled'=>'Dibatalkan'];
    return $map[$s] ?? $s;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kelola Pesanan</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <h1>Kelola Pesanan</h1>
        <a href="berandaAdmin.php">Kembali</a>
    </header>
    <main class="container">
        <?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if (empty($orders)): ?>
            <p>Tidak ada pesanan saat ini.</p>
        <?php else: ?>
            <?php foreach ($orders as $order) { ?>
                <div class="order-card">
                    <h3>Order #<?= (int)$order['id'] ?> — <?= htmlspecialchars($order['order_date'] ?? '-') ?></h3>
                    <div>Pelanggan: <?= htmlspecialchars($order['customer_name'] ?? '-') ?> — <?= htmlspecialchars($order['customer_email'] ?? '-') ?></div>
                    <div>Produk: <?= htmlspecialchars($order['product_name'] ?? '-') ?> x <?= (int)($order['quantity'] ?? 1) ?></div>
                    <div>Total: Rp <?= number_format($order['total_price'] ?? 0,0,',','.') ?></div>
                    <div>Alamat: <?= htmlspecialchars($order['customer_address'] ?? $order['address'] ?? '-') ?></div>
                    <form method="post" action="update_order_status.php">
                        <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
                        <select name="status">
                            <?php $opts = ['pending'=>'Menunggu','processing'=>'Diproses','shipping'=>'Dikirim','completed'=>'Selesai','cancelled'=>'Dibatalkan'];
                            foreach ($opts as $k=>$lbl) {
                                $sel = ($order['status'] === $k) ? 'selected' : '';
                                echo "<option value='".htmlspecialchars($k)."' $sel>".htmlspecialchars($lbl)."</option>";
                            }
                            ?>
                        </select>
                        <button type="submit">Update</button>
                    </form>
                    <?php if (isset($reviews_map[$order['id']])): $rv = $reviews_map[$order['id']]; ?>
                        <div class="review">Ulasan: <?= nl2br(htmlspecialchars($rv['review_text'])) ?> (<?= (int)$rv['rating'] ?>/5)</div>
                    <?php endif; ?>
                </div>
            <?php } ?>
        <?php endif; ?>
    </main>
</body>
</html>
                                    </thead>
