<?php
$db_name = 'wp_immonexbrickswplocal_db';
$db_user = 'wp_immonexbrickswplocal_user';
$db_password = 'wp_immonexbrickswplocal_pw';
$db_host = 'localhost';

$conn = new mysqli($db_host, $db_user, $db_password, $db_name);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Connected successfully";
