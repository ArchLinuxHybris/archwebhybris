<?php
require_once "../init.php";
require_once BASE . "/lib/mysql.php";


  $filter = "";
  if (isset($_GET["show"]) &&
    ($_GET["show"] == "ssh")) {
    $to_show = "ssh";
    $columns = array(
      "date" => "`ssh_log`.`date`",
      "build slave" => "`build_slaves`.`name`",
      "action" => "`ssh_log`.`action`",
      "parameters" => "`ssh_log`.`parameters`"
    );
    $join = " LEFT JOIN `build_slaves` ON `ssh_log`.`build_slave`=`build_slaves`.`id`";
    if (isset($_GET["action"]))
      $filter .= " AND `ssh_log`.`action` LIKE from_base64(\"" . base64_encode($_GET["action"]) . "\")";
    if (isset($_GET["slave"]))
      $filter .= " AND `build_slaves`.`name` LIKE from_base64(\"" . base64_encode($_GET["slave"]) . "\")";
  } else {
    $to_show = "email";
    $columns = array(
      "date" => "`email_log`.`date`",
      "action" => "`email_actions`.`name`",
      "count" => "`email_log`.`count`",
      "success" => "`email_log`.`success`",
      "person" => "`persons`.`name`",
      "comment" => "`email_log`.`comment`"
    );
    $join =
      " LEFT JOIN `email_actions` ON `email_log`.`action`=`email_actions`.`id`" .
      " LEFT JOIN (`gpg_keys`" .
        " JOIN `persons` ON `gpg_keys`.`owner`=`persons`.`id`" .
      ") ON `email_log`.`gpg_key`=`gpg_keys`.`id`";
  }

  if (isset($_GET["from"]))
    $min_time = $_GET["from"];
  elseif ($to_show == "email")
    $min_time = "1 00:00:00";
  else
    $min_time = "00:42:00";

  $query = "SELECT ";
  foreach ($columns as $name => $column)
    $query .= $column . " AS `".$name."`,";

  $query = substr($query,0,-1);
  $query .= " FROM `" . $to_show . "_log`" . $join .
    " WHERE TIMEDIFF((" .
  // NOW() is wrong here - due to differing time zones O.o
      "SELECT MAX(`l`.`date`) FROM `" . $to_show . "_log` AS `l`" .
    "),`" . $to_show . "_log`.`date`) < from_base64(\"" . base64_encode( $min_time ) . "\")" .
    $filter .
    " ORDER BY `" . $to_show . "_log`.`date` DESC";

  $result = mysql_run_query($query);

?>
<html>
  <head>
    <title><?php print $to_show; ?>-log</title>
    <link rel="stylesheet" type="text/css" href="/static/style.css">
  </head>
  <body>
    <table>
      <tr>
<?php
  foreach ($columns as $label => $column) {
    print "        <th>\n";
    print "          " . $label . "\n";
    print "        </th>\n";
  }
?>
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
