<?php
session_start();

// Check if order was successfully placed
if (!isset($_SESSION['order_success'])) {
    header("Location: index_customer.php");
    exit();
}

unset($_SESSION['order_success']);
$total = isset($_GET['total']) ? htmlspecialchars($_GET['total']) : 'N/A';
$customer_name = isset($_SESSION['customer_name']) ? htmlspecialchars($_SESSION['customer_name']) : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesanan Berhasil - Flowers Bouquet</title>
    <link rel="stylesheet" href="style.css">
    <style>
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: linear-gradient(135deg, #FF69B4 0%, #FFD700 100%);
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0;
    }

    .confirmation-card {
        background: #fff;
        border-radius: 12px;
        padding: 2rem;
        max-width: 500px;
        text-align: center;
        box-shadow: 0 10px 40px rgba(0,0,0,0.2);
    }

    .success-icon {
        font-size: 4rem;
        margin-bottom: 1rem;
    }

    .confirmation-card h1 {
        color: #FF1493;
        margin: 0 0 0.5rem 0;
        font-size: 2rem;
    }

    .confirmation-card p {
        color: #666;
        margin: 0.5rem 0;
        font-size: 1.05rem;
    }

    .order-details {
        background: #FFB6D9;
        padding: 1.5rem;
        border-radius: 8px;
        margin: 1.5rem 0;
        text-align: left;
    }

    .detail-item {
        display: flex;
        justify-content: space-between;
        margin-bottom: 0.75rem;
        color: #333;
    }

    .detail-item strong {
        color: #FF1493;
    }

    .action-buttons {
        display: flex;
        gap: 1rem;
        margin-top: 2rem;
    }

    .btn {
        flex: 1;
        padding: 0.75rem 1.5rem;
        border: none;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        display: inline-block;
        text-align: center;
        transition: filter 0.3s;
    }

    .btn-primary {
        background: #FF1493;
        color: #FFFF00;
    }

    .btn-primary:hover {
        filter: brightness(0.95);
    }

    .btn-secondary {
        background: #333;
        color: #fff;
    }

    .btn-secondary:hover {
        filter: brightness(0.9);
    }
    </style>
</head>
<body>
    <div class="confirmation-card">
        <div class="success-icon">✅</div>
        <h1>Pesanan Berhasil!</h1>
        <p>Terima kasih telah berbelanja di Flowers Bouquet</p>

        <div class="order-details">
            <div class="detail-item">
                <span>Nama Pelanggan:</span>
                <strong><?php echo $customer_name; ?></strong>
            </div>
            <div class="detail-item">
                <span>Total Pesanan:</span>
                <strong>Rp <?php echo $total; ?></strong>
            </div>
            <div class="detail-item">
                <span>Status:</span>
                <strong>Menunggu Konfirmasi</strong>
            </div>
            <div class="detail-item">
                <span>Estimasi Pengiriman:</span>
                <strong>1-3 hari kerja</strong>
            </div>
        </div>

        <p style="font-size: 0.95rem; color: #666;">
            📧 Anda akan menerima email konfirmasi pesanan dan informasi tracking pengiriman.
        </p>

        <div class="action-buttons">
            <a href="order_history.php" class="btn btn-primary">Lihat Pesanan</a>
            <a href="index_customer.php" class="btn btn-secondary">Lanjut Belanja</a>
        </div>
    </div>
</body>
</html>
