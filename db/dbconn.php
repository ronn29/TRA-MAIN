<?php
$servername = getenv('MYSQLHOST') ?: '127.0.0.1';
$uname      = getenv('MYSQLUSER') ?: 'root';
$pass       = getenv('MYSQLPASSWORD') ?: '';
$dbname     = getenv('MYSQLDATABASE') ?: 'tra-db';
$port       = getenv('MYSQLPORT') ?: 3306;

$conn = mysqli_connect($servername, $uname, $pass, $dbname, (int)$port);

if (!$conn) {
	die('Error connecting to database: ' . mysqli_connect_error());
}
?>
