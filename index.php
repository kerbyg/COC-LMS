<?php
// Entry point — redirect visitors to the login page
require_once __DIR__ . '/config/constants.php';
header('Location: ' . BASE_URL . '/app/login.html');
exit;
