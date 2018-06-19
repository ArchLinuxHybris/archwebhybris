<?php
require_once "../init.php";
require_once BASE . "/lib/mysql.php";

  $result = mysql_run_query(
    "SELECT" .
    " `build_slaves`.`name`," .
    "`persons`.`name` AS `operator`," .
    "`package_sources`.`pkgbase`," .
    "MAX(`ssh_log`.`date`" .
    ") AS `lc`," .
    "MAX(IF(`ssh_log`.`action`=\"get-assignment\",`ssh_log`.`date`,NULL)" .
    ") AS `bs`," .
    "`build_slaves`.`logged_lines`," .
    "`build_slaves`.`trials`," .
    "`build_slaves`.`last_action`" .
    " FROM `build_slaves`" .
    " LEFT JOIN `ssh_log` ON" .
    " `ssh_log`.`build_slave`=`build_slaves`.`id`" .
    " JOIN `ssh_keys` ON" .
    " `build_slaves`.`ssh_key`=`ssh_keys`.`id`" .
    " JOIN `persons` ON" .
    " `ssh_keys`.`owner`=`persons`.`id`" .
    " LEFT JOIN `build_assignments` ON" .
    " `build_slaves`.`currently_building`=`build_assignments`.`id`" .
    " LEFT JOIN `package_sources` ON" .
    " `build_assignments`.`package_source`=`package_sources`.`id`" .
    " GROUP BY `build_slaves`.`id`" .
    " ORDER BY `lc`"
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
    print "<tr><th>name</th><th>operator</th><th>currently building</th><th>last connection</th><th>building since</th><th>trials</th><th>logged lines</th><th>last action</th></tr>\n";
    while ($row = $result -> fetch_assoc()) {
      foreach ($row as $key => $value) {
        if ($value=="") {
          $row[$key]="&nbsp;";
        }
      }
      print "<tr>";
      print "<td><a href=\"/buildmaster/log.php?show=ssh&slave=".$row["name"]."\">".$row["name"]."</a></td>";
      print "<td>".$row["operator"]."</td>";
      print "<td>".$row["pkgbase"]."</td>";
      print "<td>".$row["lc"]."</td>";
      print "<td>".$row["bs"]."</td>";
      print "<td>".$row["trials"]."</td>";
      print "<td>".$row["logged_lines"]."</td>";
      print "<td>".$row["last_action"]."</td>";
      print "</tr>\n";
    }
  }
  print "</table>\n";

?>
</body></html>
