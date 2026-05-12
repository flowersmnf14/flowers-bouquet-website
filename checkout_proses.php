
// foreach ($cart as $item) {
//     $stmt = $conn->prepare(
//         "UPDATE products 
//          SET stock = stock - ? 
//          WHERE product_id = ? AND stock >= ?"
//     );
//     $stmt->bind_param("iii", $item['qty'], $item['product_id'], $item['qty']);
//     $stmt->execute();

//     if ($stmt->affected_rows === 0) {
//         die("Stok produk tidak mencukupi");
//     }
// }
<?php
$conn->begin_transaction();

try {

    // 1️⃣ hitung total
    $total = 0;
    foreach ($cart as $item) {
        $total += $item['qty'] * $item['price'];
    }

    // 2️⃣ simpan ke orders
    $stmt = $conn->prepare("
        INSERT INTO orders (customer_id, product_id, total, status)
        VALUES (?, ?, ?, 'pending')
    ");

    // product_id di orders cuma formalitas → ambil dari item pertama
    $firstProduct = $cart[0]['product_id'];

    $stmt->bind_param("iid", $customer_id, $firstProduct, $total);
    $stmt->execute();

    $order_id = $conn->insert_id;

    // 3️⃣ loop cart
    foreach ($cart as $item) {

        // simpan detail produk
        $stmt = $conn->prepare("
            INSERT INTO detailproduk (id_orders, product_id, jumlah, subtotal)
            VALUES (?, ?, ?, ?)
        ");

        $subtotal = $item['qty'] * $item['price'];

        $stmt->bind_param(
            "iiid",
            $order_id,
            $item['product_id'],
            $item['qty'],
            $subtotal
        );

        $stmt->execute();

        // 4️⃣ update stok
        $stmt = $conn->prepare("
            UPDATE products
            SET stock = stock - ?
            WHERE product_id = ? AND stock >= ?
        ");

        $stmt->bind_param(
            "iii",
            $item['qty'],
            $item['product_id'],
            $item['qty']
        );

        $stmt->execute();

        if ($stmt->affected_rows === 0) {
            throw new Exception("Stok produk tidak mencukupi");
        }
    }

    // 5️⃣ simpan pembayaran (opsional tapi disarankan)
    $conn->query("
        INSERT INTO pembayaran (id_orders, metode_bayar, statusBayar)
        VALUES ($order_id, 'menunggu', 'menunggu')
    ");

    // 6️⃣ sukses
    $conn->commit();

    unset($_SESSION['cart']);

} catch (Exception $e) {
    $conn->rollback();
    die("Checkout gagal: " . $e->getMessage());
}
?>