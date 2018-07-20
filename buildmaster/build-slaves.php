<?php
require_once "../init.php";
require_once BASE . "/lib/style.php";
require_once BASE . "/lib/mysql.php";

$columns = array(
  "name" => array(
    "label" => "name",
    "mysql_name" => "name",
    "mysql_query" => "`build_slaves`.`name`",
    "sort" => "name",
    "title" => "name"
  ),
  "operator" => array(
    "label" => "operator",
    "mysql_name" => "operator",
    "mysql_query" => "`persons`.`name`",
    "sort" => "operator",
    "title" => "operator"
  ),
  "currently_building" => array(
    "label" => "currently building",
    "mysql_name" => "cb",
    "mysql_query" => "`package_sources`.`pkgbase`",
    "sort" => "currently_building",
    "title" => "pkgbase of currently building package"
  ),
  "last_connection" => array(
    "label" => "last connection",
    "mysql_name" => "lc",
    "mysql_query" => "MAX(`ssh_log`.`date`)",
    "sort" => "last_connection",
    "title" => "time of last connection"
  ),
  "building_since" => array(
    "label" => "building since",
    "mysql_name" => "bs",
    "mysql_query" => "MAX(IF(`ssh_log`.`action`=\"get-assignment\",`ssh_log`.`date`,NULL))",
    "sort" => "building_since",
    "title" => "start of build"
  ),
  "trials" => array(
    "label" => "trials",
    "mysql_name" => "trials",
    "mysql_query" => "`build_slaves`.`trials`",
    "sort" => "trials",
    "title" => "number of trials"
  ),
  "logged_lines" => array(
    "label" => "logged lines",
    "mysql_name" => "ll",
    "mysql_query" => "`build_slaves`.`logged_lines`",
    "sort" => "logged_lines",
    "title" => "number of logged lines so far"
  ),
  "last_action" => array(
    "label" => "last action",
    "mysql_name" => "la",
    "mysql_query" => "`build_slaves`.`last_action`",
    "sort" => "last_action",
    "title" => "last action"
  )
);

if (!isset($_GET["sort"]))
  $_GET["sort"]="-last_connection";

if (substr($_GET["sort"],0,1) == "-") {
  $direction = " DESC";
  $sort = substr($_GET["sort"],1);
} else {
  $direction = " ASC";
  $sort = $_GET["sort"];
}

if (isset($columns[$sort]))
  $order = "IFNULL(`sub_query`.`" . $columns[$sort]["mysql_name"] . "`,0) " . $direction . ",";
else
  $order = "";

function combine_fields($cln) {
  return $cln["mysql_query"] . " AS `" . $cln["mysql_name"] . "`";
}

$result = mysql_run_query(
  "SELECT `sub_query`.* FROM (" .
    "SELECT " .
    implode(",",array_map("combine_fields",$columns)) .
    " FROM `build_assignments`" .
    " JOIN `package_sources`" .
    " ON `build_assignments`.`package_source`=`package_sources`.`id`" .

    " RIGHT JOIN `build_slaves`" .
    " ON `build_slaves`.`currently_building`=`build_assignments`.`id`" .
    " JOIN `ssh_keys`" .
    " ON `build_slaves`.`ssh_key`=`ssh_keys`.`id`" .
    " JOIN `persons`" .
    " ON `ssh_keys`.`owner`=`persons`.`id`" .

    " LEFT JOIN `ssh_log`" .
    " ON `ssh_log`.`build_slave`=`build_slaves`.`id`" .
    " GROUP BY `build_slaves`.`id`" .
  ") AS `sub_query`" .
  " ORDER BY " . $order . "`sub_query`.`name`"
);

$count = 0;

while($row = $result->fetch_assoc()) {

  foreach ($row as $name => $value) {
    if (!isset($row[$name]))
      $rows[$count][$name] = "&nbsp;";
    else
      $rows[$count][$name] = $value;
  }
  $rows[$count]["name"] =
    "<a href=\"/buildmaster/log.php?show=ssh&slave=" .
    $row["name"] .
    "\">" .
    $row["name"] .
    "</a>";

  $count++;
}

print_header("List of Build Slaves");

if ($count > 0) {

?>
      <div id="buildslaveslist-results" class="box">
        <table class="results">
          <thead>
            <tr>
<?php

foreach ($columns as $column) {

  print "            <th>\n";
  print "              <a href=\"?sort=";
  if ($column["sort"] == $_GET["sort"])
    print "-";
  print $column["sort"] . "\" ";
  print "title=\"Sort build assignments by " . $column["title"] . "\">\n";
  print "                " . $column["label"] . "\n";
  print "              </a>\n";
  print "            </th>\n";

}

?>
            </tr>
          </thead>
          <tbody>
<?php

$oddity = "odd";

foreach($rows as $row) {

  print "            <tr class=\"" . $oddity . "\">\n";

  foreach ($columns as $column) {

    print "              <td>\n";
    print "                " . $row[$column["mysql_name"]] . "\n";
    print "              </td>\n";

  }
  print "            </tr>\n";

  if ($oddity == "odd" )
    $oddity = "even";
  else
    $oddity = "odd";

}

?>
          </tbody>
        </table>
      </div>
<?php
}

print_footer();
