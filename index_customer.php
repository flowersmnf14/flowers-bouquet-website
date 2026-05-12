<?php
session_start();
include 'config.php';

// Redirect jika customer belum login
if (!isset($_SESSION['customer_logged_in']) || !$_SESSION['customer_logged_in']) {
    header("Location: login_customer.php");
    exit();
}

$customer_id   = $_SESSION['customer_id'];
$customer_name = $_SESSION['customer_name'];

// ==========================
// AMBIL PRODUK (DENGAN / TANPA KATEGORI)
// ==========================
$products = [];
$kategori = $_GET['kategori'] ?? '';

try {
    if ($kategori !== '') {
        $stmt = $conn->prepare(
            "SELECT * FROM products 
             WHERE category = ? 
             ORDER BY product_id DESC"
        );
        $stmt->bind_param("s", $kategori);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query(
            "SELECT * FROM products 
             ORDER BY product_id DESC"
        );
    }

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
    }
} catch (mysqli_sql_exception $e) {
    echo "Kesalahan saat mengambil produk: " . htmlspecialchars($e->getMessage());
}

// ==========================
// HANDLE ADD TO CART
// ==========================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $product_id = (int) $_POST['product_id'];
    $quantity   = max(1, (int) $_POST['quantity']);

    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    // Cek stok produk
    $stock = 0;
    try {
        $stmt = $conn->prepare(
            "SELECT stock 
             FROM products 
             WHERE product_id = ?"
        );
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res && $res->num_rows > 0) {
            $stock = (int) $res->fetch_assoc()['stock'];
        }
        $stmt->close();
    } catch (mysqli_sql_exception $e) {
        $stock = 0;
    }

    // Jika stok habis
    if ($stock <= 0) {
        $_SESSION['cart_message'] = "Maaf, produk ini sedang habis.";
        header("Location: index_customer.php");
        exit();
    }

    $current_qty = $_SESSION['cart'][$product_id] ?? 0;
    $desired     = $current_qty + $quantity;

    if ($desired > $stock) {
        $_SESSION['cart'][$product_id] = $stock;
        $_SESSION['cart_message'] =
            ($current_qty >= $stock)
                ? "Jumlah di keranjang sudah mencapai batas stok ($stock)."
                : "Jumlah ditambahkan sampai batas stok tersedia ($stock).";
    } else {
        $_SESSION['cart'][$product_id] = $desired;
        $_SESSION['cart_message'] = "Produk berhasil ditambahkan ke keranjang!";
    }

    header("Location: index_customer.php");
    exit();
}

// ==========================
// INFO KERANJANG
// ==========================
$cart_message = $_SESSION['cart_message'] ?? '';
unset($_SESSION['cart_message']);

$cart_count = isset($_SESSION['cart'])
    ? array_sum($_SESSION['cart'])
    : 0;
?>










<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Pelanggan - Flowers Bouquet</title>
    <link rel="stylesheet" href="style.css">
    <style>
  
  body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }


    
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

    .customer-header-right {
        display: flex;
        gap: 1rem;
        align-items: center;
    }

    .cart-btn {
        background: #fff;
        color: #FF1493;
        padding: 0.5rem 1rem;
        border-radius: 20px;
        text-decoration: none;
        font-weight: 600;
        position: relative;
    }

    .cart-badge {
        position: absolute;
        top: -8px;
        right: -8px;
        background: #FF1493;
        color: #fff;
        border-radius: 50%;
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
        font-weight: bold;
    }

    .logout-btn-customer {
        background: rgba(255,255,255,0.2);
        color: #fff;
        padding: 0.5rem 1rem;
        border-radius: 20px;
        text-decoration: none;
        border: 2px solid #fff;
        cursor: pointer;
        font-weight: 600;
    }

    .logout-btn-customer:hover {
        background: rgba(255,255,255,0.3);
    }

    .container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 2rem 1rem;
    }

    .success-message {
        background: #4CAF50;
        color: #fff;
        padding: 1rem;
        border-radius: 8px;
        margin-bottom: 2rem;
        text-align: center;
    }

    .products-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 2rem;
    }

    .product-card {
        border: 2px solid #FFB6D9;
        border-radius: 12px;
        overflow: hidden;
        background: #fff;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        transition: transform 0.3s, box-shadow 0.3s;
    }

    .product-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.15);
    }

    .product-image {
        width: 100%;
        height: 200px;
        object-fit: cover;
        background: #f5f5f5;
    }

    .product-info {
        padding: 1rem;
    }

    .product-name {
        font-size: 1.1rem;
        font-weight: 600;
        color: #333;
        margin: 0 0 0.5rem 0;
    }

    .product-category {
        font-size: 0.85rem;
        color: #FF1493;
        background: #FFB6D9;
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        display: inline-block;
        margin-bottom: 0.75rem;
    }

    .product-price {
        font-size: 1.3rem;
        font-weight: 700;
        color: #FF1493;
        margin: 0.75rem 0;
    }

    .quantity-input {
        width: 60px;
        padding: 0.5rem;
        border: 2px solid #FFD700;
        border-radius: 6px;
        margin-right: 0.5rem;
    }

    .add-to-cart-btn {
        background: #FF1493;
        color: #FFFF00;
        padding: 0.6rem 1rem;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 600;
        width: 100%;
        transition: filter 0.3s;
    }

    .add-to-cart-btn:hover {
        filter: brightness(0.95);
    }

    .product-form {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    


    .btn-all{
    display:inline-block;
    padding:8px 18px;
    background:#FFD700;
    color:#333;
    border-radius:20px;
    text-decoration:none;
    font-weight:600;
    transition:all .3s;
}

.btn-all:hover{
    background:#FF1493;
    color:#fff;
}

/* Logout */
.logout-btn-customer {
    background: #FF1493;
    color: #fff;
    padding: 0.45rem 1.1rem;
    border-radius: 20px;
    text-decoration: none;
    font-weight: 600;
    display: inline-block;
    margin-left: 6px;
}

/* chatAdmin */
.btn-chat-admin{
    display:inline-block;
    margin-top:.6rem;
    margin-left:.4rem;
    background: linear-gradient(135deg, #FF69B4, #FF1493);
    color:#fff;
    padding:.45rem 1.4rem;
    border-radius:999px;
    text-decoration:none;
    font-size:.9rem;
    font-weight:bold;
    transition:.25s ease;
    box-shadow:0 4px 8px rgba(255,20,147,.35);
}

.btn-chat-admin:hover{
    transform:translateY(-2px);
    box-shadow:0 6px 14px rgba(255,20,147,.5);
    opacity:.95;
}

    </style>
</head>
<body>
    <div class="customer-header">
        <h1>🌹 Flowers Bouquet - Portal Pelanggan</h1>
        <div class="customer-header-right">
            <span>Selamat Datang, <?php echo htmlspecialchars($customer_name); ?>!</span>
            <a href="cart.php" class="cart-btn">
                🛒 Keranjang
                <?php if ($cart_count > 0): ?>
                <span class="cart-badge"><?php echo $cart_count; ?></span>
                <?php endif; ?>
            </a>
            <a href="order_history.php" class="cart-btn" style="background: #FF1493; color: #ffffffff;">Riwayat Pesanan</a>
            <a href="#" onclick="confirmLogout()" class="logout-btn-customer" >Logout</a>
        </div>
    </div>










<section class="hero-banner">
        <div class="banner-container">
            <img src="header.jpg" alt="Flowers Bouquet" class="banner-image" onerror="this.src='https://via.placeholder.com/1200x400/FF69B4/FFFFFF?text=Flowers+Bouquet'">
            <h2 class="banner-title">Flowers Bouquet</h2>
        </div>
    </section>




    <!-- Categories Section -->
    <section class="categories-section">
        <h2>Kategori Kami</h2>
        <div class="categories-grid">
            <div class="category-card" data-category="Bunga">
                <div class="category-icon">🌹</div>
                <h3>Bunga</h3>
                <p>Koleksi bunga segar terbaik untuk berbagai acara spesial Anda.</p>
            </div>
            <div class="category-card" data-category="Uang">
                <div class="category-icon">💰</div>
                <h3>Uang</h3>
                <p>Hadiah uang dalam kemasan eksklusif yang menarik.</p>
            </div>
            <div class="category-card" data-category="Boneka">
                <div class="category-icon">🧸</div>
                <h3>Boneka</h3>
                <p>Boneka lucu dan menggemaskan untuk orang tersayang.</p>
            </div>
            <div class="category-card" data-category="Makanan">
                <div class="category-icon">🍰</div>
                <h3>Makanan</h3>
                <p>Kue dan makanan lezat berkualitas premium.</p>
            </div>
        </div>


        <br> <br>
        <center><a href="https://wa.me/6281234567890?text=Halo%20Admin,%20saya%20ingin%20bertanya%20tentang%20pesanan%20<?= str_pad($order_id,6,'0',STR_PAD_LEFT) ?>"
        target="_blank"
        class="btn-chat-admin">
        💬 Chat Admin Untuk Membeli Produk Custom
        </a></center>
        <br><br>
    </section>



    
        




    

    

    <div class="container">
        <?php if ($cart_message): ?>
        <div class="success-message"><?php echo htmlspecialchars($cart_message); ?></div>
        <?php endif; ?>

 <h1 style="text-align: center; margin-bottom: 2rem;">
    <a href="index_customer.php" style="color:#FF1493; text-decoration:none;">
        Koleksi Produk Kami
    </a>
</h1>
<?php if (!empty($kategori)) : ?>
<div style="text-align:center; margin-bottom: 2rem;">
    <a href="index_customer.php#products" class="btn-all">
        🔄 Lihat Semua Kategori
    </a>
</div>
<?php endif; ?>


<?php if (count($products) > 0): ?>
    <div class="products-grid">
        <?php foreach ($products as $product): ?>
            <div class="product-card">
                <img
                    src="<?php echo htmlspecialchars($product['image']); ?>"
                    alt="<?php echo htmlspecialchars($product['name']); ?>"
                    class="product-image"
                    onerror="this.src='https://via.placeholder.com/250x200/FFB6D9/000000?text=Produk'">

                <div class="product-info">
                    <h3 class="product-name">
                        <?php echo htmlspecialchars($product['name']); ?>
                    </h3>

                    <span class="product-category">
                        <?php echo htmlspecialchars($product['category']); ?>
                    </span>

                    <p class="product-price">
                        Rp <?php echo number_format($product['price'], 0, ',', '.'); ?>
                    </p>

                    <form method="POST" class="product-form">
                        <input type="hidden" name="product_id"
                               value="<?php echo (int)$product['product_id']; ?>">

                        <input type="number" name="quantity"
                               value="1" min="1" class="quantity-input">

                        <button type="submit"
                                name="add_to_cart"
                                class="add-to-cart-btn">
                            Tambah ke Keranjang
                        </button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div style="text-align: center; padding: 3rem; color: #999;">
        <p>Tidak ada produk tersedia. Silakan kembali lagi nanti.</p>
    </div>
<?php endif; ?>

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

    <script>
document.querySelectorAll('.category-card').forEach(card => {
    card.addEventListener('click', () => {
        const kategori = card.dataset.category;
        window.location.href = `index_customer.php?kategori=${encodeURIComponent(kategori)}`;
    });
});

window.location.href = 
`index_customer.php?kategori=${encodeURIComponent(kategori)}#products`;

</script>



</body>
</html>
