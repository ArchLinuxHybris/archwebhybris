<?php
require_once "../init.php";

require_once BASE . "/lib/mysql.php";
require_once BASE . "/lib/format.php";

$cutoff = 86400;

$result = mysql_run_query(
  "SELECT " .
  "`l_ms`.`protocol`," .
  "`l_ms`.`url`," .
  "`l_ms`.`country`," .
  "`l_ms`.`country_code`," .
  "`l_ms`.`last_sync`," .
  "`l_ms`.`start`," .
  "AVG(IF(`a_ms`.`active`,(`a_ms`.`start`-`a_ms`.`last_sync`)/3600,NULL)) AS `delay`," .
  "AVG(IF(`a_ms`.`active`,(`a_ms`.`stop`-`a_ms`.`start`)/3600,NULL)) AS `duration_avg`," .
  "STD(IF(`a_ms`.`active`,(`a_ms`.`stop`-`a_ms`.`start`)/3600,NULL)) AS `duration_stddev`," .
  "`l_ms`.`isos`," .
  "`l_ms`.`ipv4`," .
  "`l_ms`.`ipv6`," .
  "`l_ms`.`active`," .
  "(`l_ms`.`active` AND (`l_ms`.`start` > UNIX_TIMESTAMP(NOW()) - 3600)) AS `recently_active`," .
  "AVG(IF(`a_ms`.`active`,1,0)) AS `completion_pct`," .
  "COUNT(1) AS `count`" .
  " FROM (" .
    "SELECT " .
    "`mirror_statuses`.`url`," .
    "MAX(`mirror_statuses`.`start`) AS `start`" .
    " FROM `mirror_statuses`" .
    " WHERE `mirror_statuses`.`start` > UNIX_TIMESTAMP(NOW())-" . $cutoff .
    " GROUP BY `mirror_statuses`.`url`" .
  ") AS `ls`" .
  " JOIN `mirror_statuses` AS `l_ms`" .
  " ON `ls`.`url`=`l_ms`.`url`" .
  " AND `ls`.`start`=`l_ms`.`start`" .
  " JOIN `mirror_statuses` AS `a_ms`" .
  " ON `a_ms`.`url`=`l_ms`.`url`" .
  " AND `a_ms`.`start` > UNIX_TIMESTAMP(NOW())-" . $cutoff .
  " GROUP BY `l_ms`.`id`"
);

$last_check = 0;
$max_count = 0;

while($row = $result->fetch_assoc()) {
  foreach (array(
    "start",
    "delay",
    "duration_avg",
    "duration_stddev",
    "completion_pct",
    "count",
    "isos",
    "ipv4",
    "ipv6",
    "active",
    "recently_active"
  ) as $key)
    $row[$key] = floatval($row[$key]);
  $row["last_sync"] = gmdate("Y-m-d\TH:i:s\Z", $row["last_sync"]);
  $row["score"] = 
    ($row["delay"] + $row["duration_avg"] + $row["duration_stddev"]) / $row["completion_pct"];
  $urls[] = $row;
  $last_check = max ($row["start"], $last_check);
  $max_count = max ($row["count"], $max_count);
}

$content = array(
  "cutoff" => $cutoff,
  "check_frequency" => $cutoff/$max_count,
  "num_checks" => $max_count,
  "last_check" => gmdate("Y-m-d\TH:i:s.v\Z",$last_check), //"2018-06-15T07:25:06.741Z",
//  "version" => 3,
  "urls" => $urls
);

export_as_requested(
  array(
    "json" => $content,
    "tsv" => $urls
  )
);
