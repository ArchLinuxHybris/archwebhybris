<?php

  include "lib/mysql.php";

?>
<html>
  <head>
    <title>Archlinux32 packages</title>
  </head>
  <body>
<?php show_warning_on_offline_slave(); ?>
    <a href="/buildmaster/build-list.php">build list</a>
    <a href="/buildmaster/build-list.php?show=broken">broken packages</a><br>
    <a href="/buildmaster/build-slaves.php">build-slaves</a>
    <a href="/buildmaster/status.php">status</a><br>
    <a href="/buildmaster/mysql-issues.php">broken dependencies in the database</a><br>
    <a href="/buildmaster/todos.php">todos</a>
    as <a href="/buildmaster/todos.php?graph">graph</a><br>
    <a href="/buildmaster/blacklist.php">blacklisted packages</a><br>
    <img src="/buildmaster/statistics.php?log"><br>
  </body>
</html>
