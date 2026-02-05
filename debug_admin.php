<?php
require_once 'includes/config.php';

$email = 'Hello@uxpacific.com';
$password = 'admin123';

echo "<h2>Admin Debugger</h2>";

// 1. Check DB Connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
echo "Database connected.<br>";

// 2. Search for user
$sql = "SELECT * FROM users WHERE email = '$email'";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    echo "User found: " . $user['email'] . "<br>";
    echo "Role: " . $user['role'] . "<br>";
    echo "Stored Hash: " . $user['password_hash'] . "<br>";
    
    // 3. Verify Password
    if (password_verify($password, $user['password_hash'])) {
        echo "✅ Password verification SUCCESS.<br>";
    } else {
        echo "❌ Password verification FAILED.<br>";
        
        // Fix it?
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $conn->query("UPDATE users SET password_hash = '$newHash' WHERE email = '$email'");
        echo "🛠️ Password has been reset to 'admin123'. Try login again.<br>";
    }
    
    // 4. Verify Role
    if ($user['role'] !== 'admin') {
        echo "❌ Role is NOT admin. Fixing...<br>";
        $conn->query("UPDATE users SET role = 'admin' WHERE email = '$email'");
        echo "🛠️ Role updated to 'admin'.<br>";
    }
    
} else {
    echo "❌ Admin user NOT found.<br>";
    // Create it
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $sql = "INSERT INTO users (email, password_hash, first_name, last_name, role) VALUES ('$email', '$hash', 'Admin', 'User', 'admin')";
    if ($conn->query($sql)) {
         echo "🛠️ Admin user created successfully.<br>";
    } else {
         echo "Error creating admin: " . $conn->error . "<br>";
    }
}
?>
