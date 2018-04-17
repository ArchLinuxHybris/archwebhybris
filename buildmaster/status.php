<?php

  include "lib/mysql.php";

  $result = mysql_run_query(
    "SELECT MAX(`package_sources`.`commit_time`) AS `last`" .
    "FROM `package_sources`"
  );

?>
<html>
<head>
<title>Build master status</title>
<link rel="stylesheet" type="text/css" href="/static/style.css">
</head>
<body>
<a href="https://buildmaster.archlinux32.org/">Start page</a><br>
<?php

if ($result -> num_rows > 0) {
  $row = $result->fetch_assoc();
  print "latest package source is from " . $row["last"] . ".<br>\n";
}

?>
</body>
</html>
