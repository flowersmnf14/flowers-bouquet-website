<?php
include 'config.php';

$id = (int)$_GET['id'];

$conn->query("UPDATE orders SET status='approved' WHERE id=$id");

$_SESSION['admin_success'] = "Pesanan berhasil disetujui";
header("Location: manage_orders.php");
exit;
