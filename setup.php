<?php
include 'config.php';

// Create database if not exists
$sql = "CREATE DATABASE IF NOT EXISTS admin_website";
if ($conn->query($sql) === TRUE) {
    echo "Database created successfully<br>";
} else {
    echo "Error creating database: " . $conn->error . "<br>";
}

// Select database
$conn->select_db('admin_website');

// Create admins table
$sql = "CREATE TABLE IF NOT EXISTS admins (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL
)";
if ($conn->query($sql) === TRUE) {
    echo "Table admins created successfully<br>";
} else {
    echo "Error creating table: " . $conn->error . "<br>";
}

// Insert admin user
$username = 'Maemunah Nurfalah';
$password = password_hash('14Maret@', PASSWORD_DEFAULT);
$sql = "INSERT IGNORE INTO admins (username, `password`) VALUES ('$username', '$password')";
if ($conn->query($sql) === TRUE) {
    echo "Admin user inserted successfully<br>";
} else {
    echo "Error inserting admin: " . $conn->error . "<br>";
}

// Create products table
$sql = "CREATE TABLE IF NOT EXISTS products (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    image VARCHAR(255),
    category VARCHAR(50) DEFAULT 'Bunga'
)";
if ($conn->query($sql) === TRUE) {
    echo "Table products created successfully<br>";
} else {
    echo "Error creating table: " . $conn->error . "<br>";
}

// Create customers table first (before orders since orders has FK to customers)
$sql = "CREATE TABLE IF NOT EXISTS customers (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL
)";
if ($conn->query($sql) === TRUE) {
    echo "Table customers created successfully<br>";
} else {
    echo "Error creating table: " . $conn->error . "<br>";
}

// Insert sample products
$products = [
    ['Bunga Mawar', 'Rangkaian bunga mawar merah segar.', 150000, 'https://via.placeholder.com/150/FFC0CB/000000?text=Bunga', 'Bunga'],
    ['Amplop Uang', 'Amplop uang eksklusif dengan desain mewah.', 50000, 'https://via.placeholder.com/150/FFD700/000000?text=Uang', 'Uang'],
    ['Boneka Teddy', 'Boneka teddy bear lembut dan menggemaskan.', 75000, 'https://via.placeholder.com/150/FF69B4/000000?text=Boneka', 'Boneka'],
    ['Kue Tart', 'Kue tart lezat dengan berbagai rasa pilihan.', 200000, 'https://via.placeholder.com/150/FFFF00/000000?text=Makanan', 'Makanan']
];

foreach ($products as $product) {
    $name = $conn->real_escape_string($product[0]);
    $desc = $conn->real_escape_string($product[1]);
    $price = $product[2];
    $image = $conn->real_escape_string($product[3]);
    $category = $conn->real_escape_string($product[4]);
    $sql = "INSERT IGNORE INTO products (name, description, price, image, category) VALUES ('$name', '$desc', $price, '$image', '$category')";
    if ($conn->query($sql) !== TRUE) {
        echo "Error inserting product: " . $conn->error . "<br>";
    }
}

// Create orders table (after customers table)
$sql = "CREATE TABLE IF NOT EXISTS orders (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id INT(6) UNSIGNED,
    product_id INT(6) UNSIGNED,
    quantity INT(11) NOT NULL,
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (product_id) REFERENCES products(id)
)";
if ($conn->query($sql) === TRUE) {
    echo "Table orders created successfully<br>";
} else {
    echo "Error creating table: " . $conn->error . "<br>";
}

$conn->close();
echo "Setup completed!";
?>
