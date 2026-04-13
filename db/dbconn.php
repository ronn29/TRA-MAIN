<?php
$servername = getenv('MYSQLHOST') ?: 'localhost';
$uname      = getenv('MYSQLUSER') ?: 'root';
$pass       = getenv('MYSQLPASSWORD') ?: '';
$dbname     = getenv('MYSQLDATABASE') ?: 'tragabay_db_survey';
$port       = getenv('MYSQLPORT') ?: 3306;

$conn = mysqli_connect($servername, $uname, $pass, $dbname, (int)$port);

if (!$conn) {
	die('Error connecting to database: ' . mysqli_connect_error());
}
?>
