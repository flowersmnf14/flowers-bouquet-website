<?php
include 'config.php';

echo "<h2>Status Database Flowers Bouquet</h2>";

// Check products table
echo "<h3>1. Tabel Products:</h3>";
$result = $conn->query("SHOW TABLES LIKE 'products'");
if ($result->num_rows > 0) {
    echo "✓ Tabel products ada<br>";
    
    // Check columns
    echo "<h4>Kolom-kolom:</h4>";
    $result = $conn->query('DESCRIBE products');
    $has_category = false;
    while($row = $result->fetch_assoc()) {
        echo "- " . $row['Field'] . " (" . $row['Type'] . ")<br>";
        if ($row['Field'] === 'category') {
            $has_category = true;
        }
    }
    
    if (!$has_category) {
        echo "<br><strong style='color: red;'>⚠ Kolom 'category' TIDAK ADA!</strong><br>";
        echo "<a href='add_category_column.php'>Klik di sini untuk menambahkan kolom category</a>";
    } else {
        echo "<br><strong style='color: green;'>✓ Kolom 'category' sudah ada</strong>";
    }
    
    // Check sample data
    echo "<h4>Jumlah Produk:</h4>";
    $result = $conn->query("SELECT COUNT(*) as total FROM products");
    $row = $result->fetch_assoc();
    echo "Total: " . $row['total'] . " produk<br>";
    
} else {
    echo "✗ Tabel products TIDAK ada!<br>";
}

echo "<hr>";
echo "<h3>2. Tabel Orders:</h3>";
$result = $conn->query("SHOW TABLES LIKE 'orders'");
if ($result->num_rows > 0) {
    echo "✓ Tabel orders ada<br>";
} else {
    echo "✗ Tabel orders TIDAK ada<br>";
}

echo "<hr>";
echo "<h3>3. Tabel Admins:</h3>";
$result = $conn->query("SHOW TABLES LIKE 'admins'");
if ($result->num_rows > 0) {
    echo "✓ Tabel admins ada<br>";
    $result = $conn->query("SELECT COUNT(*) as total FROM admins");
    $row = $result->fetch_assoc();
    echo "Jumlah admin: " . $row['total'] . "<br>";
} else {
    echo "✗ Tabel admins TIDAK ada<br>";
}

echo "<hr>";
echo "<h3>Kesimpulan:</h3>";
echo "Jika ada ⚠ atau ✗ di atas, silakan jalankan setup.php terlebih dahulu.<br>";
echo "<a href='setup.php' target='_blank'><button>Setup Database</button></a>";
?>
