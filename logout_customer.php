<?php
session_start();

// Destroy customer session
session_destroy();

// Redirect to login
header("Location: login_customer.php");
exit();
