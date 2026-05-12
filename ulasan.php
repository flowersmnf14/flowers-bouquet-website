<?php
session_start();
include 'config.php';

// ============================
// CEK ROLE
// ============================
$isAdmin    = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
$isCustomer = isset($_SESSION['customer_logged_in']) && $_SESSION['customer_logged_in'] === true;

if (!$isAdmin && !$isCustomer) {
    header("Location: login.php");
    exit();
}

// ============================
// URL KEMBALI (1 TOMBOL)
// ============================
$back_url = $isAdmin ? 'berandaAdmin.php' : 'order_history.php';

// ============================
// SIMPAN BALASAN ADMIN
// ============================
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ulasan_id'])) {

    $ulasan_id = intval($_POST['ulasan_id']);
    $reply     = trim($_POST['admin_reply']);

    if ($reply !== '') {
        $stmt = $conn->prepare("
            UPDATE ulasan 
            SET admin_reply = ?, admin_reply_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param("si", $reply, $ulasan_id);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: ulasan.php");
    exit();
}

// ============================
// AMBIL DATA ULASAN
// ============================
$result = $conn->query("
    SELECT 
        u.id,
        u.rating,
        u.review,
        u.foto,
        u.video,
        u.admin_reply,
        u.admin_reply_at,
        u.created_at,
        o.id_orders,
        c.name AS customer_name
    FROM ulasan u
    JOIN orders o ON u.order_id = o.id_orders
    JOIN customers c ON u.customer_id = c.customer_id
    ORDER BY u.created_at DESC
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Ulasan Pelanggan</title>

<style>
body{
    font-family: Arial, sans-serif;
    background:#fff0c5;
    margin:0;
}

/* ===== HEADER ===== */
header{
    background:linear-gradient(135deg, #FF1493, #FFD700);
    padding:16px 30px;
}
.header-inner{
    width:100%;                 /* full layar */
    display:flex;
    align-items:center;
    justify-content:space-between;
}

.header-title{
    color:#fff;
    font-size:27px;
    font-weight:bold;
}
.btn-back{
    background:#FF69B4;
    color:#fff;
    padding:8px 22px;
    border-radius:999px;
    text-decoration:none;
    font-weight:bold;
    transition:.25s;
}
.btn-back:hover{
    opacity:.9;
    transform:translateY(-1px);
}

/* ===== CONTENT ===== */
.container{
    max-width:1100px;
    margin:auto;
    padding:2rem;
}

.review-card{
    background:#fff;
    border-radius:14px;
    padding:1.3rem 1.6rem;
    margin-bottom:1.4rem;
    box-shadow:0 4px 12px rgba(0,0,0,.1);
}
.review-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
}
.rating{
    color:#FFD700;
    font-size:1.4rem;
}
.review-text{
    margin:.8rem 0;
}
.review-media img,
.review-media video{
    max-width:160px;
    border-radius:8px;
    margin-right:.5rem;
    margin-top:.3rem;
}
small{ color:#777; }

.admin-reply{
    background:#fceeeeff;
    padding:.8rem;
    border-left:4px solid #FF1493;
    border-radius:8px;
    margin-top:.7rem;
}

.reply-form textarea{
    width:100%;
    border-radius:8px;
    padding:.6rem;
    border:1px solid #ddd;
    resize:vertical;
}
.reply-form button{
    margin-top:.4rem;
    background:#FF1493;
    color:#fff;
    border:none;
    padding:.4rem 1.3rem;
    border-radius:999px;
    cursor:pointer;
}
</style>
</head>

<body>

<!-- ===== HEADER ===== -->
<header>
    <div class="header-inner">
        <div class="header-title">⭐ Ulasan Pelanggan</div>
        <a href="<?= $back_url ?>" class="btn-back">← Kembali</a>
    </div>
</header>

<!-- ===== CONTENT ===== -->
<div class="container">

<?php if ($result->num_rows == 0): ?>
    <p>Belum ada ulasan.</p>
<?php endif; ?>

<?php while($r = $result->fetch_assoc()): ?>
<div class="review-card">

    <div class="review-header">
        <div>
            <strong><?= htmlspecialchars($r['customer_name']) ?></strong><br>
            <small>Pesanan #<?= str_pad($r['id_orders'],6,'0',STR_PAD_LEFT) ?></small>
        </div>
        <div class="rating">
            <?= str_repeat('★', (int)$r['rating']) ?>
        </div>
    </div>

    <div class="review-text">
        <?= nl2br(htmlspecialchars($r['review'])) ?>
    </div>

    <div class="review-media">
        <?php if($r['foto']): ?>
            <img src="<?= htmlspecialchars($r['foto']) ?>">
        <?php endif; ?>

        <?php if($r['video']): ?>
            <video src="<?= htmlspecialchars($r['video']) ?>" controls></video>
        <?php endif; ?>
    </div>

    <small><?= date('d M Y H:i', strtotime($r['created_at'])) ?></small>

    <hr>

    <!-- ADMIN SAJA BISA BALAS -->
    <?php if($isAdmin): ?>
        <?php if(!empty($r['admin_reply'])): ?>
            <div class="admin-reply">
                <strong>Balasan Admin:</strong><br>
                <?= nl2br(htmlspecialchars($r['admin_reply'])) ?><br>
                <small><?= date('d M Y H:i', strtotime($r['admin_reply_at'])) ?></small>
            </div>
        <?php else: ?>
            <form method="post" class="reply-form">
                <input type="hidden" name="ulasan_id" value="<?= $r['id'] ?>">
                <textarea name="admin_reply" placeholder="Tulis balasan admin..." required></textarea>
                <button type="submit">Kirim Balasan</button>
            </form>
        <?php endif; ?>
    <?php elseif(!empty($r['admin_reply'])): ?>
        <div class="admin-reply">
            <strong>Balasan Admin:</strong><br>
            <?= nl2br(htmlspecialchars($r['admin_reply'])) ?>
        </div>
    <?php endif; ?>

</div>
<?php endwhile; ?>

</div>
</body>
</html>
