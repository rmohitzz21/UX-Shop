<?php
session_start();
echo "Session ID: " . session_id() . "<br>";
echo "Test Key: " . (isset($_SESSION['test_key']) ? $_SESSION['test_key'] : 'NOT SET') . "<br>";
echo "Cookie Params: <pre>" . print_r(session_get_cookie_params(), true) . "</pre>";
?>
