<?php

  include "lib/mysql.php";

  $result = mysql_run_query(
    "SELECT `ssh_log`.`date`," .
    "`build_slaves`.`name`," .
    "`ssh_log`.`action`," .
    "`ssh_log`.`parameters`" .
    " FROM `ssh_log`" .
    " JOIN `build_slaves` ON `ssh_log`.`build_slave`=`build_slaves`.`id`" .
    " WHERE TIMEDIFF(NOW(),`ssh_log`.`date`) < \"10:00\"" .
    " ORDER BY `ssh_log`.`date` DESC"
  );

?>
<html>
  <head>
    <title>ssh-log</title>
    <link rel="stylesheet" type="text/css" href="/static/style.css">
  </head>
  <body>
    <table>
      <tr>
        <th>
          date
        </th>
        <th>
          build slave
        </th>
        <th>
          action
        </th>
        <th>
          parameters
        </th>
      </tr>
<?php

  while ($row = $result -> fetch_assoc()) {
    print "      <tr>\n";
    foreach ($row as $val) {
      print "      <td>\n";
      print "        " . $val . "\n";
      print "      </td>\n";
    }
    print "      </tr>\n";
  }

?>
    </table>
  </body>
</html>
