<?php
// Einstiegspunkt: leitet direkt in den Admin-Bereich weiter.
require_once __DIR__ . '/includes/auth.php';

startSecureSession();
redirect(isLoggedIn() ? 'admin/index.php' : 'admin/login.php');
