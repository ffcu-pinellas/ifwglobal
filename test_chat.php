<?php
session_start();
$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_id'] = 1;
$_SESSION['admin_role'] = 'admin';
ini_set('display_errors', 1);
error_reporting(E_ALL);
include 'admin/chat.php';




