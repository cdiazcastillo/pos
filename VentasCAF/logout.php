<?php
require_once 'includes/auth.php';
auth_logout_user();
auth_redirect('vendedor_login.php');
