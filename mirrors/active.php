<?php
require_once "../init.php";

require_once BASE . "/lib/mysql.php";
require_once BASE . "/lib/format.php";

$result = mysql_run_query(
  "SELECT " .
  "`mirror_statuses`.`protocol`," .
  "`mirror_statuses`.`url`," .
  "`mirror_statuses`.`country`," .
  "`mirror_statuses`.`country_code`," .
  "`mirror_statuses`.`last_sync`," .
  "`mirror_statuses`.`start`," .
  "`mirror_statuses`.`isos`," .
  "`mirror_statuses`.`ipv4`," .
  "`mirror_statuses`.`ipv6`," .
  "`mirror_statuses`.`active`," .
  "(`mirror_statuses`.`active` AND (`mirror_statuses`.`start` > UNIX_TIMESTAMP(NOW()) - 3600)) AS `recently_active`" .
  " FROM `mirror_statuses`" .
  " JOIN (" .
    "SELECT " .
    "`mirror_statuses`.`url`," .
    "MAX(`mirror_statuses`.`start`) AS `start`" .
    " FROM `mirror_statuses` GROUP BY `url`" .
  ") AS `max_mirror`" .
  " ON `mirror_statuses`.`url`=`max_mirror`.`url`" .
  " AND `mirror_statuses`.`start`=`max_mirror`.`start`" .
  " ORDER BY `mirror_statuses`.`url`"
);

while($row = $result->fetch_assoc()) {
  foreach (array(
    "start",
    "isos",
    "ipv4",
    "ipv6",
    "active"
  ) as $key)
    $row[$key] = floatval($row[$key]);
  foreach (array(
    "start",
    "last_sync"
  ) as $key)
    $row[$key] = gmdate("Y-m-d\TH:i:s\Z", $row[$key]);
  $content[] = $row;
}

export_as_requested($content);
