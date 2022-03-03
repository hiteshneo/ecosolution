<?php
define('SUBDIR', str_replace($_SERVER['DOCUMENT_ROOT'],"",dirname(__FILE__)).'/');
define('ROOT_DIRECTORY',$_SERVER['DOCUMENT_ROOT'] . SUBDIR);
define('CASHFREE_PAYOUT_FILES',ROOT_DIRECTORY."cashfreepayout/cfpayout.inc.php");
define('USER_ROLE',2);
define('DRIVER_ROLE',3);
define('RESTAURANT_ROLE',4);
?>