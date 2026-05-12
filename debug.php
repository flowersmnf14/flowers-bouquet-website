<?php
session_start();
include 'config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

echo "<h1>🔍 Debug Info - Flowers Bouquet</h1>";
echo "<hr>";

// Check database connection
echo "<h2>1. Koneksi Database</h2>";
if ($conn->connect_error) {
    echo "<p style='color:red;'>❌ ERROR: " . $conn->connect_error . "</p>";
} else {
    echo "<p style='color:green;'>✅ Database terkoneksi</p>";
}

// Check if products table exists and count products
echo "<h2>2. Status Produk</h2>";
try {
    $sql = "SELECT COUNT(*) as count FROM products";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    $product_count = $row['count'];
    
    if ($product_count > 0) {
        echo "<p style='color:green;'>✅ Total produk: <strong>$product_count</strong></p>";
        
        // List all products
        echo "<h3>Daftar Produk:</h3>";
        echo "<table border='1' cellpadding='10'>";
        echo "<tr><th>ID</th><th>Nama</th><th>Harga</th><th>Image URL</th><th>Status</th></tr>";
        
        $sql = "SELECT id, name, price, image FROM products";
        $result = $conn->query($sql);
        
        while ($row = $result->fetch_assoc()) {
            $id = $row['id'];
            $name = $row['name'];
            $price = $row['price'];
            $image = $row['image'];
            
            // Check if image URL is valid
            $image_status = strpos($image, 'http') === 0 ? '✅ Valid URL' : '⚠️ Local Path';
            
            echo "<tr>";
            echo "<td>$id</td>";
            echo "<td>$name</td>";
            echo "<td>\$" . number_format($price, 2) . "</td>";
            echo "<td><small>" . htmlspecialchars(substr($image, 0, 50)) . "...</small></td>";
            echo "<td>$image_status</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "<p style='color:red;'>❌ Tidak ada produk di database!</p>";
        echo "<p>Silakan jalankan <strong>setup.php</strong> untuk membuat produk sample.</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ ERROR: " . $e->getMessage() . "</p>";
}

// Check admin users
echo "<h2>3. Admin Users</h2>";
try {
    $sql = "SELECT COUNT(*) as count FROM admins";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    $admin_count = $row['count'];
    
    if ($admin_count > 0) {
        echo "<p style='color:green;'>✅ Total admin: <strong>$admin_count</strong></p>";
    } else {
        echo "<p style='color:red;'>❌ Tidak ada admin user!</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ ERROR: " . $e->getMessage() . "</p>";
}

// Check session status
echo "<h2>4. Status Login</h2>";
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in']) {
    echo "<p style='color:green;'>✅ Admin terlogin: <strong>" . $_SESSION['admin_username'] . "</strong></p>";
} else {
    echo "<p style='color:orange;'>⚠️ Belum login</p>";
}

// File permissions
echo "<h2>5. Folder Uploads</h2>";
if (is_dir('uploads')) {
    if (is_writable('uploads')) {
        echo "<p style='color:green;'>✅ Folder uploads ada dan writable</p>";
    } else {
        echo "<p style='color:red;'>❌ Folder uploads tidak writable</p>";
    }
} else {
    echo "<p style='color:red;'>❌ Folder uploads tidak ditemukan</p>";
}

echo "<hr>";
echo "<p><a href='berandaAdmin.php'>← Kembali ke Beranda Admin</a></p>";
?>
<style>
body {
    font-family: Arial, sans-serif;
    margin: 20px;
    background-color: #FFF5EE;
}
h1, h2, h3 {
    color: #FF1493;
}
table {
    margin-top: 10px;
    background: white;
    border-collapse: collapse;
}
th {
    background-color: #FFC0CB;
    color: white;
}
td {
    text-align: left;
}
a {
    color: #FF1493;
    text-decoration: none;
    font-weight: bold;
}
a:hover {
    text-decoration: underline;
}
</style>
