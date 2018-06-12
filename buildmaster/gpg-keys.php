<?php

  include "lib/mysql.php";

  $result = mysql_run_query(
    "SELECT" .
    " GROUP_CONCAT(`email_actions`.`name`) AS `action`," .
    "`persons`.`name` AS `person`," .
    "`gpg_keys`.`fingerprint`" .
    " FROM `email_actions`" .
    " JOIN `allowed_email_actions` ON" .
    " `email_actions`.`id`=`allowed_email_actions`.`action`" .
    " RIGHT JOIN `gpg_keys` ON" .
    " `allowed_email_actions`.`gpg_key`=`gpg_keys`.`id`" .
    " JOIN `persons` ON" .
    " `gpg_keys`.`owner`=`persons`.`id`" .
    " GROUP BY `gpg_keys`.`id`" .
    " ORDER BY `persons`.`name`"
  );

?>
<html>
  <head>
    <title>list of gpg-keys</title>
  </head>
  <body>
<?php
show_warning_on_offline_slave();

  print "<table border=1>\n";
  if ($result->num_rows > 0) {
    print "<tr><th>person</th><th>action</th><th>fingerprint</th></tr>\n";
    while ($row = $result -> fetch_assoc()) {
      foreach ($row as $key => $value) {
        if ($value=="") {
          $row[$key]="&nbsp;";
        }
      }
      print "<tr>";
      print "<td>" . $row["person"] . "</td>";
      print "<td>" . $row["action"] . "</td>";
      print "<td><a href=\"http://pgp.mit.edu/pks/lookup?op=get&search=0x" .
        substr($row["fingerprint"],-16) .
        "\">" . $row["fingerprint"] . "</a></td>";
      print "</tr>\n";
    }
  }
  print "</table>\n";

?>
</body></html>
