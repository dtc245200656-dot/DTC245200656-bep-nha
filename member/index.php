<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/community-lib.php';
$currentUser=requireContributor();
$currentAdmin=$currentUser['role']==='admin'?$currentUser:false;
require __DIR__ . '/../includes/community-page.php';