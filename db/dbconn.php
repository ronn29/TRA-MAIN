<?php
$servername="localhost";
$uname="root";
$pass="";
$dbname="tragabay_db_survey";

$conn = mysqli_connect($servername, $uname, $pass, $dbname);

if (!$conn) {

	
	if (!$conn) {
		echo 'Error connecting to database'.mysqli_connect_error($conn);
}

}
?>