<?php
// Copy this file to /etc/components/config.php (outside the web root),
// fill in the real password and set:
//   sudo chown root:www-data /etc/components/config.php
//   sudo chmod 640 /etc/components/config.php
define('DB_HOST', 'localhost');
define('DB_NAME', 'components');
define('DB_USER', 'component');
define('DB_PASS', 'CHANGE_ME');
