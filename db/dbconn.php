<?php
function env($key, $default = '') {
	return getenv($key) ?: ($_ENV[$key] ?? ($_SERVER[$key] ?? $default));
}

$servername = env('MYSQLHOST', '127.0.0.1');
$uname      = env('MYSQLUSER', 'root');
$pass       = env('MYSQLPASSWORD', '');
$dbname     = env('MYSQLDATABASE', 'tra-db');
$port       = env('MYSQLPORT', 3306);

$conn = mysqli_connect($servername, $uname, $pass, $dbname, (int)$port);

if (!$conn) {
	die('Error connecting to database: ' . mysqli_connect_error());
}
?>
