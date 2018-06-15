<?php

include "lib/mysql.php";

$cutoff = 86400;

mysql_run_query(
  "CREATE TEMPORARY TABLE `ls` (`id` BIGINT NOT NULL, PRIMARY KEY (`id`))"
);

mysql_run_query(
  "INSERT INTO `ls` (`id`)" .
  " SELECT `ms`.`id`" .
  " FROM `mirror_statuses` AS `ms`" .
  " WHERE NOT EXISTS (" .
    "SELECT 1 FROM `mirror_statuses` AS `n_ms`" .
    " WHERE `n_ms`.`url`=`ms`.`url`" .
    " AND `n_ms`.`start`>`ms`.`start`" .
  ") AND `ms`.`start` > UNIX_TIMESTAMP(NOW())-" . $cutoff
);

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
  "AVG(IF(`a_ms`.`active`,1,0)) AS `completion`," .
  "COUNT(1) AS `count`" .
  " FROM `ls`" .
  " JOIN `mirror_statuses` AS `l_ms` ON `ls`.`id`=`l_ms`.`id`" .
  " JOIN `mirror_statuses` AS `a_ms` ON `a_ms`.`url`=`l_ms`.`url`" .
  " AND `a_ms`.`start` > UNIX_TIMESTAMP(NOW())-" . $cutoff .
  " GROUP BY `l_ms`.`id`"
);

$last_check = 0;
$max_count = 0;

while($row = $result->fetch_assoc()) {
  $row["score"] = 
    ($row["delay"] + $row["duration_avg"] + $row["duration_stddev"]) / $row["completion"];
  $urls[] = $row;
  $last_check = max ($row["start"], $last_check);
  $max_count = max ($row["count"], $max_count);
}

$content = array(
  "cutoff" => $cutoff,
  "check_frequency" => $cutoff/$max_count,
  "num_checks" => $max_count,
  "last_check" => gmdate("Y-m-d\TH:i:s.v",$last_check), //"2018-06-15T07:25:06.741Z",
//  "version" => 3,
  "urls" => $urls
);

if (isset($_GET["json"])) {
  header ("Content-type: application/json");
  print json_encode($content,JSON_UNESCAPED_SLASHES);
} else {
  print "Unknown output format.";
}
