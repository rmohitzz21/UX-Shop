<?php
require_once 'includes/config.php';
$result = $conn->query("SELECT email, role FROM users WHERE role = 'admin'");
echo "Admins in DB:\n";
while($row = $result->fetch_assoc()) {
    echo "- " . $row['email'] . "\n";
}
?>
