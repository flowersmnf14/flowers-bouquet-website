<?php
session_start();
include 'config.php';

// Only logged-in customers can submit reviews
if (!isset($_SESSION['customer_logged_in']) || !$_SESSION['customer_logged_in']) {
    header('Location: login_customer.php');
    exit();
}

$customer_id = $_SESSION['customer_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: order_history.php');
    exit();
}

$order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
$rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
$review_text = isset($_POST['review_text']) ? trim($_POST['review_text']) : '';

if ($order_id <= 0 || $rating < 1 || $rating > 5) {
    $_SESSION['review_error'] = 'Input review tidak valid.';
    header('Location: order_history.php');
    exit();
}

try {
    // Ensure the order belongs to this customer and is completed
    $stmt = $conn->prepare('SELECT id, product_id, status FROM orders WHERE id = ? AND customer_id = ? LIMIT 1');
    $stmt->bind_param('ii', $order_id, $customer_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if (!$res || $res->num_rows === 0) {
        $_SESSION['review_error'] = 'Pesanan tidak ditemukan.';
        header('Location: order_history.php');
        exit();
    }
    $row = $res->fetch_assoc();
    $product_id = (int)$row['product_id'];
    $status = $row['status'];
    $stmt->close();

    if ($status !== 'completed') {
        $_SESSION['review_error'] = 'Ulasan hanya dapat diberikan setelah pesanan selesai.';
        header('Location: order_history.php');
        exit();
    }

    // Create reviews table if not exists (safe, low-risk migration)
    $createSql = "CREATE TABLE IF NOT EXISTS reviews (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        product_id INT DEFAULT NULL,
        customer_id INT NOT NULL,
        rating TINYINT NOT NULL,
        review_text TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY ux_order_customer (order_id, customer_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $conn->query($createSql);

    // Insert or update review for this order/customer
    $stmtUp = $conn->prepare("INSERT INTO reviews (order_id, product_id, customer_id, rating, review_text) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE rating = VALUES(rating), review_text = VALUES(review_text), created_at = NOW()");
    $stmtUp->bind_param('iiiis', $order_id, $product_id, $customer_id, $rating, $review_text);
    $stmtUp->execute();
    $stmtUp->close();

    $_SESSION['review_success'] = 'Terima kasih, ulasan anda telah dikirim.';
    header('Location: order_history.php');
    exit();

} catch (Exception $e) {
    $_SESSION['review_error'] = 'Gagal menyimpan ulasan: ' . $e->getMessage();
    header('Location: order_history.php');
    exit();
}

?>
