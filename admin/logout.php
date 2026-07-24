<?php
require_once __DIR__ . '/../includes/auth.php';

startSecureSession();
logoutAdmin();
redirect('login.php');
