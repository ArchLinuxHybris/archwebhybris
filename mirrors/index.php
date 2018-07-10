<?php
require_once "../init.php";

require_once BASE . "/lib/mysql.php";
require_once BASE . "/lib/style.php";

$cutoff = 3600;

$sorts = array(
  "server" => array(
    "title" => "server",
    "label" => "Server",
    "mysql" => "`url`"
  ),
  "country" => array(
    "title" => "country",
    "label" => "Country",
    "mysql" => "`l_ms`.`country_code`"
  ),
  "isos" => array(
    "title" => "wether isos are available",
    "label" => "ISOs",
    "mysql" => "`l_ms`.`isos`"
  ),
  "protocols" => array(
    "title" => "available protocols",
    "label" => "Protocols",
    "mysql" => "`protocols`"
  )
);

$query =
  "SELECT " .
  "GROUP_CONCAT(`l_ms`.`protocol`) AS `protocols`," .
  "SUBSTRING(`l_ms`.`url`,LENGTH(`l_ms`.`protocol`)+4) AS `url`," .
  "`l_ms`.`country`," .
  "`l_ms`.`country_code`," .
  "`l_ms`.`isos`," .
  "`l_ms`.`ipv4`," .
  "`l_ms`.`ipv6`" .
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
  " GROUP BY `url`" .
  " ORDER BY ";

if (isset($_GET["sort"])) {
  if (isset($sorts[$_GET["sort"]]["mysql"]))
    $query .= $sorts[$_GET["sort"]]["mysql"] . ",";
  elseif (isset($sorts[substr($_GET["sort"],1)]["mysql"]))
    $query .= $sorts[substr($_GET["sort"],1)]["mysql"] . " DESC,";
}

$query .= "`url`";

$result = mysql_run_query(
  $query
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
<?php
  foreach ($sorts as $get => $sort) {
    print "              <th>\n";
    print "                <a href=\"/mirrors/?";
    print substr(str_replace(
      "&sort=".$_GET["sort"]."&",
      "&",
      "&".$_SERVER["QUERY_STRING"]."&"
    ),1)."sort=";
    if ($_GET["sort"] == $get)
      print "-";
    print $get."\" title=\"Sort package by ".$sort["title"]."\">".$sort["label"]."</a>\n";
    print "              </th>\n";
  }
?>
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
