<?php
session_start();
include 'config.php';

// Only admin
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('HTTP/1.1 403 Forbidden');
    echo 'Akses ditolak.';
    exit();
}

// Fetch orders
$orders = [];
try {
    $sql = "SELECT o.*, p.name AS product_name, p.price AS product_price, c.name AS customer_name, c.email AS customer_email, c.phone AS customer_phone, c.address AS customer_address
            FROM orders o
            LEFT JOIN products p ON o.product_id = p.id
            LEFT JOIN customers c ON o.customer_id = c.id
            ORDER BY o.order_date DESC";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
    }
} catch (Exception $e) {
    // ignore and continue with empty
}

$filename = 'laporan_pesanan_' . date('Ymd_His') . '.doc';
header("Content-Type: application/msword; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header('Pragma: no-cache');
header('Expires: 0');

echo "<html><head><meta charset=\"utf-8\"><title>Laporan Pesanan</title></head><body>";
echo "<h2>Laporan Pesanan - " . date('d-m-Y H:i') . "</h2>";
if (empty($orders)) {
    echo "<p>Tidak ada pesanan.</p>";
} else {
    foreach ($orders as $order) {
        echo "<h3>Order #" . htmlspecialchars($order['id']) . " - " . htmlspecialchars($order['order_date'] ?? '-') . "</h3>";
        echo "<p><strong>Pelanggan:</strong> " . htmlspecialchars($order['customer_name'] ?? '-') . " (" . htmlspecialchars($order['customer_email'] ?? '-') . ")</p>";
        echo "<p><strong>Alamat:</strong> " . htmlspecialchars($order['customer_address'] ?? ($order['address'] ?? '-')) . "</p>";
        echo "<table border=1 cellspacing=0 cellpadding=6><tr><th>Produk</th><th>Harga</th><th>Kuantitas</th><th>Subtotal</th></tr>";
        $price = $order['product_price'] ?? ($order['price'] ?? 0);
        $qty = $order['quantity'] ?? 1;
        $subtotal = $order['total_price'] ?? ($price * $qty);
        echo "<tr><td>" . htmlspecialchars($order['product_name'] ?? '-') . "</td><td>Rp " . number_format($price,0,',','.') . "</td><td>" . htmlspecialchars($qty) . "</td><td>Rp " . number_format($subtotal,0,',','.') . "</td></tr>";
        echo "</table>";
        echo "<p><strong>Status:</strong> " . htmlspecialchars($order['status'] ?? 'Menunggu') . "</p>";
        echo "<hr/>";
    }
}

echo "</body></html>";

exit();
