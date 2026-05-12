<?php
session_start();
include 'config.php';

// Redirect jika belum login
if (!isset($_SESSION['customer_logged_in']) || !$_SESSION['customer_logged_in']) {
    header("Location: login_customer.php");
    exit();
}

$customer_id   = $_SESSION['customer_id'];
$customer_name = $_SESSION['customer_name'];

// ==========================
// CEK STATUS AKUN CUSTOMER
// ==========================
$stmtStatus = $conn->prepare(
    "SELECT COALESCE(`status`, 'aktif') AS status 
     FROM customers 
     WHERE customer_id = ? 
     LIMIT 1"
);
$stmtStatus->bind_param('i', $customer_id);
$stmtStatus->execute();

$resStatus  = $stmtStatus->get_result();
$custStatus = 'aktif';

if ($resStatus && $resStatus->num_rows > 0) {
    $custStatus = $resStatus->fetch_assoc()['status'];
}
$stmtStatus->close();

if (strtolower((string)$custStatus) !== 'aktif') {
    unset(
        $_SESSION['customer_logged_in'],
        $_SESSION['customer_id'],
        $_SESSION['customer_name'],
        $_SESSION['cart']
    );
    header("Location: login_customer.php?blocked=1");
    exit();
}

// ==========================
// INIT CART
// ==========================
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// ==========================
// HAPUS ITEM DARI CART
// ==========================
if (isset($_POST['remove_from_cart'])) {
    $product_id = (int) $_POST['product_id'];
    unset($_SESSION['cart'][$product_id]);
    header("Location: cart.php");
    exit();
}

// ==========================
// UPDATE JUMLAH ITEM
// ==========================
if (isset($_POST['update_quantity'])) {
    $product_id = (int) $_POST['product_id'];
    $quantity   = (int) $_POST['quantity'];

    if ($quantity > 0) {
        // cek stok
        $stmt = $conn->prepare(
            "SELECT stock 
             FROM products 
             WHERE product_id = ?"
        );
        $stmt->bind_param('i', $product_id);
        $stmt->execute();
        $res   = $stmt->get_result();
        $stock = 0;

        if ($res && $res->num_rows > 0) {
            $stock = (int) $res->fetch_assoc()['stock'];
        }
        $stmt->close();

        if ($stock <= 0) {
            unset($_SESSION['cart'][$product_id]);
            $_SESSION['cart_message'] = 'Maaf, produk ini sedang habis.';
        } else {
            if ($quantity > $stock) {
                $quantity = $stock;
                $_SESSION['cart_message'] =
                    "Jumlah dikurangi sesuai stok tersedia ($stock).";
            }
            $_SESSION['cart'][$product_id] = $quantity;
        }
    } else {
        unset($_SESSION['cart'][$product_id]);
    }

    header("Location: cart.php");
    exit();
}

// ==========================
// AMBIL DATA PRODUK DI CART
// ==========================
$cart_items  = [];
$total_price = 0;

if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $product_id => $quantity) {
        $stmt = $conn->prepare(
            "SELECT * 
             FROM products 
             WHERE product_id = ?"
        );
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $product     = $result->fetch_assoc();
            $item_total  = $product['price'] * $quantity;
            $total_price += $item_total;

            $cart_items[] = [
                'product'     => $product,
                'quantity'    => $quantity,
                'item_total'  => $item_total
            ];
        }
        $stmt->close();
    }
}

$pajak_persen = 1;
$pajak = $total_price * ($pajak_persen / 100);
$grand_total = $total_price + $pajak;

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keranjang - Flowers Bouquet</title>
    <link rel="stylesheet" href="style.css">
    <style>
    .customer-header {
    background: linear-gradient(135deg, #FF69B4 0%, #FFD700 100%);
    padding: 1rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    color: #fff;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.customer-header h1 {
    margin: 0;
    font-size: 1.5rem;
}

.header-links {
    display: flex;
    gap: 1rem;
}

.header-links a {
    color: #fff;
    text-decoration: none;
    padding: 0.5rem 1rem;
    background: rgba(255,255,255,0.1);
    border-radius: 20px;
    border: 2px solid transparent;
    transition: border-color 0.3s;
}

.header-links a:hover {
    border-color: #fff;
}

.container {
    max-width: 1000px;
    margin: 0 auto;
    padding: 2rem 1rem;
}

.cart-header {
    text-align: center;
    color: #FF1493;
    margin-bottom: 2rem;
}

.cart-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 2rem;
    background: #fff;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.cart-table th {
    background: #FF1493;
    color: #fff;
    padding: 1rem;
    text-align: left;
    font-weight: 600;
}

.cart-table td {
    padding: 1rem;
    border-bottom: 1px solid #FFB6D9;
}

.cart-table tr:last-child td {
    border-bottom: none;
}

.product-cell {
    display: flex;
    gap: 1rem;
    align-items: center;
}

.product-cell img {
    width: 80px;
    height: 80px;
    object-fit: cover;
    border-radius: 6px;
}

.quantity-input-cart {
    width: 70px;
    padding: 0.5rem;
    border: 2px solid #FFD700;
    border-radius: 6px;
}

.remove-btn {
    background: #FF6B6B;
    color: #fff;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    cursor: pointer;
}

.remove-btn:hover {
    filter: brightness(0.9);
}

.cart-summary {
    background: #FFB6D9;
    padding: 1.5rem;
    border-radius: 8px;
    text-align: right;
    margin-bottom: 2rem;
}

.summary-row {
    display: flex;
    justify-content: flex-end;
    margin-bottom: 0.5rem;
    font-size: 1.1rem;
}

.summary-total {
    display: flex;
    justify-content: flex-end;
    margin-top: 1rem;
    font-size: 1.5rem;
    font-weight: 700;
    color: #FF1493;
    border-top: 2px solid #FF1493;
    padding-top: 1rem;
}

.action-buttons {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
}

.btn-continue {
    background: #333;
    color: #fff;
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
}

.btn-checkout {
    background: #FF1493;
    color: #FFFF00;
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    font-size: 1.1rem;
}

.btn-checkout:hover {
    filter: brightness(0.95);
}

.empty-cart {
    text-align: center;
    padding: 3rem;
    color: #999;
}

.empty-cart a {
    color: #FF1493;
    text-decoration: none;
    font-weight: 600;
}

/* Hilangkan garis bawah pada semua tombol link */
.action-buttons a,
.btn-continue,
.btn-checkout,
.empty-cart a {
    text-decoration: none !important;
}

/* Biar aman saat hover & focus juga */
.action-buttons a:hover,
.action-buttons a:focus,
.btn-continue:hover,
.btn-checkout:hover {
    text-decoration: none !important;
}

/* Tombol navbar */
.header-links a {
    background: #FF1493; /* pink cerah */
    color: #fff;
    text-decoration: none;
    padding: 0.5rem 1.2rem;
    border-radius: 20px;
    font-weight: 600;
    border: none;
    transition: background 0.2s ease, transform 0.1s ease;
}

/* Hover */
.header-links a:hover {
    background: #ff3399;
}

/* Saat diklik / fokus → tetap pink */
.header-links a:active,
.header-links a:focus {
    background: #FF1493;
    color: #fff;
    outline: none;
    box-shadow: none;
    transform: scale(0.97); /* efek klik halus */
}

.cart-summary {
    background: #FFB6D9; /* pink muda */
    padding: 1.5rem;
    border-radius: 10px;
    margin-bottom: 2rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

/* Baris subtotal & pajak */
.summary-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.5rem;
    font-size: 1.1rem;
    color: #333;
}

/* Total lebih tegas tapi masih satu kotak */
.summary-total {
    display: flex;
    justify-content: space-between;
    margin-top: 1rem;
    padding-top: 0.75rem;
    border-top: 2px solid #FF1493;
    font-size: 1.4rem;
    font-weight: 700;
    color: #FF1493;
}



    </style>
</head>
<body>
    <div class="customer-header">
        <h1>🛒 Keranjang Belanja</h1>
        <div class="header-links">
            <a href="index_customer.php">← Lanjut Belanja</a>
            <a href="#" onclick="confirmLogout()">Logout</a>
        </div>
    </div>

    <div class="container">
        <h2 class="cart-header">Keranjang Belanja Anda</h2>

        <?php if (isset($_SESSION['cart_message'])): ?>
    <div style="background:#fff3f6; padding:0.75rem; border-radius:6px; margin-bottom:1rem; color:#333; border:1px solid #ffd1e6;">
        <?php
            echo htmlspecialchars($_SESSION['cart_message']);
            unset($_SESSION['cart_message']);
        ?>
    </div>
<?php endif; ?>


        <?php if (count($cart_items) > 0): ?>
        <table class="cart-table">
            <thead>
                <tr>
                    <th>Produk</th>
                    <th style="text-align: center;">Jumlah</th>
                    <th style="text-align: right;">Harga Satuan</th>
                    <th style="text-align: right;">Total</th>
                    <th style="text-align: center;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cart_items as $item): ?>
                <tr>
                    <td>
                        <div class="product-cell">
    <img
        src="<?php echo htmlspecialchars($item['product']['image']); ?>"
        alt="<?php echo htmlspecialchars($item['product']['name']); ?>"
        onerror="this.src='https://via.placeholder.com/80x80/FFB6D9/000000?text=Produk'">

    <div class="product-info-text">
        <strong><?php echo htmlspecialchars($item['product']['name']); ?></strong><br>
        <small class="product-category">
            <?php echo htmlspecialchars($item['product']['category']); ?>
        </small>
    </div>
</div>

                    </td>
      <td style="text-align: center;">
    <form method="POST" style="display: inline-flex; align-items: center; gap: 0.5rem;">
        <input type="hidden" name="product_id" value="<?php echo $item['product']['product_id']; ?>">

        <input type="number"
               name="quantity"
               value="<?php echo $item['quantity']; ?>"
               min="1"
               class="quantity-input-cart"
               style="width: 60px; text-align: center;">

        <button type="submit"
                name="update_quantity"
                style="padding: 0.35rem 0.75rem; background: #FFD700; border: none; border-radius: 4px; cursor: pointer;">
            Update
        </button>
    </form>
</td>

                  <td style="text-align: right;">
    Rp <?php echo number_format($item['product']['price'], 0, ',', '.'); ?>
</td>

<td style="text-align: right;">
    <strong>Rp <?php echo number_format($item['item_total'], 0, ',', '.'); ?></strong>
</td>

<td style="text-align: center;">
    <form method="POST" style="display: inline;">
        <input type="hidden" name="product_id" value="<?php echo $item['product']['product_id']; ?>">
        <button type="submit" name="remove_from_cart" class="remove-btn">
            Hapus
        </button>
    </form>
</td>

                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="cart-summary">
    <div class="summary-row">
        <span style="flex: 1; text-align: left;">Subtotal:</span>
        <span>Rp <?php echo number_format($total_price, 0, ',', '.'); ?></span>
    </div>

    <div class="summary-row">
        <span style="flex: 1; text-align: left;">Pajak (1%):</span>
        <span>Rp <?php echo number_format($pajak, 0, ',', '.'); ?></span>
    </div>

    <div class="summary-total">
        <span style="flex: 1; text-align: left;">Total:</span>
        <span>Rp <?php echo number_format($grand_total, 0, ',', '.'); ?></span>
    </div>
</div>


        <div class="action-buttons">
            <a href="index_customer.php" class="btn-continue">← Lanjut Belanja</a>
            <a href="checkout.php" class="btn-checkout">Lanjut ke Checkout →</a>
        </div>

        <?php else: ?>
        <div class="empty-cart">
            <h3>Keranjang Anda Kosong</h3>
            <p>Belum ada produk yang ditambahkan ke keranjang.</p>
            <a href="index_customer.php">Kembali ke Toko →</a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Logout Modal -->
    <style>
    #logoutModal {
        display: none;
        position: fixed;
        z-index: 9999;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0,0,0,0.5);
        align-items: center;
        justify-content: center;
    }
    #logoutModal .modal-card {
        background: #fff;
        padding: 1.25rem;
        border-radius: 8px;
        width: 90%;
        max-width: 420px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        text-align: center;
    }
    #logoutModal .modal-actions { margin-top: 1rem; display:flex; gap:0.5rem; justify-content:center }
    #logoutModal .btn-ya { background:#FF1493; color:#fff; border:none; padding:0.6rem 1rem; border-radius:6px; cursor:pointer }
    #logoutModal .btn-tidak { background:#FFD700; color:#000; border:none; padding:0.6rem 1rem; border-radius:6px; cursor:pointer }
    </style>

    <div id="logoutModal">
        <div class="modal-card" role="dialog" aria-modal="true">
            <h3>Konfirmasi Logout</h3>
            <p>Apakah yakin ingin LOGOUT?</p>
            <div class="modal-actions">
                <button class="btn-ya" id="logoutYes">Ya</button>
                <button class="btn-tidak" id="logoutNo">Tidak</button>
            </div>
        </div>
    </div>

    <script>
    function confirmLogout() {
        document.getElementById('logoutModal').style.display = 'flex';
    }

    document.getElementById('logoutYes').addEventListener('click', function() {
        window.location.href = 'logout_customer.php';
    });

    document.getElementById('logoutNo').addEventListener('click', function() {
        document.getElementById('logoutModal').style.display = 'none';
    });

    document.getElementById('logoutModal').addEventListener('click', function(e) {
        if (e.target === this) {
            this.style.display = 'none';
        }
    });
    </script>
</body>
</html>
