<?php
session_start();
include 'config.php';

// Set error reporting
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$login_error = null;

// Handle login: only allow the specific admin credentials
// Username: Flowers
// Password: Bunga14@
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['username']) && isset($_POST['password'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    if ($username === 'Flowers' && $password === 'Bunga14@') {
        // Successful admin login
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;
    } else {
        // Deny access for any other credentials
        $login_error = "Anda bukan admin.";
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: berandaAdmin.php");
    exit();
}

// Fetch products
$products = [];
try {
    $sql = "SELECT * FROM products";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
    }
} catch (mysqli_sql_exception $e) {
    echo "Kesalahan saat mengambil produk: " . htmlspecialchars($e->getMessage());
}

// Fetch admin notifications (only if admin is logged in)
$notifications = [];
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in']) {
    try {
        // Count pending orders
        $sql_pending = "SELECT COUNT(*) as pending_count FROM orders WHERE status = 'pending'";
        $result_pending = $conn->query($sql_pending);
        $pending_orders = $result_pending ? $result_pending->fetch_assoc()['pending_count'] : 0;

        // Get low stock categories (stock < 5)
        $sql_stock = "SELECT category, COUNT(*) as low_stock_count FROM products WHERE stock < 5 GROUP BY category";
        $result_stock = $conn->query($sql_stock);
        $low_stock_categories = [];
        if ($result_stock) {
            while ($row = $result_stock->fetch_assoc()) {
                $low_stock_categories[] = $row;
            }
        }

        // Count new user registrations in last 7 days
        $sql_new_users = "SELECT COUNT(*) as new_users_count FROM customers WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $result_new_users = $conn->query($sql_new_users);
        $new_users = $result_new_users ? $result_new_users->fetch_assoc()['new_users_count'] : 0;

        $notifications = [
            'pending_orders' => $pending_orders,
            'low_stock_categories' => $low_stock_categories,
            'new_users' => $new_users
        ];
    } catch (mysqli_sql_exception $e) {
        // Handle error silently or log it
    }
    // Ensure notification keys exist with safe defaults to avoid undefined index/count errors
    if (!isset($notifications['pending_orders']) || !is_numeric($notifications['pending_orders'])) {
        $notifications['pending_orders'] = 0;
    }
    if (!isset($notifications['low_stock_categories']) || !is_array($notifications['low_stock_categories'])) {
        $notifications['low_stock_categories'] = [];
    }
    if (!isset($notifications['new_users']) || !is_numeric($notifications['new_users'])) {
        $notifications['new_users'] = 0;
    }
}

// Hardcoded sales data for graph display
$salesDataByCategory = [
    'Bunga' => 12,
    'Uang' => 8,
    'Boneka' => 15,
    'Makanan' => 6
];

// AJAX endpoint: return sales aggregates based on days & category
if (isset($_GET['action']) && $_GET['action'] === 'getSales') {
    // Expected params: days (int), category (string: all or specific)
    $days = isset($_GET['days']) ? (int)$_GET['days'] : 7;
    $category = isset($_GET['category']) ? $_GET['category'] : 'all';

    header('Content-Type: application/json');
    try {
        // Aggregate directly from orders -> products (current schema stores product_id in orders)
        // We'll sum o.quantity grouped by product category or product name depending on selection
        if ($category === 'all') {
            $sql = $sql = "SELECT p.category AS label, SUM(d.jumlah) AS total
        FROM detailproduk d
        JOIN products p ON d.product_id = p.product_id
        JOIN orders o ON d.id_orders = o.id_orders
        WHERE o.order_date >= DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY p.category";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $days);
        $stmt->execute();
        $res = $stmt->get_result();

           
            $labels = [];
            $data = [];
            while ($row = $res->fetch_assoc()) {
                $labels[] = $row['label'];
                $data[] = (int)$row['total'];
            }
            echo json_encode(['labels' => $labels, 'data' => $data]);
            exit();
        } else {
            // specific category -> return top products in that category
            $sql = "SELECT 
    p.category,
    SUM(d.jumlah) AS total_terjual
FROM detailproduk d
JOIN products p ON d.product_id = p.product_id
JOIN orders o ON d.id_orders = o.id_orders
WHERE o.order_date BETWEEN '2025-12-22' AND '2025-12-29' -- sesuaikan range
GROUP BY p.category
ORDER BY total_terjual DESC;

                    LIMIT 12";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('is', $days, $category);
            $stmt->execute();
            $res = $stmt->get_result();
            $labels = [];
            $data = [];
            while ($row = $res->fetch_assoc()) {
                $labels[] = $row['label'];
                $data[] = (int)$row['total'];
            }
            echo json_encode(['labels' => $labels, 'data' => $data]);
            exit();
        }
    } catch (Exception $e) {
        echo json_encode(['labels' => [], 'data' => []]);
        exit();
    }
}















?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flowers Bouquet - Website Admin</title>
    <link rel="stylesheet" href="style.css">
    <style>
    /* Make header login buttons elliptical, pink background and yellow text */
    .login-btn {
        background: #FF1493; /* vibrant pink */
        color: #FFFF00; /* bright yellow */
        padding: 0.35rem 0.65rem;
        border-radius: 999px; /* ellipse */
        text-decoration: none;
        font-weight: 600;
        display: inline-block;
        border: 2px solid rgba(0,0,0,0.06);
    }
    .login-btn:hover {
        filter: brightness(0.95);
        transform: translateY(-1px);
    }
    
    /* Position login buttons to the right */
    .login-container {
        display: flex;
        gap: 0.2rem;
        align-items: center;
    }

    /* Footer (pink & yellow theme) */
    footer.site-footer {
        background: linear-gradient(135deg,#FF69B4 0%, #FFD700 100%);
        color: #fff;
        padding: 1.25rem 1rem;
        margin-top: 2rem;
    }
    footer.site-footer p { margin: 0 0 0.5rem 0; color: rgba(255,255,255,0.95); }
    footer .footer-grid { display:flex; gap:1.5rem; align-items:flex-start; flex-wrap:wrap; }
    footer .footer-social h4, footer .footer-map h4 { margin:0 0 0.25rem 0; color:#fff; }
    footer a.footer-link { color: #fff; text-decoration: none; font-weight:700; }
    footer a.footer-link:hover { text-decoration: underline; opacity:0.95; }
    footer .map-card { width:100%; height:180px; border-radius:8px; overflow:hidden; border:2px solid rgba(255,255,255,0.2); }
    /* Footer three-column layout */
    footer .footer-col { flex: 1 1 220px; min-width: 180px; }
    footer .footer-left { max-width: 360px; }
    footer .footer-center { text-align: center; }
    footer .store-name { font-size:1.35rem; margin:0 0 0.35rem 0; font-weight:800; color:#fff; }
    footer .store-desc { margin:0 0 0.6rem 0; color: rgba(255,255,255,0.9); }
    /* vertical social links (stacked) */
    .social-row { display:flex; flex-direction:column; gap:0.5rem; justify-content:center; align-items:center; }
    .social-link { display:flex; gap:0.6rem; align-items:center; background: rgba(255,255,255,0.08); padding:0.5rem 0.8rem; border-radius:10px; color:#fff; text-decoration:none; width:220px; justify-content:flex-start; }
    .social-link svg { width:20px; height:20px; display:block; }
    .social-link span { font-weight:700; font-size:0.95rem; }

    /* Controls: range buttons and category select (theme: pink/yellow) */
    .controls { display:flex; gap:0.5rem; align-items:center; flex-wrap:wrap; }
    .range-group { display:flex; gap:0.5rem; align-items:center; }
    .range-btn {
        padding: 0.45rem 0.7rem;
        border-radius: 8px;
        border: 2px solid rgba(255,182,217,0.9); /* #FFB6D9 */
        background: #fff;
        color: #FF1493;
        font-weight:700;
        cursor: pointer;
        transition: all 180ms ease;
        box-shadow: none;
    }
    .range-btn:hover { transform: translateY(-2px); }
    .range-btn.active {
        background: linear-gradient(135deg,#FF1493 0%, #FF69B4 100%);
        color: #FFFF00;
        border-color: rgba(255,20,147,0.2);
        box-shadow: 0 8px 18px rgba(255,20,147,0.12);
    }

    /* Category select themed */
    .category-select {
        padding: 0.45rem 0.6rem;
        border-radius: 8px;
        border: 2px solid #FFB6D9;
        background: #fff;
        color: #333;
        font-weight:700;
    }



    /* ================================
   ADMIN NOTIFICATIONS - YELLOW GUI
================================ */

.admin-notifications {
    padding: 2rem;
    background: linear-gradient(135deg, #fdfae7ff, #fffbdbff);
    border-radius: 18px;
}

.admin-notifications h2 {
    font-size: 2.2rem;
    font-weight: 700;
    margin-bottom: 1.5rem;
    color: #b08900;
    text-align: center;
    
}


/* GRID */
.notifications-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 1.5rem;
}

/* CARD */
.notification-card {
    background: #ffffff;
    border-radius: 18px;
    padding: 1.5rem 1.4rem;
    box-shadow: 0 10px 25px rgba(255, 193, 7, 0.18);
    border: 2px solid #746201ff;
    text-align: center;
    transition: all 0.3s ease;
    position: relative;
}

.notification-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 18px 35px rgba(255, 193, 7, 0.28);
}

/* ICON */
.notification-icon {
    width: 64px;
    height: 64px;
    margin: 0 auto 0.8rem;
    background: linear-gradient(135deg, #ffd43b, #ffb703);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    box-shadow: 0 6px 15px rgba(255, 193, 7, 0.4);
}

/* TITLE */
.notification-card h3 {
    font-size: 1.1rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    color: #6c5700;
}

/* TEXT */
.notification-card p {
    font-size: 0.9rem;
    color: #555;
    line-height: 1.5;
    margin-bottom: 0.8rem;
}

.notification-card strong {
    color: #d18b00;
}

/* LIST STOK */
.notification-card ul {
    list-style: none;
    padding: 0;
    margin: 0.6rem 0 1rem;
}

.notification-card ul li {
    background: #fcfaa2ff;
    border: 1px solid #ffe58f;
    border-radius: 8px;
    padding: 0.45rem 0.6rem;
    font-size: 0.85rem;
    margin-bottom: 0.4rem;
    color: #6b5a00;
}

/* BUTTON */
.notification-btn {
    display: inline-block;
    margin-top: 0.5rem;
    padding: 0.55rem 1.2rem;
    background: linear-gradient(135deg, #ffd43b, #ffb703);
    color: #5a4300;
    font-weight: 600;
    font-size: 0.85rem;
    border-radius: 999px;
    text-decoration: none;
    box-shadow: 0 6px 15px rgba(255, 193, 7, 0.35);
    transition: all 0.25s ease;
}

.notification-btn:hover {
    background: linear-gradient(135deg, #ffb703, #f59f00);
    transform: scale(1.05);
    box-shadow: 0 10px 22px rgba(255, 193, 7, 0.45);
}

/* EMPTY STATE */
.notification-card p:last-child {
    margin-bottom: 0;
}

/* RESPONSIVE */
@media (max-width: 600px) {
    .admin-notifications {
        padding: 1.2rem;
    }
}








.btn-laporan{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:12px;

    padding:16px 400px;       /* 👉 INI yang bikin elips PANJANG */
    min-width:260px;         /* 👉 jamin tetap panjang walau teks pendek */

    background:linear-gradient(300deg, #FF69B4, #FFD700);
    color:#fff;

    font-size:18px;
    font-weight:700;

    border-radius:9999px;    /* 👉 kunci bentuk elips sempurna */
    text-decoration:none;

    box-shadow:0 12px 26px rgba(255,105,180,.35);
    transition:all .25s ease;
}

.btn-laporan:hover{
    background:linear-gradient(135deg, #FF85C1, #FFE066);
    transform:translateY(-4px);
    box-shadow:0 18px 36px rgba(255,182,193,.45);
}

.btn-laporan:active{
    transform:scale(.96);
}
.btn-laporan{
    display:inline-flex;
    margin:20px auto;   /* 👈 INI KUNCINYA */
}
.laporan-wrapper{
    text-align: center;
}

#logoutModal h3{
    text-align:center;
    color:#222;              /* hitam lembut */
    font-size:1.3rem;
    margin-bottom:0.5rem;
}
#logoutModal p{
    text-align:center;
    color:#333;
    font-size:0.95rem;
    line-height:1.5;
}






/* === Container Sales Notification === */
.sales-notification {
    display: flex;
    gap: 0.75rem; /* jarak antar item */
    justify-content: flex-start;
    flex-wrap: wrap; /* agar responsif jika sempit */
    margin-top: 1rem;
}

/* === Item Notification === */
.notification-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 1.2rem 0.35rem; /* kotak lebih kecil */
    border-radius: 8px;
    justify-content: flex-start;
    background-color: #4dd0e1; /* default biru untuk top */
    border: 1px solid #4dd0e1;
    font-family: Arial, sans-serif;
    font-size: 1.2rem; /* font lebih besar */
    color: #fff; /* teks putih */
    min-width: 140px; /* kotak lebih kecil */
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    text-align: left;
}

/* Hover effect */
.notification-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(0,0,0,0.15);
}

/* Icon */
.notification-icon {
    font-size: 1.6rem;
    
}

/* Top & Bottom variants */
.notification-top {
    background-color: #26c6da; /* biru lebih gelap */
    border-color: #26c6da;
}

.notification-bottom {
    background-color: #ff8a65; /* oranye lebih gelap */
    border-color: #ff8a65;
}

/* Strong text highlight */
.notification-item strong {
    color: #fff; /* tetap putih */
}

    </style>
</head>
<body>
    <header>
        <h1>
            <?php if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in']): ?>
                Selamat Datang Admin!
            <?php else: ?>
                Selamat Datang di Flowers Bouquet
            <?php endif; ?>
        </h1>
        <?php if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in']): ?>
            <a href="#" id="logoutBtn" onclick="confirmLogout()" class="logout-btn">Logout</a>
        <?php else: ?>
            <div class="login-container">
                <a href="login.php" class="login-btn">Login Admin</a>
                <a href="login_customer.php" class="login-btn">Login Pelanggan</a>
            </div>
        <?php endif; ?>
    </header>

    <!-- Hero Section with Banner Image -->
    <section class="hero-banner">
        <div class="banner-container">
            <img src="header.jpg" alt="Flowers Bouquet" class="banner-image" onerror="this.src='https://via.placeholder.com/1200x400/FF69B4/FFFFFF?text=Flowers+Bouquet'">
            <h2 class="banner-title">Flowers Bouquet</h2>
        </div>
    </section>

    <!-- Carousel/Slider Section with Sales Chart -->
    <section class="carousel-section">
        <div class="carousel-sales-wrapper">
            <!-- Left: Carousel Produk Unggulan -->
            <div class="carousel-left">
                <h2>Koleksi Produk</h2>
                <?php if (count($products) > 0): ?>
                <div class="carousel-container">
                    <button class="carousel-btn prev" onclick="prevSlide()">❮</button>
                    <div class="carousel-wrapper">
                        <div class="carousel">
                            <?php foreach ($products as $index => $product): ?>
                            <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                                <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" onerror="this.src='https://via.placeholder.com/300x300/FFB6D9/000000?text=Produk'">
                                <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                                <p>Rp <?php echo number_format($product['price'], 0, ',', '.'); ?></p>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <button class="carousel-btn next" onclick="nextSlide()">❯</button>
                </div>

                <div class="carousel-dots">
                <?php 
                    $maxDots = 3;
                    foreach ($products as $index => $product):
                    if ($index >= $maxDots) break;
                ?>
                <span class="dot <?php echo $index === 0 ? 'active' : ''; ?>" 
                onclick="currentSlide(<?php echo $index; ?>)">
                </span>
                <?php endforeach; ?>
                </div>

                <?php else: ?>
                <div style="padding: 3rem; text-align: center; color: #999;">
                    <p>Belum ada produk. Silakan login dan tambahkan produk terlebih dahulu.</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Right: Sales Chart -->




            <div class="carousel-right">
                <h2>Statistik Penjualan</h2>
                <div class="sales-chart-container">
                            <canvas id="salesChart"></canvas>
                </div>
                        
                <div class="sales-notification">
                    <div id="topSelling" class="notification-item notification-top">
                        
                        <span id="topSellingText"> 🏆 Terbanyak: <strong>-</strong></span>
                    </div>
                    <div id="bottomSelling" class="notification-item notification-bottom">
                        
                        <span id="bottomSellingText"> 📉 Paling Sedikit: <strong>-</strong></span>
                    </div>
                </div>
            </div>
    </section>

    <!-- Categories Section -->
    <section class="categories-section">
        <h2>Kategori Kami</h2>
        <div class="categories-grid">
            <div class="category-card">
                <div class="category-icon">🌹</div>
                <h3>Bunga</h3>
                <p>Koleksi bunga segar terbaik untuk berbagai acara spesial Anda.</p>
            </div>
            <div class="category-card">
                <div class="category-icon">💰</div>
                <h3>Uang</h3>
                <p>Hadiah uang dalam kemasan eksklusif yang menarik.</p>
            </div>
            <div class="category-card">
                <div class="category-icon">🧸</div>
                <h3>Boneka</h3>
                <p>Boneka lucu dan menggemaskan untuk orang tersayang.</p>
            </div>
            <div class="category-card">
                <div class="category-icon">🍰</div>
                <h3>Makanan</h3>
                <p>Kue dan makanan lezat berkualitas premium.</p>
            </div>
        </div>
    </section>

   <!-- Portals Section -->
<section style="padding: 2rem; display: flex; gap: 2rem; justify-content: center; flex-wrap: wrap; align-items: stretch;">
    <!-- Admin Portal -->
    <?php if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']): ?>
    <div style="background: linear-gradient(135deg, #FF69B4 0%, #FFD700 100%);
                padding: 2rem; border-radius: 12px; text-align: center;
                min-width: 300px; width: 320px; flex: 1 1 320px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
        <h2 style="color: #fff; margin-top: 0;">🔐 Portal Admin</h2>
        <p style="color: rgba(255,255,255,0.9);">Untuk mengelola produk dan pesanan, silakan login sebagai admin.</p>
        <a href="login.php" class="admin-login-btn"
           style="background: #fff; color: #FF1493; padding: 0.75rem 2rem; border-radius: 20px;
                  text-decoration: none; font-weight: 600; display: inline-block;">Login Admin</a>
    </div>
    <?php endif; ?>

    <?php if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']): ?>
    <!-- Customer Portal -->
    <?php if (!isset($_SESSION['customer_logged_in']) || !$_SESSION['customer_logged_in']): ?>
    <div style="background: linear-gradient(135deg, #FF69B4 0%, #FFD700 100%);
                padding: 2rem; border-radius: 12px; text-align: center;
                min-width: 300px; width: 320px; flex: 1 1 320px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
        <h2 style="color: #fff; margin-top: 0;">🛍️ Portal Pelanggan</h2>
        <p style="color: rgba(255,255,255,0.9);">Jelajahi koleksi produk kami dan lakukan pembelian dengan mudah.</p>
        <a href="login_customer.php"
           style="background: #fff; color: #FF1493; padding: 0.75rem 2rem; border-radius: 20px;
                  text-decoration: none; font-weight: 600; display: inline-block;">Login Pelanggan</a>
    </div>
    <?php else: ?>
    <div style="background: linear-gradient(135deg, #FF69B4 0%, #FFD700 100%);
                padding: 2rem; border-radius: 12px; text-align: center;
                min-width: 300px; width: 320px; flex: 1 1 320px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
        <h2 style="color: #fff; margin-top: 0;">🛍️ Portal Pelanggan</h2>
        <p style="color: rgba(255,255,255,0.9);">Selamat datang kembali, <?php echo htmlspecialchars($_SESSION['customer_name']); ?>!</p>
        <a href="index_customer.php"
           style="background: #fff; color: #FF1493; padding: 0.75rem 2rem; border-radius: 20px;
                  text-decoration: none; font-weight: 600; display: inline-block;">Lanjutkan Belanja</a>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</section>


    <!-- Login Modal (styled like login_customer) -->
    <div id="loginModal" style="display:none; position:fixed; z-index:10000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center;">
        <style>
            /* Scoped modal styles to match login_customer.php */
            #loginModal .login-container { background: white; padding: 2.5rem; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); width:100%; max-width:420px; animation: slideIn 0.45s ease; font-family: 'Arial', sans-serif; }
            @keyframes slideIn { from { opacity:0; transform: translateY(-12px);} to { opacity:1; transform: translateY(0);} }
            #loginModal .login-header { text-align:center; margin-bottom:1.25rem; }
            #loginModal .login-header h1 { color:#FF1493; font-size:1.6rem; margin:0; }
            #loginModal .form-group { margin-bottom:1rem; }
            #loginModal .form-group label { display:block; margin-bottom:0.5rem; font-weight:bold; color:#333; }
            #loginModal .form-group input { width:100%; padding:0.9rem; border:2px solid #FFB6D9; border-radius:8px; font-size:1rem; transition:all 0.25s ease; }
            #loginModal .form-group input:focus { outline:none; border-color:#FF1493; box-shadow:0 0 10px rgba(255,20,147,0.12); background:#FFF0F5; }
            #loginModal .error-message { background:#f8d7da; color:#721c24; padding:1rem; border-radius:8px; margin-bottom:1rem; border:1px solid #f5c6cb; }
            #loginModal .login-btn { width:100%; padding:0.95rem; background:linear-gradient(135deg,#FFC0CB 0%,#FF69B4 100%); color:#fff; border:none; border-radius:8px; font-size:1.05rem; font-weight:700; cursor:pointer; }
            #loginModal .cancel-btn { width:100%; padding:0.75rem; background:#FFD700; color:#000; border:none; border-radius:8px; font-weight:700; margin-top:0.75rem; cursor:pointer; }
            @media (max-width:480px){ #loginModal .login-container{ padding:1.5rem; } }
        </style>

        <div class="login-container" role="dialog" aria-modal="true" aria-labelledby="adminLoginTitle">
            <div class="login-header">
                <h1 id="adminLoginTitle">Flowers Bouquet</h1>
                <p style="color:#666; margin-top:6px;">Login Admin</p>
            </div>

            <?php if ($login_error): ?>
            <div class="error-message">
                <strong>Error!</strong> <?php echo htmlspecialchars($login_error); ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="" autocomplete="off" id="adminLoginForm">
                <div class="form-group">
                    <label for="username">Nama Pengguna:</label>
                    <input type="text" id="username" name="username" placeholder="Masukkan nama pengguna" required autofocus>
                </div>

                <div class="form-group">
                    <label for="password">Kata Sandi:</label>
                    <input type="password" id="password" name="password" placeholder="Masukkan kata sandi" required>
                </div>

                <button type="submit" class="login-btn">Login</button>
            </form>

            <button class="cancel-btn" onclick="closeLoginModal()">Batal</button>
        </div>

        <script>
            // Clear and anti-autofill helpers for admin modal
            function clearAdminLoginFields() {
                const u = document.getElementById('username');
                const p = document.getElementById('password');
                if (u) u.value = '';
                if (p) p.value = '';
            }

            // Validate form like customer login
            (function(){
                const form = document.getElementById('adminLoginForm');
                if (!form) return;
                form.addEventListener('submit', function(e){
                    const username = document.getElementById('username').value.trim();
                    const password = document.getElementById('password').value.trim();
                    if (username === ''){ e.preventDefault(); alert('❌ Nama pengguna tidak boleh kosong!'); document.getElementById('username').focus(); return false; }
                    if (password === ''){ e.preventDefault(); alert('❌ Kata sandi tidak boleh kosong!'); document.getElementById('password').focus(); return false; }
                    if (password.length < 3){ e.preventDefault(); alert('❌ Kata sandi minimal 3 karakter!'); document.getElementById('password').focus(); return false; }
                });
            })();

            // Clear aggressively to avoid browser autofill
            setTimeout(clearAdminLoginFields, 100);
            setTimeout(clearAdminLoginFields, 300);
            setTimeout(clearAdminLoginFields, 500);

            // Clear on focus to avoid autofill values
            document.addEventListener('focusin', function(e){
                if (e.target && (e.target.id === 'username' || e.target.id === 'password')){
                    setTimeout(function(){ if (e.target.value !== '') e.target.value = ''; }, 50);
                }
            });
        </script>
    </div>

   <!-- Admin Notifications Section -->
   <?php if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true): ?>
    <section id="admin-notifications" class="admin-notifications">
        <h2>Notifikasi Admin!</h2>
        <div class="notifications-grid">
            <div class="notification-card">
                <div class="notification-icon">📋</div>
                <h3>Pesanan Tertunda</h3>
                <p>Ada <strong><?php echo $notifications['pending_orders']; ?></strong> pesanan yang perlu disetujui.</p>
                <?php if ($notifications['pending_orders'] > 0): ?>
                <a href="manage_orders.php" class="notification-btn">Lihat Pesanan</a>
                <?php endif; ?>
            </div>
            <div class="notification-card">
                <div class="notification-icon">⚠️</div>
                <h3>Peringatan Stok</h3>
                <?php if (count($notifications['low_stock_categories']) > 0): ?>
                <p>Kategori dengan stok rendah:</p>
                <ul>
                    <?php foreach ($notifications['low_stock_categories'] as $category): ?>
                    <li><?php echo htmlspecialchars($category['category']); ?> (<?php echo $category['low_stock_count']; ?> produk)</li>
                    <?php endforeach; ?>
                </ul>
                <a href="manage_products.php" class="notification-btn">Lihat Stok</a>
                <?php else: ?>
                <p>Semua kategori memiliki stok yang cukup.</p>
                <?php endif; ?>
            </div>
            <div class="notification-card">
                <div class="notification-icon">👤</div>
                <h3>Pendaftaran Pengguna Baru</h3>
                <p>Ada <strong><?php echo $notifications['new_users']; ?></strong> pengguna baru yang mendaftar dalam 7 hari terakhir.</p>
                <?php if ($notifications['new_users'] > 0): ?>
                <a href="manage_customers.php" class="notification-btn">Lihat Pengguna</a>
                <?php endif; ?>
            </div>
        </div>
    </section>

<!-- Management Section (only for logged-in admins) -->
    
    <section id="management" class="management">
        <h2>Panel Manajemen Admin</h2>
        <div class="management-grid">
            <div class="manage-section">
                <h3>📦 Kelola Produk</h3>
                <p>Tambah, edit, atau hapus produk dari katalog.</p>
                <a href="manage_products.php" class="action-btn">Kelola Produk</a>
            </div>
            <div class="manage-section">
                <h3>📋 Kelola Pesanan</h3>
                <p>Lihat dan proses pesanan pelanggan.</p>
                <a href="manage_orders.php" class="action-btn">Kelola Pesanan</a>
            </div>
            <div class="manage-section">
                <h3>👥 Kelola Akun Pelanggan</h3>
                <p>Kelola akun dan informasi pelanggan yang telah registrasi.</p>
                <a href="manage_customers.php" class="action-btn">Kelola Akun Pelanggan</a>
            </div>
        </div>
        <br>
        <br>

    

        <div class="laporan-wrapper">
    <a href="laporan.php" class="btn-laporan">📊 Laporan</a>
</div>
 </section>




    



    
    <?php endif; ?>

    




    <!-- Footer -->
    <footer class="site-footer">
    

        <div class="footer-grid">
            <div class="footer-col footer-left">
                <h3 class="store-name">Flowers Bouquet</h3>
                <br>
                <p class="store-desc">toko rangkaian bunga,uang,makanan,dll untuk segala acara. Berlokasi di Buah Batu, Kota Bandung. Kami menyediakan layanan pengiriman cepat dan packaging khusus untuk momen istimewa Anda.</p>
            </div>

            <div class="footer-col footer-center">
                <h4>Ikuti Kami</h4>
                    <br>
                <div class="social-row" role="list">
                    <a class="social-link" href="https://instagram.com/flowers_bouquet" target="_blank" rel="noopener noreferrer" role="listitem" aria-label="Instagram Flowers Bouquet">
                        <!-- Instagram SVG -->
                        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5" stroke="#fff" stroke-width="1.6" fill="none"/><circle cx="12" cy="12" r="3.2" stroke="#fff" stroke-width="1.6" fill="none"/><circle cx="17.5" cy="6.5" r="0.8" fill="#fff"/></svg>
                        <span>@flowers_bouquet</span>
                    </a>

                    <a class="social-link" href="https://facebook.com/flowersbouquet" target="_blank" rel="noopener noreferrer" role="listitem" aria-label="Facebook Flowers Bouquet">
                        <!-- Facebook SVG -->
                        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M15 3h3v4h-3v14h-4V7H9V3h3V1.8C12 0.8 12.6 0 14.6 0H17v3h-2c-.6 0-1 .4-1 1V7h3l-1 3h-2v10h-4V10H6V7h3V4c0-2 1.5-4 4-4h2v3z" fill="#fff"/></svg>
                        <span>Flowers Bouquet</span>
                    </a>

                    <a class="social-link" href="https://twitter.com/FlowersBQ" target="_blank" rel="noopener noreferrer" role="listitem" aria-label="Twitter Flowers Bouquet">
                        <!-- Twitter SVG -->
                        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M22 5.92c-.63.28-1.3.47-2 .56.72-.43 1.27-1.1 1.53-1.9-.68.4-1.44.7-2.25.86C18.5 4.6 17.6 4 16.5 4c-1.6 0-2.9 1.3-2.9 2.9 0 .23.03.45.08.66C10.3 7.38 7 5.5 4.7 2.8c-.25.43-.4.93-.4 1.46 0 1.01.51 1.9 1.3 2.42-.6-.02-1.17-.18-1.66-.46v.05c0 1.4.99 2.56 2.3 2.83-.48.13-.98.14-1.48.05.42 1.3 1.6 2.24 3.02 2.27C8 13.5 6.9 13.9 5.7 13.9c-.36 0-.71-.02-1.06-.07C5.3 15 6.9 16 8.7 16.04c-1.44 1.12-3.25 1.78-5.22 1.78-.34 0-.68-.02-1.01-.06C2.9 19.12 5.1 20 7.5 20c9 0 13.9-7.66 13.9-14.3 0-.22 0-.43-.02-.64.96-.7 1.8-1.57 2.46-2.56-.86.39-1.77.65-2.72.77z" fill="#fff"/></svg>
                        <span>@FlowersBQ</span>
                    </a>
                </div>
            </div>

            <div class="footer-col footer-right">
                <h4>Lokasi</h4>
                <p style="margin:0 0 0.5rem 0;">Buah Batu, Kota Bandung</p>
                <div class="map-card">
                    <iframe src="https://www.google.com/maps?q=Buah%20Batu%20Bandung&output=embed" width="100%" height="100%" frameborder="0" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                </div>
            </div>
        </div>

        <p>&copy; 2025 Flowers Bouquet. Semua hak dilindungi.</p>

    <!-- Chart.js Library -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="script.js"></script>
    <script>
        // Dynamic sales chart: fetch from server endpoint and update chart
        let salesChart = null;

        async function fetchSales(days = 7, category = 'all') {
            try {
                const resp = await fetch(`?action=getSales&days=${encodeURIComponent(days)}&category=${encodeURIComponent(category)}`);
                if (!resp.ok) return { labels: [], data: [] };
                return await resp.json();
            } catch (e) {
                return { labels: [], data: [] };
            }
        }

        function getColorPalette(n) {
            const base = ['#FF69B4', '#FFD700', '#FF1493', '#FFFF00', '#FFA07A', '#8A2BE2', '#00CED1', '#FF7F50'];
            const colors = [];
            for (let i=0;i<n;i++) colors.push(base[i % base.length]);
            return colors;
        }

        function buildChart(labels, data) {
            const ctx = document.getElementById('salesChart').getContext('2d');
            const colors = getColorPalette(labels.length);
            if (salesChart) salesChart.destroy();
            salesChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Jumlah Penjualan',
                        data: data,
                        backgroundColor: colors,
                        borderColor: colors,
                        borderWidth: 2,
                        borderRadius: 8
                    }]
                },
                options: {
                    indexAxis: 'x',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, suggestedMax: Math.max(...data, 10) + 5 },
                        x: { grid: { display: false } }
                    }
                }
            });
            updateNotificationsFromData(labels, data);
        }

        function updateNotificationsFromData(labels, data) {
            if (!labels || labels.length === 0) {
                document.getElementById('topSellingText').innerHTML = `Terbanyak: <strong>-</strong>`;
                document.getElementById('bottomSellingText').innerHTML = `Paling Sedikit: <strong>-</strong>`;
                return;
            }
            let maxIdx = 0, minIdx = 0;
            for (let i=0;i<data.length;i++){
                if (data[i] > data[maxIdx]) maxIdx = i;
                if (data[i] < data[minIdx]) minIdx = i;
            }
            document.getElementById('topSellingText').innerHTML = `Terbanyak: <strong>${labels[maxIdx]}</strong> (${data[maxIdx]} unit)`;
            document.getElementById('bottomSellingText').innerHTML = `Paling Sedikit: <strong>${labels[minIdx]}</strong> (${data[minIdx]} unit)`;
        }

        // Wire controls
        async function refreshChart(days, category) {
            const payload = await fetchSales(days, category);
            buildChart(payload.labels || [], payload.data || []);
        }

        document.addEventListener('DOMContentLoaded', function(){
            // initial load: 7 days, all categories
            const defaultDays = 7;
            const defaultCategory = 'all';
            refreshChart(defaultDays, defaultCategory);

            // range buttons: use .active class for visuals and behavior
            // set default active (7 days)
            const defaultBtn = document.querySelector('.range-btn[data-days="7"]');
            if (defaultBtn) {
                defaultBtn.classList.add('active');
                defaultBtn.setAttribute('aria-pressed', 'true');
            }

            document.querySelectorAll('.range-btn').forEach(btn => {
                btn.addEventListener('click', function(){
                    const days = this.getAttribute('data-days');
                    // visual active toggle
                    document.querySelectorAll('.range-btn').forEach(b=>{ b.classList.remove('active'); b.setAttribute('aria-pressed','false'); });
                    this.classList.add('active');
                    this.setAttribute('aria-pressed','true');
                    const category = document.getElementById('categorySelect').value;
                    refreshChart(days, category);
                });
            });

            // category select
            const cat = document.getElementById('categorySelect');
            cat.addEventListener('change', function(){
                // find currently active range button (or default 7)
                const active = document.querySelector('.range-btn.active');
                const days = active ? active.getAttribute('data-days') : 7;
                refreshChart(days, this.value);
            });
        });
    </script>
        <!-- Logout confirmation modal (custom so we can use 'Ya' / 'Tidak') -->
        <style>
        /* Simple modal styles scoped to this page */
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
    #logoutModal .btn-tidak:hover { filter:brightness(0.95); }
        </style>

        <div id="logoutModal" aria-hidden="true">
            <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="logoutTitle">
                <h3 id="logoutTitle">Konfirmasi Logout</h3>
                <p>Apakah yakin ingin LOGOUT? </p>
                <div class="modal-actions">
                    <button class="btn-ya" id="logoutYes">Ya</button>
                    <button class="btn-tidak" id="logoutNo">Tidak</button>
                </div>
            </div>
        </div>

        <script>
        // Show modal instead of native confirm so we can have custom labels
        function confirmLogout() {
            const modal = document.getElementById('logoutModal');
            modal.style.display = 'flex';
            modal.setAttribute('aria-hidden', 'false');
        }

        // Wire buttons
        (function () {
            const modal = document.getElementById('logoutModal');
            const yes = document.getElementById('logoutYes');
            const no = document.getElementById('logoutNo');
            if (!modal) return;
            yes && yes.addEventListener('click', function () {
                modal.style.display = 'none';
                window.location.href = 'logout.php';
            });
            no && no.addEventListener('click', function () {
                modal.style.display = 'none';
                // kembali ke tampilan manajemen
                window.location.href = 'berandaAdmin.php';
            });

            // close modal when clicking outside card
            modal.addEventListener('click', function (e) {
                if (e.target === modal) {
                    modal.style.display = 'none';
                }
            });
        })();

        // Login modal functions
        function showLoginModal() {
            const modal = document.getElementById('loginModal');
            modal.style.display = 'flex';
            modal.setAttribute('aria-hidden', 'false');
        }

        function closeLoginModal() {
            const modal = document.getElementById('loginModal');
            modal.style.display = 'none';
            modal.setAttribute('aria-hidden', 'true');
        }

        // Close modal when clicking outside
        document.getElementById('loginModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeLoginModal();
            }
        });
        </script>




















<script>
        function updateNotificationsFromData(labels, data) {
            if (!labels || labels.length === 0) {
                document.getElementById('topSellingText').innerHTML = 'Terbanyak: <strong>-</strong>';
                document.getElementById('bottomSellingText').innerHTML = 'Paling Sedikit: <strong>-</strong>';
                return;
            }
            let max = Math.max(...data);
            let min = Math.min(...data);
            let maxIndex = data.indexOf(max);
            let minIndex = data.indexOf(min);
            document.getElementById('topSellingText').innerHTML = `Terbanyak: <strong>${labels[maxIndex]} (${max})</strong>`;
            document.getElementById('bottomSellingText').innerHTML = `Paling Sedikit: <strong>${labels[minIndex]} (${min})</strong>`;
        }

        // Initial load
        async function initChart() {
            const rangeButtons = document.querySelectorAll('.range-btn');
            const categorySelect = document.getElementById('categorySelect');

            async function refreshChart() {
                const days = document.querySelector('.range-btn.active')?.dataset.days || 7;
                const category = categorySelect.value;
                const { labels, data } = await fetchSales(days, category);
                buildChart(labels, data);
            }

            rangeButtons.forEach(btn => {
                btn.addEventListener('click', () => {
                    rangeButtons.forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                    refreshChart();
                });
            });

            categorySelect.addEventListener('change', refreshChart);

            // Set default 7 hari active
            const defaultBtn = document.querySelector('.range-btn[data-days="7"]');
            if (defaultBtn) defaultBtn.classList.add('active');

            refreshChart();
        }

        initChart();

</script>

</body>
</html>
