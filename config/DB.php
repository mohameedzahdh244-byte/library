
<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "library_system";

// إنشاء الاتصال


$conn = new mysqli($servername, $username, $password, $dbname);

// التحقق من الاتصال
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
     //echo "Connected successfully";
    $conn->set_charset("utf8mb4");



?>




