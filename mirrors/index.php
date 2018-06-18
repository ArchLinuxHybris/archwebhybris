<?php

include "lib/mysql.php";
include "lib/style.php";

$cutoff = 3600;

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
  "GROUP_CONCAT(`l_ms`.`protocol`) AS `protocols`," .
  "SUBSTRING(`l_ms`.`url`,LENGTH(`l_ms`.`protocol`)+4) AS `url`," .
  "`l_ms`.`country`," .
  "`l_ms`.`country_code`," .
  "`l_ms`.`isos`," .
  "`l_ms`.`ipv4`," .
  "`l_ms`.`ipv6`" .
  " FROM `ls`" .
  " JOIN `mirror_statuses` AS `l_ms` ON `ls`.`id`=`l_ms`.`id`" .
  " GROUP BY SUBSTRING(`l_ms`.`url`,LENGTH(`l_ms`.`protocol`)+4)"
);

$last_check = 0;
$max_count = 0;

while($row = $result->fetch_assoc())
  $rows[] = $row;

print_header("Mirror Overview");

?>
      <div id="dev-mirrorlist" class="box">
        <h2>Mirror Overview</h2>
        <table class="results">
          <thead>
            <tr>
              <th>Server</th>
              <th>Country</th>
              <th>ISOs</th>
              <th>Protocols</th>
            </tr>
          </thead>
          <tbody>
<?php

$oddity = "odd";
foreach ($rows as $row) {
  print "            <tr class=\"" . $oddity ."\">\n";
  print "              <td>\n";
  print "                " . $row["url"] . "\n";
  print "              </td>\n";
  print "              <td class=\"country\">\n";
  print "                <span class=\"fam-flag fam-flag-" . $row["country_code"] . "\" title=\"" . $row["country"] . "\">\n";
  print "                </span>\n";
  print "                  " . $row["country"] . "\n";
  print "              </td>\n";
  print "              <td>\n";
  if ($row["isos"])
    print "                Yes\n";
  else
    print "                No\n";
  print "              </td>\n";
  print "              <td class=\"wrap\">\n";
  print "                " . $row["protocols"] . "\n";
  print "              </td>\n";
  print "            </tr>\n";
  if ($oddity == "odd")
    $oddity = "even";
  else
    $oddity = "odd";
}

?>
          </tbody>
        </table>
      </div>
<?php

  print_footer();
