<?php
session_start();
include 'config.php';

if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header("Location: berandaAdmin.php");
    exit();
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $price = $_POST['price'] ?? 0;
    $category = $_POST['category'] ?? 'Bunga';
    $stock = (int)($_POST['stock'] ?? 0);
    $product_id = $_POST['product_id'] ?? null;

    $image_path = '';
    if (!empty($_FILES['image']['name'])) {
        if (!is_dir("uploads")) mkdir("uploads");
        $image_path = "uploads/" . time() . "_" . basename($_FILES['image']['name']);
        move_uploaded_file($_FILES['image']['tmp_name'], $image_path);
    }

    // ===== TAMBAH PRODUK =====
    if ($action === 'add') {
        $stmt = $conn->prepare(
            "INSERT INTO products (name, description, price, image, category, stock)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("ssdssi", $name, $description, $price, $image_path, $category, $stock);
        $stmt->execute();
        $message = "Produk berhasil disimpan";
    }

    // ===== EDIT PRODUK =====
    if ($action === 'edit' && $product_id) {
        if ($image_path) {
            $stmt = $conn->prepare(
                "UPDATE products 
                 SET name=?, description=?, price=?, image=?, category=?, stock=?
                 WHERE product_id=?"
            );
            $stmt->bind_param(
                "ssdssii",
                $name,
                $description,
                $price,
                $image_path,
                $category,
                $stock,
                $product_id
            );
        } else {
            // 🔥 PERBAIKAN DI SINI (category STRING)
            $stmt = $conn->prepare(
                "UPDATE products 
                 SET name=?, description=?, price=?, category=?, stock=?
                 WHERE product_id=?"
            );
            $stmt->bind_param(
                "ssdssi",
                $name,
                $description,
                $price,
                $category,
                $stock,
                $product_id
            );
        }
        $stmt->execute();
        $message = "Produk berhasil diperbarui";
    }

    // ===== HAPUS PRODUK =====
    if ($action === 'delete' && $product_id) {
        $stmt = $conn->prepare("DELETE FROM products WHERE product_id=?");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $message = "Produk berhasil dihapus";
    }
}

// ===== AMBIL DATA =====
$products = [];
$res = $conn->query("SELECT * FROM products ORDER BY product_id DESC");
while ($r = $res->fetch_assoc()) $products[] = $r;

$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $conn->prepare("SELECT * FROM products WHERE product_id=?");
    $stmt->bind_param("i", $_GET['edit']);
    $stmt->execute();
    $edit = $stmt->get_result()->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Kelola Produk</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
*{box-sizing:border-box;font-family:Arial,sans-serif}
body{margin:0;background:#FFF5EE;font-size:17px}

header{
    background: linear-gradient(135deg, #FF1493, #FFD700);;
    padding:13px 30px;
    display:flex;
    justify-content:space-between;
    align-items:center
}
header h1{color: #ffffffff;font-size:33px}


.back-btn{
    background: #FF1493;
    color: #ffffffff;
    padding:12px 26px;
    border-radius:22px;
    font-weight:bold;
    text-decoration:none;
    font-size:16px
}

.container{max-width:1250px;margin:36px auto}

.box{
    background: #fff;
    padding:28px;
    border-radius:14px;
    margin-bottom:32px
}

.success{
    background: #d4edda;
    color: #155724;
    padding:16px;
    border-radius:6px;
    margin-bottom:24px;
    font-weight:bold;
    text-align:center;
    font-size:17px
}

h2{color:#FF1493;margin-bottom:20px;font-size:24px}

label{font-weight:bold}

input,textarea,select{
    width:100%;
    padding:13px;
    border:2px solid #FF69B4;
    border-radius:6px;
    margin-bottom:18px;
    font-size:17px
}

button{
    background:#FF69B4;
    color:#fff;
    border:none;
    padding:12px 30px;
    border-radius:8px;
    font-weight:bold;
    font-size:17px;
    cursor:pointer
}

table{
    width:100%;
    border-collapse:collapse;
    font-size:17px
}
th{
    background:#FF69B4;
    color:#fff;
    padding:16px;
    text-align:center;
    white-space:nowrap
}
td{
    padding:16px;
    border-bottom:1px solid #FFD6E7;
    vertical-align:middle
}

.t-left{text-align:left}
.t-center{text-align:center}
.t-right{text-align:right}

.desc{
    max-width:280px;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis
}

img{
    width:95px;
    height:95px;
    object-fit:cover;
    border-radius:10px
}

.action{
    display:flex;
    gap:10px;
    justify-content:center
}
.btn-edit,.btn-del{
    width:82px;
    padding:9px 0;
    border-radius:7px;
    font-size:15px;
    font-weight:bold;
    text-align:center
}
.btn-edit{background:#007BFF;color:#fff;text-decoration:none}
.btn-del{background:#FF6B6B;color:#fff;border:none}


/* ===== EFEK KLIK & HOVER WARNA KUNING ===== */
button:hover,
button:active,
.back-btn:hover,
.back-btn:active,
.btn-edit:hover,
.btn-edit:active,
.btn-del:hover,
.btn-del:active {
    background: #FFFF00 !important;
    color: #FF1493 !important;
    transition: 0.2s;
}

/* khusus tombol hapus agar tetap kontras */
.btn-del:hover,
.btn-del:active {
    color: #000 !important;
}

/* ===== BODY ===== */
body {
    margin: 0;
    font-family: Arial, sans-serif;
    background-color: #fff0c5ff; /* background body */
}
</style>
</head>
<body>

<header>
<h1>Kelola Produk</h1>
<a href="berandaAdmin.php" class="back-btn">Kembali</a>
</header>

<div class="container">

<?php if($message): ?>
<div class="success"><?= $message ?></div>
<?php endif; ?>

<div class="box">
<h2><?= $edit ? 'Edit Produk' : 'Tambah Produk Baru' ?></h2>

<form method="post" enctype="multipart/form-data">
<input type="hidden" name="action" value="<?= $edit?'edit':'add' ?>">
<?php if($edit): ?>
<input type="hidden" name="product_id" value="<?= $edit['product_id'] ?>">
<?php endif; ?>

<label>Nama Produk</label>
<input name="name" required value="<?= $edit['name']??'' ?>">

<label>Kategori</label>
<select name="category">
<option <?=($edit['category']??'')=='Bunga'?'selected':''?>>Bunga</option>
<option <?=($edit['category']??'')=='Uang'?'selected':''?>>Uang</option>
<option <?=($edit['category']??'')=='Boneka'?'selected':''?>>Boneka</option>
<option <?=($edit['category']??'')=='Makanan'?'selected':''?>>Makanan</option>
</select>

<label>Deskripsi</label>
<textarea name="description"><?= $edit['description']??'' ?></textarea>

<label>Harga</label>
<input type="number" name="price" value="<?= $edit['price']??'' ?>">

<label>Stok</label>
<input type="number" name="stock" min="0" value="<?= $edit['stock']??0 ?>" required>

<label>Foto Produk</label>
<input type="file" name="image">

<button>Simpan</button>
</form>
</div>

<div class="box">
<h2>Daftar Produk (<?= count($products) ?>)</h2>

<table>
<tr>
    <th>Foto</th>
    <th>Nama</th>
    <th>Kategori</th>
    <th>Deskripsi</th>
    <th>Harga</th>
    <th>Stok</th>
    <th>Aksi</th>
</tr>

<?php foreach($products as $p): ?>
<tr>
    <td class="t-center"><img src="<?= $p['image'] ?>"></td>
    <td class="t-left"><?= $p['name'] ?></td>
    <td class="t-center"><?= $p['category'] ?></td>
    <td class="t-left desc"><?= $p['description'] ?></td>
    <td class="t-right">Rp <?= number_format($p['price'],0,',','.') ?></td>
    <td class="t-center"><?= $p['stock'] ?></td>
    <td class="t-center">
        <div class="action">
            <a href="?edit=<?= $p['product_id'] ?>" class="btn-edit">Edit</a>
            <form method="post">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="product_id" value="<?= $p['product_id'] ?>">
                <button class="btn-del">Hapus</button>
            </form>
        </div>
    </td>
</tr>
<?php endforeach; ?>
</table>

</div>
</div>
</body>
</html>
