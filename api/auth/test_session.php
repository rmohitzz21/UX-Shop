<?php
session_set_cookie_params(0, '/');
session_start();
$_SESSION['test_key'] = 'Hello';
echo "Session ID: " . session_id() . "<br>";
echo "Cookie Params: <pre>" . print_r(session_get_cookie_params(), true) . "</pre>";
?>
