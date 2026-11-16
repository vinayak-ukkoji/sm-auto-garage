<?php
require __DIR__ . '/includes/bootstrap.php';
redirect(current_user() ? 'service.php' : 'login.php');
