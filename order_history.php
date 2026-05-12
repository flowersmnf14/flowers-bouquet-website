<?php
session_start();
include 'config.php';

if (!isset($_SESSION['customer_logged_in']) || !$_SESSION['customer_logged_in']) {
    header("Location: login_customer.php");
    exit();
}

$customer_id = $_SESSION['customer_id'];

$grouped_orders = [];

$stmt = $conn->prepare("
    SELECT
        o.id_orders,o.order_date,o.status,o.total,o.alamat,o.nomor_hp,
        p.name AS product_name,p.image,
        d.jumlah
    FROM orders o
    JOIN detailproduk d ON o.id_orders = d.id_orders
    JOIN products p ON d.product_id = p.product_id
    WHERE o.customer_id = ?
    ORDER BY o.order_date DESC
");
$stmt->bind_param("i",$customer_id);
$stmt->execute();
$res = $stmt->get_result();

while($r=$res->fetch_assoc()){
    $oid=$r['id_orders'];
    if(!isset($grouped_orders[$oid])){
        $grouped_orders[$oid]=[
            'order_date'=>$r['order_date'],
            'status'=>$r['status'],
            'total'=>$r['total'],
            'alamat'=>$r['alamat'],
            'nomor_hp'=>$r['nomor_hp'],
            'items'=>[]
        ];
    }
    $grouped_orders[$oid]['items'][]=[
        'name'=>$r['product_name'],
        'image'=>$r['image'],
        'jumlah'=>$r['jumlah']
    ];
}

function statusColor($s){
    return [
        'pending'=>'#FF9800',
        'prepared'=>'#FF69B4',
        'shipping'=>'#2196F3',
        'shipped'=>'#2196F3',
        'completed'=>'#4CAF50'
    ][$s] ?? '#999';
}
function statusLabel($s){
    return [
        'pending'=>'Menunggu Konfirmasi',
        'prepared'=>'Sedang Diproses',
        'shipping'=>'Sedang Dikirim',
        'shipped'=>'Sedang Dikirim',
        'completed'=>'Selesai'
    ][$s] ?? $s;
}


?>
<?php
// ============================
// Simpan ulasan jika form dikirim
// ============================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'])) {

    $customer_id = $_SESSION['customer_id'];
    $order_id    = intval($_POST['order_id']);
    $rating      = isset($_POST['rating']) ? intval($_POST['rating']) : null;
    $review      = $_POST['ulasan'] ?? '';

    // Upload foto
    $foto = '';
    if (!empty($_FILES['foto']['name'])) {
        $foto = 'uploads/' . time() . '_' . basename($_FILES['foto']['name']);
        move_uploaded_file($_FILES['foto']['tmp_name'], $foto);
    }

    // Upload video
    $video = '';
    if (!empty($_FILES['video']['name'])) {
        $video = 'uploads/' . time() . '_' . basename($_FILES['video']['name']);
        move_uploaded_file($_FILES['video']['tmp_name'], $video);
    }

    // Validasi rating 1-5
    if ($rating === null || $rating < 1 || $rating > 5) {
        echo "<script>alert('Silakan pilih rating 1-5.'); window.history.back();</script>";
        exit();
    }

    // Simpan ke tabel ulasan
    $stmtInsert = $conn->prepare("
        INSERT INTO ulasan (order_id, customer_id, rating, review, foto, video)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmtInsert->bind_param("iiisss", $order_id, $customer_id, $rating, $review, $foto, $video);
    $stmtInsert->execute();
    $stmtInsert->close();

    echo "<script>alert('Ulasan berhasil dikirim!'); window.location='order_history.php';</script>";
    exit();
}


?>



<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Riwayat Pesanan</title>
<style>
body{font-family:Arial;margin:0;background:#fff}
.container{max-width:1100px;margin:auto;padding:2rem 1rem}
.order-card{
    border:2px solid #FFB6D9;
    border-radius:12px;
    padding:1.5rem;
    margin-bottom:1.5rem;
}
.order-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
}
.status-badge{
    padding:.5rem 1rem;
    border-radius:20px;
    color:#fff;
    font-weight:bold;
}
.product-info{
    display:flex;
    gap:1rem;
    margin:.75rem 0;
}
.product-image{
    width:80px;height:80px;border-radius:8px;object-fit:cover;
}
.total-price{
    font-weight:bold;color:#FF1493;margin-top:.5rem;
}

/* ===== ULASAN ===== */
.review-box{
    margin-top:1rem;
    padding:1rem;
    background: #ffdae9ff;
    border-radius:16px;
}
.review-top{
    display:flex;
    justify-content:space-between;
    align-items:center;
}
.review-stars{
    display:flex;
    gap:.3rem;
}
.review-stars input{display:none}
.review-stars label{
    font-size:1.6rem;
    color: #ccc;
    cursor:pointer;
}
.review-stars input:checked ~ label,
.review-stars label:hover,
.review-stars label:hover ~ label{
    color:#FFD700;
}

/* ICON UPLOAD */
.review-media{
    display:flex;
    gap:.75rem;
    margin:.5rem 0;
}
.media-btn{
    background:#FF69B4;
    color:#fff;
    border-radius:999px;
    padding:.5rem .9rem;
    font-size:.9rem;
    cursor:pointer;
    display:flex;
    align-items:center;
    gap:.3rem;
}
.media-btn input{display:none}

textarea{
    width:100%;
    border-radius:10px;
    padding:.6rem;
    border:1px solid #ddd;
}
button{
    margin-top:.5rem;
    background:#FF69B4;
    color:#fff;
    border:none;
    padding:.5rem 1.5rem;
    border-radius:999px;
    cursor:pointer;
}






.review-top{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:.5rem;
}

.review-title{
    font-size:1.2rem;          /* tulisan ulasan diperbesar */
    font-weight:bold;
}

.review-stars{
    display:flex;
    gap:.3rem;
}

/* sembunyikan radio */
.review-stars input{
    display:none;
}

/* bintang default */
.review-stars label{
    font-size:1.8rem;
    color:#ccc;
    cursor:pointer;
    transition:.2s;
}

/* hover: warnai dari kiri */
.review-stars label:hover,
.review-stars label:hover ~ label{
    color:#FFD700;
}

/* checked: warnai dari kiri */
.review-stars input:checked ~ label{
    color:#FFD700;
}

/* BALIK URUTAN VISUAL SAJA (tanpa merusak logika) */
.review-stars{
    flex-direction:row-reverse;
}




header {
    background: linear-gradient(135deg, #FF1493, #FFD700);
    padding: 17px 30px;
}

.customer-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.customer-header h2 {
    color: #ffffff;
    font-size: 24px;
    margin: 0;
}

.header-links a {
    color: #ffffff;
    text-decoration: none;
    margin-left: 1rem; /* jarak antar link */
    font-weight: 500;
}

.header-links a:hover {
    text-decoration: underline;
}


body {
    margin: 0;
    padding: 0;
    background-color: #fff0c5 !important; /* pakai !important untuk override */
    font-family: Arial, sans-serif;
}


.container {
    padding: 20px;
    background: transparent; /* biar body pink terlihat */
}

.order-card {
    background: #fceeeeff; /* pink lembut, bisa berbeda tapi harmonis */
    border-radius: 12px;
    padding: 15px;
    margin-bottom: 20px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.08);
}
.order-card textarea {
    width: 100%;           /* maksimal selebar card */
    max-width: 100%;       /* jangan melebihi container */
    padding: 8px 12px;     /* beri jarak dalam */
    border-radius: 8px;    /* sudut melengkung */
    border: 1px solid #ddd; /* garis tipis */
    font-size: 14px;
    resize: vertical;      /* hanya bisa diubah tinggi, tidak melebar */
    box-sizing: border-box; /* supaya padding tidak menambah lebar */
}


.customer-header a{
    background-color: #ff1493; /* pink */
    color: #fff;
    padding: 14px 22px;
    border-radius: 999px; /* oval */
    text-decoration: none;
    font-size: 14px;
    display: inline-block;
    transition: 0.3s ease;
}









.logout-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    display: none;
    justify-content: center;
    align-items: center;
    z-index: 1000;
}

.logout-modal {
    background: #fff;
    width: 400px;          /* lebar modal */
    max-width: 50%; 
    padding: 2rem;
    border-radius: 10px;
    text-align: center;
    box-shadow: 0 4px 10px rgba(0,0,0,0.2);
}

.logout-actions {
    margin-top: 1rem;
    display: flex;
    justify-content: center; /* tombol rapat di tengah */
    gap: 0.3rem;            /* jarak tombol kecil */
}

.logout-actions button {
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 4px;     /* bikin kotak */
    cursor: pointer;
    font-weight: bold;
}

.btn-logout-yes {
    background: #FF1493;
    color: #fff;
}
.btn-logout-no {
    background: #ffc107;
    color: #000;
}

.review-stars{
    display:flex;
    flex-direction:row-reverse; /* tetap biar hover dari kiri ke kanan */
    gap:.3rem;
}

/* ulasan? */
.btn-lihat-ulasan{
    display:inline-block;
    margin-top:.6rem;
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

.btn-lihat-ulasan:hover{
    transform:translateY(-2px);
    box-shadow:0 6px 14px rgba(255,20,147,.5);
    opacity:.95;
}

</style>
</head>

<body>

<header>
    <div class="customer-header">
    <h2>📦 Riwayat Pesanan</h2>
    <div>
        <a href="index_customer.php" style="color:#fff;text-decoration:none;margin-right:1rem">
            ← Belanja Lagi</a>
 <a href="#" id="btnLogout" style="color:#fff;text-decoration:none">
    Logout
</a>


</div>
</header>

<div class="container">

<?php foreach($grouped_orders as $order_id=>$o): ?>
<div class="order-card">

<div class="order-header">
    <div>
        <strong>Pesanan #<?= str_pad($order_id,6,'0',STR_PAD_LEFT) ?></strong><br>
        <small><?= date('d M Y H:i',strtotime($o['order_date'])) ?></small>
    </div>
    <div class="status-badge" style="background:<?= statusColor($o['status']) ?>">
        <?= statusLabel($o['status']) ?>
    </div>
</div>

<?php foreach($o['items'] as $it): ?>
<div class="product-info">
    <img class="product-image" src="<?= htmlspecialchars($it['image']) ?>">
    <div>
        <strong><?= htmlspecialchars($it['name']) ?></strong><br>
        Jumlah: <?= $it['jumlah'] ?>
    </div>
</div>
<?php endforeach; ?>

<div class="total-price">Total: Rp <?= number_format($o['total'],0,',','.') ?></div>

<div style="margin-top:.5rem;font-size:.9rem">
    <strong>Alamat:</strong> <?= htmlspecialchars($o['alamat']) ?><br>
    <strong>No HP:</strong> <?= htmlspecialchars($o['nomor_hp']) ?>
</div>

<?php if($o['status']==='completed'): ?>
<div class="review-box">


<form method="post" enctype="multipart/form-data">
    <input type="hidden" name="order_id" value="<?= $order_id ?>">
    
    <a href="ulasan.php?order_id=<?= $order_id ?>"
    class="btn-lihat-ulasan">
    Lihat Semua Ulasan
    </a>

    <!-- Rating -->
    <div class="review-stars">
<?php for ($i = 5; $i >= 1; $i--): ?>
    <input type="radio" id="s<?= $i ?>-<?= $order_id ?>" name="rating" value="<?= $i ?>">
    <label for="s<?= $i ?>-<?= $order_id ?>">★</label>
<?php endfor; ?>
</div>


    <!-- Ulasan teks -->
    <textarea name="ulasan" placeholder="Tulis ulasan kamu..."></textarea>

    <!-- Upload foto & video -->
    <label>📷 Foto <input type="file" name="foto" accept="image/*"></label>
    <label>🎥 Video <input type="file" name="video" accept="video/*"></label>

    <button type="submit">Kirim Ulasan</button>
</form>



</div>
<?php endif; ?>

</div>
<?php endforeach; ?>

</div>
<div class="logout-overlay" id="logoutModal" style="display:none;">
    <div class="logout-modal">
        <h3>Konfirmasi Logout</h3>
        <p>Apakah yakin ingin LOGOUT?</p>

        <div class="logout-actions">
            <button class="btn-logout-yes" onclick="window.location='logout_customer.php'">Ya</button>
            <button class="btn-logout-no" onclick="closeLogout()">Tidak</button>
        </div>
    </div>
</div>

<script>
function closeLogout() {
    document.getElementById('logoutModal').style.display = 'none';
}

document.getElementById('btnLogout').onclick = function (e) {
    e.preventDefault();
    document.getElementById('logoutModal').style.display = 'flex';
};
</script>

</body>
</html>
