<?php
session_start();
include 'config.php';

// =================== INISIALISASI ===================
$error = ''; 
$customer_id = $_SESSION['customer_id'] ?? null;
$customer_name = $_SESSION['customer_name'] ?? null;

// Redirect jika customer belum login
if (!isset($_SESSION['customer_logged_in']) || !$_SESSION['customer_logged_in']) {
    header("Location: login_customer.php");
    exit();
}

// Ambil data telepon dan status customer
$customer_phone = '';
$customer_status = 'aktif';
try {
    $stmtPhone = $conn->prepare("SELECT phone, COALESCE(`status`, 'aktif') as status FROM customers WHERE customer_id = ? LIMIT 1");
    $stmtPhone->bind_param('i', $customer_id);
    $stmtPhone->execute();
    $resPhone = $stmtPhone->get_result();
    if ($resPhone && $resPhone->num_rows > 0) {
        $row = $resPhone->fetch_assoc();
        $customer_phone = $row['phone'] ?? '';
        $customer_status = $row['status'] ?? 'aktif';
    }
    $stmtPhone->close();
} catch (Exception $e) {
    $customer_phone = '';
    $customer_status = 'aktif';
}

// Jika akun diblokir
if (strtolower($customer_status) !== 'aktif') {
    unset($_SESSION['customer_logged_in'], $_SESSION['customer_id'], $_SESSION['customer_name']);
    header('Location: login_customer.php?blocked=1');
    exit();
}

// Cek cart kosong
if (!isset($_SESSION['cart']) || count($_SESSION['cart']) == 0) {
    header("Location: cart.php");
    exit();
}

// Load alamat tersimpan
$saved_addresses = [];
try {
    $stmtAddr = $conn->prepare("SELECT id_alamat, nama_penerima, nomor_hp, alamat, kecamatan, kota, provinsi, negara, is_default FROM alamat_user WHERE customer_id = ? ORDER BY is_default DESC, id_alamat DESC");
    $stmtAddr->bind_param('i', $customer_id);
    $stmtAddr->execute();
    $resAddr = $stmtAddr->get_result();
    while ($row = $resAddr->fetch_assoc()) {
        $saved_addresses[] = $row;
    }
    $stmtAddr->close();
} catch (Exception $_) {}

// Pilih alamat default / terpilih
$selected_address = null;
try {
    $stmt = $conn->prepare("SELECT id_alamat, nama_penerima, nomor_hp, alamat, kecamatan, kota, provinsi, negara, is_default FROM alamat_user WHERE customer_id = ? ORDER BY is_default DESC LIMIT 1");
    $stmt->bind_param('i', $customer_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
        $selected_address = $res->fetch_assoc();
    }
    $stmt->close();
} catch (Exception $e) {}

// Hitung total & ambil detail produk
$total_price = 0;
$cart_items = [];
foreach ($_SESSION['cart'] as $product_id => $jumlah) {
    $stmt = $conn->prepare("SELECT * FROM products WHERE product_id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $product = $result->fetch_assoc();
        $subtotal = $product['price'] * $jumlah;
        $total_price += $subtotal;

        $cart_items[] = [
            'product'  => $product,
            'jumlah'   => $jumlah,
            'subtotal' => $subtotal
        ];
    }
    $stmt->close();
}

// =================== PROSES CHECKOUT ===================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {

    $phone = trim($_POST['phone'] ?? '');
    $selected_alamat_id = (int)($_POST['selected_alamat_id'] ?? 0);

    // Ambil alamat
    if ($selected_alamat_id > 0) {
        $stmtAddr = $conn->prepare("
            SELECT CONCAT_WS(', ', alamat, kecamatan, kota, provinsi, negara) AS full_addr
            FROM alamat_user
            WHERE id_alamat = ? AND customer_id = ?
            LIMIT 1
        ");
        $stmtAddr->bind_param('ii', $selected_alamat_id, $customer_id);
        $stmtAddr->execute();
        $resAddr = $stmtAddr->get_result();
        $address = $resAddr->num_rows ? trim($resAddr->fetch_assoc()['full_addr']) : '';
        $stmtAddr->close();
    } else {
        $address = trim($_POST['address'] ?? '');
    }

    $shipping_method = $_POST['shipping_method'] ?? 'cod_kurir';
    $payment_method  = $_POST['payment_method'] ?? 'bank_transfer';
    $delivery_date   = $_POST['delivery_date'] ?? '';
    $delivery_slot   = $_POST['delivery_slot'] ?? '';

    if (empty($phone) || empty($address) || empty($delivery_date) || empty($delivery_slot)) {
        $error = "Telepon, alamat, tanggal, dan jam pengiriman wajib diisi.";
    } else {
        try {
            $conn->begin_transaction();

            // =================== INSERT ORDERS ===================
            $stmtOrder = $conn->prepare("
            INSERT INTO orders (customer_id, total, status, order_date, alamat, nomor_hp)
            VALUES (?, ?, 'pending', NOW(), ?, ?)
            ");
            $stmtOrder->bind_param('idss', $customer_id, $total_price, $address, $phone);
            $stmtOrder->execute();
            $order_id = $stmtOrder->insert_id;
            $stmtOrder->close();

            
            // =================== INSERT DETAIL PRODUK & UPDATE STOCK ===================
            foreach ($cart_items as $item) {

                $product_id = (int)$item['product']['product_id'];
                $jumlah     = (int)$item['jumlah'];
                $subtotal   = $item['subtotal'];

                // Lock stock
                $stmtLock = $conn->prepare("SELECT stock FROM products WHERE product_id = ? FOR UPDATE");
                $stmtLock->bind_param('i', $product_id);
                $stmtLock->execute();
                $resLock = $stmtLock->get_result();
                $stock = $resLock->num_rows ? (int)$resLock->fetch_assoc()['stock'] : 0;
                $stmtLock->close();

                if ($stock < $jumlah) {
                    throw new Exception("Stok produk tidak mencukupi: " . htmlspecialchars($item['product']['name']));
                }

                // Insert detailproduk
                $stmtDetail = $conn->prepare("
                    INSERT INTO detailproduk (id_orders, product_id, jumlah, subtotal)
                    VALUES (?, ?, ?, ?)
                ");
                $stmtDetail->bind_param('iiid', $order_id, $product_id, $jumlah, $subtotal);
                $stmtDetail->execute();
                $stmtDetail->close();

                // Update stock
                $stmtUpd = $conn->prepare("UPDATE products SET stock = stock - ? WHERE product_id = ?");
                $stmtUpd->bind_param('ii', $jumlah, $product_id);
                $stmtUpd->execute();
                $stmtUpd->close();
            }

            // =================== UPLOAD BUKTI TRANSFER ===================
      
            $namaFile = null;

            if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/uploads/bukti/'; // pastikan folder ini ada dan writable
            if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
            }

            $originalName = $_FILES['payment_proof']['name'];
            $ext = pathinfo($originalName, PATHINFO_EXTENSION);
            $namaFile = 'bukti_' . time() . '.' . $ext;

            $tmpFile = $_FILES['payment_proof']['tmp_name'];
            if (!move_uploaded_file($tmpFile, $uploadDir . $namaFile)) {
            throw new Exception("Gagal meng-upload file bukti transfer.");
            }
            }



            // =================== INSERT PEMBAYARAN ===================
            $stmtPay = $conn->prepare("
                INSERT INTO pembayaran (id_orders, metode_bayar, buktiTransfer, tgl_bayar, statusBayar)
                VALUES (?, ?, ?, NOW(), 'menunggu')
            ");
            $stmtPay->bind_param('iss', $order_id, $payment_method, $namaFile);
            $stmtPay->execute();
            $stmtPay->close();

            // =================== COMMIT ===================
            $conn->commit();
            $_SESSION['cart'] = [];

            // =================== INSERT NOTIFIKASI OTOMATIS ===================
            $notifPesan = "Pesanan #$order_id sedang diproses.";
            $stmtNotif = $conn->prepare("
                INSERT INTO notifikasi (pesan, tgl_kirim, statusBaca)
                VALUES (?, NOW(), 0)
            ");
            $stmtNotif->bind_param('s', $notifPesan);
            $stmtNotif->execute();
            $stmtNotif->close();

            header("Location: order_history.php?id=" . $order_id);
            exit();

        } catch (Exception $e) {
            $conn->rollback();
            $error = "Checkout gagal: " . htmlspecialchars($e->getMessage());
        }
    }
}


?>


  

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Flowers Bouquet</title>
    <link rel="stylesheet" href="style.css">

    <style>
.customer-header {
    background: linear-gradient(135deg, #FF69B4 0%, #FFD700 100%);
    padding: 0.8rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    color: #fff;
}

.container {
    max-width: 1200px;
    margin: auto;
    padding: 2rem 1rem;
}

/* === GRID UTAMA === */
/* .checkout-wrapper {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 2rem;
} */



/* === KOLOM === */
.col-left,
.col-center {
    background: #fff;
    padding: 2.3rem;
    border-radius: 10px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    /* display: flex; */
    flex-direction: column;
}

/* tombol tetap di bawah */
/* .col-center .submit-btn {
    margin-top: auto;
} */

/* === SUMMARY KANAN === */
/* .order-summary {
    background: #fff;
    padding: 1.5rem;
    border-radius: 10px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    grid-column: 2;
    height: fit-content;
} */

/* === FORM === */
.form-group {
    margin-bottom: 1.2rem;
}

/* .label-options {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
    margin-top: 8px;
} */

.label-option {
    /* position: relative; */
    /* border: 1.5px solid #ffd1e6; */
    /* border-radius: 8px; */
    /* padding: 8px 6px; */
    /* cursor: pointer; */

    font-size: 0.8rem; 
    /* display: flex; */
    /* flex-direction: column; */
   
    /* gap: 4px; */
    /* background: #fff; */
}

/* .label-option svg {
    width: 18px;
    height: 18px;
} */

.label-option.selected {
    border-color: #FF1493;
    background: #fff0f7;
}
.label-option input[type="radio"] {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}
.label-option {
    cursor: pointer;
}



.form-group input,
.form-group textarea,
.form-group select {
    width: 100%;
    padding: .7rem;
    border-radius: 6px;
    border: 1.8px solid #FFB6D9;
}

/* === BUTTON === */
.submit-btn {
    background: #FF1493;
    color: #fff;
    border: none;
    padding: 1rem;
    border-radius: 8px;
    font-size: 1.05rem;
    font-weight: 700;
    cursor: pointer;
}

/* === ORDER ITEM === */
.order-item {
    display: flex;
    justify-content: space-between;
    padding: .6rem 0;
    border-bottom: 1px solid #f3c4da;
}

/* === RESPONSIVE === */
@media (max-width: 1024px) {
    .checkout-wrapper {
        grid-template-columns: 1fr;
    }
    .checkout-form {
        grid-template-columns: 1fr;
    }
}


.form-group.pengiriman .label-options {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
}

.form-group.pengiriman .label-option {
    border: 1.5px solid #ffd1e6;
    border-radius: 8px;
    padding: 10px 6px;
    text-align: center;
    font-size: 0.8rem;
    cursor: pointer;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    background: #fff;
}

.form-group.pengiriman .label-option svg {
    width: 20px;
    height: 20px;
}

.form-group.pengiriman input[type="radio"] {
    display: none;
}

.form-group.pengiriman .label-option.selected {
    border-color: #FF1493;
    background: #fff0f7;
}


/* Samakan semua judul section */
.form-group > label,
.section-title {
    font-weight: 700;
    font-size: 1.05rem;
    color: #222;
    margin-bottom: 12px;
    display: block;
}

.submit-btn {
    width: 100%;
    text-align: center;
}


.form-group.pembayaran .label-options {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
}

.form-group.pembayaran .label-option {
    border: 1.5px solid #ffd1e6;
    border-radius: 8px;
    padding: 10px 6px;
    text-align: center;
    font-size: 0.8rem;
    cursor: pointer;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    background: #fff;
}

.form-group.pembayaran .label-option svg {
    width: 20px;
    height: 20px;
}

.form-group.pembayaran input[type="radio"] {
    display: none;
}

.form-group.pembayaran .label-option.selected {
    border-color: #FF1493;
    background: #fff0f7;
}


.label-options {
    display: grid;
    gap: 10px;
}

.form-group.alamat .label-options { grid-template-columns: repeat(3, 1fr); }
.form-group.pengiriman .label-options { grid-template-columns: repeat(2, 1fr); }
.form-group.pembayaran .label-options { grid-template-columns: repeat(2, 1fr); }

.label-option {
    border: 1.5px solid #ffd1e6;
    border-radius: 8px;
    padding: 10px;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    cursor: pointer;
    background: #fff;
}

.label-option.selected {
    border-color: #FF1493;
    background: #fff0f7;
}

.label-option input[type="radio"] {
    display: none;
}

.label-option svg { width: 20px; height: 20px; }


/* ===== INFORMASI PEMBAYARAN ===== */
#paymentInfo {
  background: linear-gradient(135deg, #fff0f7, #fff8e7);
  border: 1px solid #ffd1e8;
  border-radius: 14px;
  padding: 16px;
  font-size: 14px;
  color: #444;
  animation: fadeUp 0.35s ease;
}

/* BANK & EWALLET BOX */
#bankInfo,
#ewalletInfo,
#simpleInfo {
  background: #ffffff;
  border-radius: 12px;
  padding: 14px;
  box-shadow: 0 6px 14px rgba(0,0,0,0.06);
}

/* JARAK ANTAR ELEMEN */
#bankInfo p,
#ewalletInfo p,
#simpleInfo p {
  margin: 6px 0;
}

/* JUDUL */
/* #bankInfo p:first-child,
#ewalletInfo p:first-child {
  font-weight: 600;
  color: #ff1493;
} */

/* NOMOR REKENING / EWALLET */
#bankInfo strong,
#ewalletInfo strong {
  display: inline-block;
  margin: 6px 0;
  font-size: 18px;
  color: #222;
  letter-spacing: 1px;
}

/* QRIS */
#bankInfo img {
  display: block;
  margin: 10px auto 0;
  border-radius: 12px;
  border: 1px dashed #ffb6d9;
  padding: 8px;
  background: #fff;
}

/* INFO SEDERHANA */
#simpleInfo {
  text-align: center;
  color: #666;
}

/* ===== UPLOAD BUKTI ===== */
#uploadProof {
  background: #fff;
  border-radius: 12px;
  padding: 12px;
  border: 2px dashed #ffc0cb;
  animation: fadeUp 0.35s ease;
}

#uploadProof input[type="file"] {
  width: 100%;
  cursor: pointer;
  font-size: 13px;
}

/* ===== ANIMASI ===== */
@keyframes fadeUp {
  from {
    opacity: 0;
    transform: translateY(8px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}


.label-options{
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 12px;
}

/* .label-option{
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px;
    border: 1.5px solid #f3c1d9;
    border-radius: 12px;
    cursor: pointer;
    transition: all .2s ease;
    background: #fff;
} */

/* .label-option svg{
    width: 26px;
    height: 26px;
} */

.label-option input{
    display: none;
}

.label-option:hover{
    border-color: #ff69b4;
    background: #fff0f7;
}

.label-option input:checked + svg{
    stroke: #ff1493;
}

.label-option input:checked ~ .label-text{
    font-weight: 600;
}

.label-option:has(input:checked){
    border-color: #ff1493;
    background: linear-gradient(135deg, #fff0f7, #fff8fb);
}



.label-option {
    display: flex;
    align-items: center;
    gap: 8px;
    border: 1.5px solid #f3c1d9;
    border-radius: 12px;
    padding: 8px 12px;
    cursor: pointer;
    transition: all 0.2s;
}

.label-option input {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
}

.label-option:has(input:checked) {
    border-color: #ff1493;
    background: #fff0f7;
}

.label-icon {
    display: flex;
    align-items: center;
}



/* Jika dipilih */
.label-option:has(input:checked) {
    border-color: #ff1493; /* border pink */
    background: linear-gradient(135deg, #fff0f7, #fff8fb); /* background pink muda */
}

/* Teks bold dan pink */
.label-option:has(input:checked) .label-text {
    font-weight: 600;
    color: #FF1493;
}

/* Icon stroke pink */
.label-option:has(input:checked) svg path,
.label-option:has(input:checked) svg rect,
.label-option:has(input:checked) svg circle {
    stroke: #FF1493;
}
/* Container label-options */
.form-group.pembayaran .label-options {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
}

/* Kotak label */
.form-group.pembayaran .label-option {
    border: 1.5px solid #ffd1e6;
    border-radius: 8px;
    padding: 12px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    cursor: pointer;
    background: #fff;
    transition: all 0.2s ease;
}

/* Ikon di dalam label */
.form-group.pembayaran .label-option svg {
    width: 20px;
    height: 20px;
}

/* Input radio disembunyikan */
.form-group.pembayaran .label-option input[type="radio"] {
    display: none;
}

/* Efek saat label dipilih */
.form-group.pembayaran .label-option.selected,
.form-group.pembayaran .label-option:has(input:checked) {
    border-color: #FF1493;
    background: #fff0f7; /* pink muda */
}

/* Teks label dipertebal saat dipilih */
.form-group.pembayaran .label-option:has(input:checked) .label-text {
    font-weight: 600;
}

.customer-header a {
    background-color: #ff1493; /* pink */
    color: #fff;
    padding: 11px 22px;
    border-radius: 999px; /* oval */
    text-decoration: none;
    font-size: 14px;
    display: inline-block;
    transition: 0.3s ease;
}




</style>
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
    
    
    

  /* Container semua card */
.checkout-container {
    display: flex;
    gap: 2.5rem;
    justify-content: center; /* card seimbang di tengah */
    align-items: flex-start;
    flex-wrap: nowrap;
}

/* Semua card individual */
.checkout-card {
    flex: 0 0 33.33%;       /* semua card sama lebar 1/3 container */
    max-width: 400px;
    min-width: 300px;
    min-height: 700px;      /* tinggi semua card sama */
    background: #fff;
    border-radius: 16px;
    padding: 2rem;
    box-shadow: 0 6px 18px rgba(0,0,0,0.08);
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
    transition: none !important; /* hilangkan transisi */
}

/* Semua isi card tetap tidak mempengaruhi ukuran */
.checkout-card * {
    box-sizing: border-box;
    transition: none !important; /* hilangkan perubahan ukuran saat klik/fokus */
}

/* Card tidak bergerak saat aktif/fokus */
.checkout-card:active,
.checkout-card:focus-within,
.checkout-card:hover {
    transform: none !important;
}

/* Label / pilihan di dalam card tetap statis */
.label-option {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    gap: 0.5rem;
    padding: 0.8rem 1rem;
    border: 1px solid #FF1493;
    border-radius: 8px;
    cursor: pointer;
     /* hanya background berubah, bukan ukuran */
}

/* Jangan ubah padding / margin saat dipilih */
.label-option input:checked + svg + .label-text {
    /* hanya bisa ubah warna teks / icon, bukan ukuran card */
    color: #FF1493;
}

/* Responsive untuk layar kecil */
@media (max-width: 1024px) {
    .checkout-container {
        flex-direction: column;
        gap: 2rem;
        justify-content: center;
    }
    .checkout-card {
        flex: 1 1 auto;
        max-width: 100%;
        min-height: auto;
        padding: 1.5rem;
    }
}


/* Samakan style input tanggal dengan select jam pengiriman */
#deliveryDate,
#deliverySlot {
    width: 100%;
    padding: 0.6rem 1rem;           /* kotak lebih pendek dan konsisten */
    border: 1.5px solid #FFB6D9;
    border-radius: 8px;
    background: #fff;
    font-size: 1rem;
    color: #333;
    outline: none;
    box-sizing: border-box;
    -webkit-appearance: none;       /* hilangkan tampilan default browser */
    -moz-appearance: none;
    appearance: none;
}

/* Fokus / hover */
#deliveryDate:focus,
#deliverySlot:focus {
    border-color: #FF1493;
    box-shadow: 0 0 0 2px rgba(255,20,147,0.15);
}

/* Tambahkan ikon kalender (opsional) agar lebih mirip select biasa) */
#deliveryDate::-webkit-calendar-picker-indicator {
    filter: invert(35%) sepia(100%) saturate(500%) hue-rotate(320deg); 
    cursor: pointer;
}

/* Responsive */
@media (max-width: 1024px) {
    #deliveryDate,
    #deliverySlot {
        padding: 0.5rem 0.9rem;
        font-size: 0.95rem;
    }
}





    /* CARD PAYMENT INFO: tetap diam */
#paymentInfo {
    position: relative; /* konten tetap di flow card */
    min-height: 120px;  /* sesuaikan dengan konten maksimum */
}

/* Semua konten info pembayaran disembunyikan tapi tetap ada */
.payment-detail {
    position: absolute; /* tidak mempengaruhi tinggi card */
    top: 0;
    left: 0;
    width: 100%;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.3s ease;
}

/* Aktifkan konten yang dipilih */
.payment-detail.active {
    opacity: 1;
    visibility: visible;
    position: relative; /* agar terlihat */
}

    </style>

</head>
<body>
    <div class="customer-header">
        <h1>💳 Checkout</h1>
            <div>
                <div class="header-links">
                <a href="cart.php" style="color: #fff; text-decoration: none; margin-right: 1rem;">← Kembali</a>
                <a href="#" onclick="confirmLogout()" style="color: #fff; text-decoration: none;">Logout</a>
                </div>
            </div>
    </div>


    <div class="container">

        <div class="checkout-wrapper">

            <!-- Form spans left+center columns -->
            <form method="POST" id="checkoutForm" class="checkout-form" enctype="multipart/form-data">
                <!-- LEFT column: identity & address selection -->

                <div class="checkout-container">

                    <div class="checkout-card">

                <!-- <div class="col-left"> -->
                        <h2>Data Pengiriman</h2>
                        <?php if ($error): ?>
                            <div class="error-message"><?php echo htmlspecialchars($error); ?>
                            </div>
                            <?php endif; ?>

                            <div class="form-group">
                                <label>Nama Lengkap</label>
                                <input type="text" value="<?php echo htmlspecialchars($customer_name); ?>" disabled style="background: #f5f5f5;">
                            </div>

                            <div class="form-group">
                                <label>Nomor Telepon</label>
                                <input type="tel" name="phone" required placeholder="Contoh: 08123456789" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : htmlspecialchars($customer_phone); ?>">
                            </div>

                            <!-- Recipient and bouquet note -->
                             <div class="form-group">
                                <label>Nama Penerima</label>
                                <input type="text" name="recipient_name">
                                <small style="color:#666;display:block">*kosongkan untuk menggunakan nama akun (<?php echo htmlspecialchars($customer_name); ?>)</small>
                            </div>

                            <div class="form-group">
                                <label>Catatan</label>
                                <textarea name="bouquet_note" placeholder="Contoh: Selamat ulang tahun, Nura! Wish you all the best."></textarea>
                            </div>



                        <h3 style="margin-top:0;">Alamat</h3>
                            <div class="form-group alamat">
                                <label>Label Alamat</label>
                                    <div class="label-options">
                                        <div class="label-option" data-value="Rumah" role="button" tabindex="0">
                                            <input type="radio" name="label" value="Rumah" checked>
                                            <svg viewBox="0 0 24 24" fill="none"><path d="M3 10.5L12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1V10.5z" stroke="#FF1493" stroke-width="1.2"/></svg>
                                            <span class="label-text">Rumah</span>
                                        </div>
                            
                                        <div class="label-option" data-value="Kantor" role="button" tabindex="0">
                                            <input type="radio" name="label" value="Kantor">
                                            <svg viewBox="0 0 24 24" fill="none"><rect x="3" y="4" width="14" height="16" rx="2" stroke="#FF1493" stroke-width="1.2"/><path d="M7 8h2M7 12h2M11 8h2M11 12h2" stroke="#FF1493" stroke-width="1.2"/></svg>
                                            <span class="label-text">Kantor</span>
                                        </div>

                                        <div class="label-option" data-value="Sekolah/Universitas" role="button" tabindex="0">
                                            <input type="radio" name="label" value="Sekolah/Universitas">
                                            <svg viewBox="0 0 24 24" fill="none"><path d="M12 2l9 5-9 5-9-5 9-5z" stroke="#FF1493" stroke-width="1.2"/><path d="M3 9v6l9 5 9-5V9" stroke="#FF1493" stroke-width="1.2"/></svg>
                                            <span class="label-text">Sekolah/Universitas</span>
                                        </div>
                                </div>
                            </div>
                        



<!-- Alamat -->
<?php if (!empty($selected_address)): ?>
    <div class="alamat-terpilih" style="border:1px solid #ffd1e6;padding:0.75rem;border-radius:6px;background:#fff7fb">
        <label>Alamat Terpilih</label>
            <div style="padding:0.75rem; background:#fff; border-radius:6px; border:1px solid #ffdcec; display:flex; flex-direction:column; gap:6px;">
                <div style="font-weight:500;">
                <?php echo htmlspecialchars($selected_address['alamat']); ?>
                </div>

                <div style="color:#333;font-size:14px;">
                <?php echo htmlspecialchars(implode(', ', array_filter([
                    $selected_address['kecamatan'],
                    $selected_address['kota'],
                    $selected_address['provinsi'],
                    $selected_address['negara']
                ]))); ?>
                </div>
            </div>
        <input type="hidden" name="selected_alamat_id" value="<?php echo (int)$selected_address['id_alamat']; ?>">
    </div>
<?php endif; ?>


<!-- Tombol Pilih Alamat -->
<div class="form-group" style="margin-top:1rem;">
    <button type="button" 
            onclick="window.location.href='alamat.php?return=checkout'" 
            style="padding:0.6rem 1.2rem; background:linear-gradient(90deg,#FF1493,#FFD700); border:none; border-radius:6px; color:#fff; font-weight:600; cursor:pointer;">
        Pilih / Kelola Alamat
    </button>
</div>
</div>

                        <div class="col-center">
                     <label><h3>Tanggal Pengiriman</h3></label>
                           <!-- <div class="checkout-card"> -->
                    <div class="form-group">
                        <input type="date" name="delivery_date" id="deliveryDate" required min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label>Jam Pengiriman</label>
                        <select name="delivery_slot" id="deliverySlot" required>
                            <option value="">-- Pilih Jendela Waktu --</option>
                            <option value="08:00-10:00">08.00–10.00</option>
                            <option value="10:00-12:00">10.00–12.00</option>
                            <option value="13:00-15:00">13.00–15.00</option>
                            <option value="13:00-15:00">15.00–17.00</option>
                            
                        </select>
                    </div>
      
  <!-- METODE PENGIRIMAN -->
<div class="form-group pengiriman">
    <label>Metode Pengiriman</label>
    <div class="label-options">
        <div class="label-option" role="button" tabindex="0">
            <input type="radio" name="shipping_method" value="cod_kurir" checked>
            <svg viewBox="0 0 24 24" fill="none"><path d="M3 7h13v10H3zM16 10h4l1 3v4h-5" stroke="#FF1493" stroke-width="1.2"/><circle cx="7" cy="18" r="1.5" stroke="#FF1493" stroke-width="1.2"/><circle cx="17" cy="18" r="1.5" stroke="#FF1493" stroke-width="1.2"/></svg>
            <span class="label-text">COD Kurir Toko</span>
        </div>
        <div class="label-option" role="button" tabindex="0">
            <input type="radio" name="shipping_method" value="pickup">
            <svg viewBox="0 0 24 24" fill="none"><path d="M3 9l1-4h16l1 4M5 9v10h14V9" stroke="#FF1493" stroke-width="1.2"/><path d="M9 13h6" stroke="#FF1493" stroke-width="1.2"/></svg>
            <span class="label-text">Ambil di Toko</span>
        </div>
        <div class="label-option" role="button" tabindex="0">
            <input type="radio" name="shipping_method" value="gosend_grab">
            <svg viewBox="0 0 24 24" fill="none"><circle cx="6" cy="17" r="3" stroke="#FF1493" stroke-width="1.2"/><circle cx="18" cy="17" r="3" stroke="#FF1493" stroke-width="1.2"/><path d="M6 17l4-8h4l2 4" stroke="#FF1493" stroke-width="1.2"/></svg>
            <span class="label-text">GoSend / Grab</span>
        </div>
        <div class="label-option" role="button" tabindex="0">
            <input type="radio" name="shipping_method" value="jne">
            <svg viewBox="0 0 24 24" fill="none"><path d="M3 7l9-4 9 4v10l-9 4-9-4V7z" stroke="#FF1493" stroke-width="1.2"/></svg>
            <span class="label-text">JNE / J&T</span>
        </div>
    </div>
</div>



<div class="form-group pembayaran">
    <label>Metode Pembayaran</label>

    <div class="label-options">

        <label class="label-option" role="button" tabindex="0">
            <input type="radio" name="payment_method" value="bank_transfer" checked>
            <svg viewBox="0 0 24 24" fill="none">
                <path d="M3 10h18M5 10v8M9 10v8M13 10v8M17 10v8"
                      stroke="#FF1493" stroke-width="1.2"/>
            </svg>
            <span class="label-text">Transfer Bank</span>
        </label>

        <!-- ✅ FIX UTAMA: value HARUS e_wallet -->
         
        <label class="label-option" role="button" tabindex="0">
            <input type="radio" name="payment_method" value="e_wallet">
            <svg viewBox="0 0 24 24" fill="none">
                <rect x="3" y="6" width="18" height="12" rx="2"
                      stroke="#FF1493" stroke-width="1.2"/>
                <path d="M15 12h3" stroke="#FF1493" stroke-width="1.2"/>
            </svg>
            <span class="label-text">E-Wallet</span>
        </label>

        <label class="label-option" role="button" tabindex="0">
            <input type="radio" name="payment_method" value="cod">
            <svg viewBox="0 0 24 24" fill="none">
                <circle cx="8" cy="16" r="2"
                        stroke="#FF1493" stroke-width="1.2"/>
                <circle cx="16" cy="16" r="2"
                        stroke="#FF1493" stroke-width="1.2"/>
                <path d="M4 16h2l2-6h8l2 4"
                      stroke="#FF1493" stroke-width="1.2"/>
            </svg>
            <span class="label-text">COD</span>
        </label>

        <label class="label-option" role="button" tabindex="0">
            <input type="radio" name="payment_method" value="pay_on_pickup">
            <svg viewBox="0 0 24 24" fill="none">
                <path d="M3 9l1-4h16l1 4M5 9v10h14V9"
                      stroke="#FF1493" stroke-width="1.2"/>
                <path d="M9 13h6"
                      stroke="#FF1493" stroke-width="1.2"/>
            </svg>
            <span class="label-text">Bayar di Toko</span>
        </label>

    </div>
</div>



<!-- INFORMASI PEMBAYARAN -->
<div id="paymentInfo" style="display:none;margin-top:15px;">

    <!-- BANK -->
<div class="label-option" data-pay="bank_transfer">
 <div id="bankInfo" style="display:none;">
        <p>Bank BCA</p>
        <p><strong>1234567890</strong></p>

        <p>QRIS:</p>
        <img src="qris.jpeg" width="180">
    </div>
  
</div>



    <!-- E-WALLET -->
  
   <div id="ewalletInfo" style="display:none;">
        <p>DANA / OVO / GoPay</p>
        <p><strong>0812-3456-7890</strong></p>
    </div>

</div>
  
    <!-- INFO SEDERHANA -->
     <div class="label-option" data-pay="bank_transfer">
    <div id="simpleInfo" style="display:none;">
        <p>Pembayaran dilakukan saat barang diterima / diambil.</p>
    </div>
</div>

<!-- UPLOAD -->
 
<div id="uploadProof" style="display:none;margin-top:10px;">
    <input type="file" name="payment_proof">
</div>



                </div> 
          

           
                <div class="checkout-card ringkasan-card">
                <h2>Ringkasan Pesanan</h2>

                <?php foreach ($cart_items as $item): ?>
                 <div class="order-item">
                  <span>
                 <?php echo htmlspecialchars($item['product']['name']); ?> × <?php echo $item['jumlah']; ?>
                 </span>
                 <span>
                 Rp <?php echo number_format($item['subtotal'], 0, ',', '.'); ?>
                 </span>
              </div>
                <?php endforeach; ?>


                <div style="padding-top:0.5rem">
                    <div style="display:flex;justify-content:space-between"><span>Subtotal produk</span><strong id="summarySubtotal">Rp <?php echo number_format($total_price,0,',','.'); ?></strong></div>
                    <div style="display:flex;justify-content:space-between"><span>Ongkir</span><strong id="summaryShipping">Rp 0</strong></div>
                    <div style="display:flex;justify-content:space-between"><span>Diskon</span><strong id="summaryDiscount">Rp 0</strong></div>
                    <div style="display:flex;justify-content:space-between;margin-top:0.5rem;font-size:1.2rem;font-weight:800;color:#FF1493"><span>Total bayar</span><strong id="summaryTotal">Rp <?php echo number_format($total_price,0,',','.'); ?></strong></div>
                </div>

                        <br>
                        <br>
                <div>
                    <span id="summaryLabelAlamat"></span> <br>
                    <span id="summaryMetodePengiriman"></span><br>
                    <span id="summaryMetodePembayaran"></span>
                        
                </div>

                <hr style="border:none;border-top:1px dashed #FFB6D9;margin:1rem 0">

                <div>
                    <div style="font-weight:700">Penerima</div>
                    <div id="summaryRecipient">-</div>
                   <div id="summaryAddress" 
                       style="white-space: pre-line; margin-top:0.5rem; color:#333">-</div>
                    <div id="summaryBouquetNote" style="margin-top:0.5rem;color:#666">-</div>
                </div>

                

                <div id="estimateBanner" style="background: #FFB6D9; padding: 1rem; border-radius: 6px; margin-top: 1rem; text-align: center; color: #333;">
                    <p style="margin: 0;"><strong id="estimateText">Estimasi Pengiriman: 1-3 hari</strong></p>
                </div>
              
               
<!-- checkout -->

    <input type="hidden" name="total_bayar" value="<?= $total_bayar ?>">
    <button type="submit" name="place_order" class="submit-btn">
        
        Konfirmasi Pesanan
       
    </button>
</div>
    </form>

    

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
document.addEventListener('DOMContentLoaded', function () {

    // ===== ELEMENTS =====
    const recipientInput = document.querySelector('input[name="recipient_name"]');
    const bouquetNoteInput = document.querySelector('textarea[name="bouquet_note"]');
    const summarySubtotal = document.getElementById('summarySubtotal');
    const summaryShipping = document.getElementById('summaryShipping');
    const summaryDiscount = document.getElementById('summaryDiscount');
    const summaryTotal = document.getElementById('summaryTotal');
    const summaryRecipient = document.getElementById('summaryRecipient');
    const summaryAddress = document.getElementById('summaryAddress');
    const summaryBouquetNote = document.getElementById('summaryBouquetNote');
    const summaryLabelAlamat = document.getElementById('summaryLabelAlamat');
    const summaryMetodePengiriman = document.getElementById('summaryMetodePengiriman');
    const summaryMetodePembayaran = document.getElementById('summaryMetodePembayaran');
    const estimateText = document.getElementById('estimateText');
    const deliveryDate = document.getElementById('deliveryDate');
    const deliverySlot = document.getElementById('deliverySlot');

    const subtotal = <?php echo (int)$total_price; ?>;
    const defaultRecipient = <?php echo json_encode($customer_name); ?>;

    // ===== PAYMENT INFO =====
    const paymentInfoMap = {
        'bank_transfer': ['bankInfo','uploadProof'],
        'e_wallet': ['ewalletInfo','uploadProof'],
        'cod': ['simpleInfo'],
        'pay_on_pickup': ['simpleInfo']
    };

    function formatRp(num) {
        return 'Rp ' + Math.round(num).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    function getShippingCost(method) {
        switch(method){
            case 'cod_kurir': return 9500;
            case 'pickup': return 0;
            case 'gosend_grab': return 15000;
            case 'jne': return 25000;
            default: return 0;
        }
    }

    function getDiscount(subtotal){
        return subtotal > 200000 ? subtotal * 0.2 : 0;
    }

    function getSelectedText(inputName){
        const selected = document.querySelector(`input[name="${inputName}"]:checked`);
        return selected ? selected.parentElement.querySelector('.label-text')?.textContent.trim() || selected.value : '-';
    }

    // ===== GET ALAMAT =====
    function getSelectedAddress(){
        const alamatDiv = document.querySelector('.alamat-terpilih');
        if(alamatDiv) return alamatDiv.innerText.trim();

        const selAddr = document.querySelector('input[name="selected_alamat_id"]');
        if(selAddr && selAddr.value && parseInt(selAddr.value) > 0) return selAddr.value;

        const detail = (document.querySelector('textarea[name="detail_addr"]') || {value:''}).value.trim();
        const prov = document.getElementById('provinceSelect')?.value || '';
        const reg = document.getElementById('regencySelect')?.value || '';
        const dist = document.getElementById('districtSelect')?.value || '';
        const vill = document.getElementById('villageSelect')?.value || '';
        const rtrw = (document.querySelector('input[name="rtrw"]') || {value:''}).value;
        let composed = detail;
        if(prov||reg||dist||vill){
            composed += '\n' + [prov,reg,dist,vill].filter(Boolean).join(', ') + (rtrw ? ', RT/RW: ' + rtrw : '');
        }
        return composed || '-';
    }

    // ===== UPDATE PAYMENT INFO =====
    function updatePaymentInfo(){
        ['bankInfo','ewalletInfo','simpleInfo','uploadProof','paymentInfo'].forEach(id=>{
            const el = document.getElementById(id);
            if(el) el.style.display='none';
        });
        const selected = document.querySelector('input[name="payment_method"]:checked')?.value;
        if(selected && paymentInfoMap[selected]){
            paymentInfoMap[selected].forEach(id=>{
                const el = document.getElementById(id);
                if(el) el.style.display='block';
            });
            const info = document.getElementById('paymentInfo');
            if(info) info.style.display='block';
        }
    }

    // ===== ESTIMASI PENGIRIMAN =====
    function isWeekday(date) {
        const day = date.getDay();
        return day !== 0 && day !== 6; // Minggu/Sabtu tidak dihitung
    }

    function calculateBusinessDays(start, end) {
        let count = 0;
        let current = new Date(start);
        while(current <= end){
            if(isWeekday(current)) count++;
            current.setDate(current.getDate() + 1);
        }
        return count;
    }

    function updateEstimate(){
        if(!deliveryDate.value){
            estimateText.textContent = 'Estimasi Pengiriman: 1-3 hari kerja';
            return;
        }
        const today = new Date();
        const selected = new Date(deliveryDate.value);
        const diffDays = calculateBusinessDays(today, selected);

        let estimate = '';
        if(diffDays === 0) estimate = 'Estimasi Pengiriman: hari ini';
        else if(diffDays === 1) estimate = 'Estimasi Pengiriman: besok';
        else estimate = `Estimasi Pengiriman: ${diffDays} hari`;

        if(deliverySlot.value) estimate += ` (${deliverySlot.value})`;
        estimateText.textContent = estimate;
    }

    // ===== UPDATE SUMMARY =====
    function updateSummary(){
        summaryRecipient.textContent = recipientInput?.value.trim() || defaultRecipient;
        summaryBouquetNote.textContent = bouquetNoteInput?.value.trim() || '-';
        summaryLabelAlamat.textContent = getSelectedText('label');
        summaryMetodePengiriman.textContent = getSelectedText('shipping_method');
        summaryMetodePembayaran.textContent = getSelectedText('payment_method');
        summaryAddress.textContent = getSelectedAddress();

        const shippingMethod = document.querySelector('input[name="shipping_method"]:checked')?.value || 'cod_kurir';
        const shippingCost = getShippingCost(shippingMethod);
        const discount = getDiscount(subtotal);
        const total = subtotal + shippingCost - discount;

        summarySubtotal.textContent = formatRp(subtotal);
        summaryShipping.textContent = formatRp(shippingCost);
        summaryDiscount.textContent = formatRp(discount);
        summaryTotal.textContent = formatRp(total);

        // update estimasi
        updateEstimate();
    }

    // ===== EVENT LISTENERS =====
    ['recipient_name','bouquet_note','label','shipping_method','payment_method','selected_alamat_id','detail_addr','provinceSelect','regencySelect','districtSelect','villageSelect','rtrw','delivery_date','delivery_slot'].forEach(name=>{
        document.querySelectorAll(`[name="${name}"]`).forEach(el=>{
            el.addEventListener('input', ()=>{ updateSummary(); if(name==='payment_method') updatePaymentInfo(); });
            el.addEventListener('change', ()=>{ updateSummary(); if(name==='payment_method') updatePaymentInfo(); });
        });
    });

    document.querySelectorAll('.label-option').forEach(opt=>{
        opt.addEventListener('click', ()=>{
            const input = opt.querySelector('input[type="radio"]');
            if(input) input.checked = true;
            const siblings = opt.parentElement.querySelectorAll('.label-option');
            siblings.forEach(s=>s.classList.remove('selected'));
            opt.classList.add('selected');
            updateSummary();
            if(input?.name==='payment_method') updatePaymentInfo();
        });
    });

    const changeAddressBtn = document.getElementById('changeAddressBtn');
    if(changeAddressBtn){
        changeAddressBtn.addEventListener('click', ()=>{ window.location.href = 'alamat.php?return=checkout'; });
    }

    // ===== INITIAL =====
    updatePaymentInfo();
    updateSummary();

    // ===== CHECKOUT VALIDATION =====
    const checkoutForm = document.getElementById('checkoutForm');
    if(checkoutForm){
        checkoutForm.addEventListener('submit', function(e){
            const phone = document.querySelector('input[name="phone"]')?.value.trim() || '';
            const addressProvided = getSelectedAddress() !== '-';
            if(!phone || !addressProvided){
                e.preventDefault();
                alert('Silakan lengkapi nomor telepon dan pilih/isi alamat pengiriman sebelum melanjutkan.');
            }
        });
    }

});


// Fungsi tampilkan modal ketika klik Logout
function confirmLogout() {
    document.getElementById('logoutModal').style.display = 'flex';
}

// Tutup modal
document.getElementById('logoutNo').onclick = function() {
    document.getElementById('logoutModal').style.display = 'none';
}

// Logout ketika klik Ya
document.getElementById('logoutYes').onclick = function() {
    window.location.href = 'logout_customer.php';
}

</script>


</body>
</html>
