<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "kajian_asrama";
$port = "3307"; 

$conn = mysqli_connect($host, $user, $pass, $db, $port);

if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

// Gunakan ini agar lebih aman
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>