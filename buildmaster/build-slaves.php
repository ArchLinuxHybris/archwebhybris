<?php

  include "lib/mysql.php";

  $result = mysql_run_query(
    "SELECT" .
    " `build_slaves`.`name`," .
    "`build_slaves`.`operator`," .
    "`package_sources`.`pkgbase`," .
    "`build_slaves`.`last_connection`," .
    "`build_slaves`.`logged_lines`," .
    "`build_slaves`.`last_action`" .
    " FROM `build_slaves`" .
    " LEFT JOIN `build_assignments` ON" .
    " `build_slaves`.`currently_building`=`build_assignments`.`id`" .
    " LEFT JOIN `package_sources` ON" .
    " `build_assignments`.`package_source`=`package_sources`.`id`" .
    " ORDER BY `build_slaves`.`last_connection`"
  );

?>
<html>
  <head>
    <title>list of build slaves</title>
  </head>
  <body>
<?php
show_warning_on_offline_slave();

  print "<table border=1>\n";
  if ($result->num_rows > 0) {
    print "<tr><th>name</th><th>operator</th><th>currently building</th><th>last connection</th><th>logged lines</th><th>last action</th></tr>\n";
    while ($row = $result -> fetch_assoc()) {
      foreach ($row as $key => $value) {
        if ($value=="") {
          $row[$key]="&nbsp;";
        }
      }
      print "<tr>";
      print "<td>".$row["name"]."</td>";
      print "<td>".$row["operator"]."</td>";
      print "<td>".$row["pkgbase"]."</td>";
      print "<td>".$row["last_connection"]."</td>";
      print "<td>".$row["logged_lines"]."</td>";
      print "<td>".$row["last_action"]."</td>";
      print "</tr>\n";
    }
  }
  print "</table>\n";

?>
</body></html>
