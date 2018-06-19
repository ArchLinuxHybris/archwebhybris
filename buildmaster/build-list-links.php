<?php
require "../init.php";
require BASE . "/lib/mysql.php";

$edges = "";
$knots = "";

if (!isset($_GET["raw"]))
  $limit = " LIMIT 150";

$query =
  "CREATE TEMPORARY TABLE `ba` (" .
    "`id` BIGINT, " .
    "`group` VARCHAR(256), " .
    "`color` VARCHAR(7), " .
    "UNIQUE KEY `id` (`id`), " .
    "KEY `group` (`group`)" .
  ")";
if (isset($_GET["raw"]))
  print $query . ";\n";
mysql_run_query($query);

$query =
  "INSERT IGNORE INTO `ba` (`id`,`color`)" .
  " SELECT" .
  " `build_assignments`.`id`," .
  "IF(`build_assignments`.`is_broken`,\"#ff0000\",IF(`build_assignments`.`is_blocked` IS NULL,\"#000000\",\"#800000\"))" .
  " FROM `binary_packages_in_repositories`" .
  " JOIN `binary_packages` ON `binary_packages_in_repositories`.`package`=`binary_packages`.`id`" .
  " JOIN `repositories` ON `binary_packages_in_repositories`.`repository`=`repositories`.`id`" .
  " JOIN `build_assignments` ON `binary_packages`.`build_assignment`=`build_assignments`.`id`" .
  " WHERE `repositories`.`name`=\"build-list\"" .
  $limit;
if (isset($_GET["raw"]))
  print $query . ";\n";
mysql_run_query($query);

$query =
  "CREATE TEMPORARY TABLE `ba_copy` (" .
    "`id` BIGINT, " .
    "`group` VARCHAR(256), " .
    "`color` VARCHAR(7), " .
    "UNIQUE KEY `id` (`id`)" .
  ")";
if (isset($_GET["raw"]))
  print $query . ";\n";
mysql_run_query($query);

$query =
  "INSERT IGNORE INTO `ba_copy` (`id`,`color`)" .
  " SELECT `ba`.`id`,`ba`.`color`" .
  " FROM `ba`";
if (isset($_GET["raw"]))
  print $query . ";\n";
mysql_run_query($query);

$query =
  "CREATE TEMPORARY TABLE `ba_links` (" .
    "`from` BIGINT, " .
    "`to` BIGINT, " .
    "`type` MEDIUMINT, " .
    "UNIQUE KEY `content` (`from`,`to`,`type`)" .
  ")";
if (isset($_GET["raw"]))
  print $query . ";\n";
mysql_run_query($query);

$query =
  "INSERT IGNORE INTO `ba_links` (`from`,`to`,`type`)" .
  "SELECT `i_bp`.`build_assignment`," .
  "`d_bp`.`build_assignment`," .
  "`dependencies`.`dependency_type`" .
  " FROM `ba`" .
  " JOIN `binary_packages` AS `d_bp` ON `d_bp`.`build_assignment`=`ba`.`id`" .
  " JOIN `dependencies` ON `d_bp`.`id`=`dependencies`.`dependent`" .
  " JOIN `install_target_providers` ON `install_target_providers`.`install_target`=`dependencies`.`depending_on`" .
  " JOIN `binary_packages` AS `i_bp` ON `i_bp`.`id`=`install_target_providers`.`package`" .
  " JOIN `ba_copy` ON `i_bp`.`build_assignment`=`ba_copy`.`id`" .
  " WHERE `d_bp`.`build_assignment`!=`i_bp`.`build_assignment`";
if (isset($_GET["raw"]))
  print $query . ";\n";
mysql_run_query($query);

$query =
  "CREATE TEMPORARY TABLE `ba_links_copy` (" .
    "`from` BIGINT, " .
    "`to` BIGINT, " .
    "`type` MEDIUMINT, " .
    "UNIQUE KEY `content` (`from`,`to`,`type`)" .
  ")";
if (isset($_GET["raw"]))
  print $query . ";\n";
mysql_run_query($query);

$query =
  "INSERT IGNORE INTO `ba_links_copy` (`from`,`to`,`type`)" .
  " SELECT `ba_links`.`from`,`ba_links`.`to`,`ba_links`.`type` FROM `ba_links`";
if (isset($_GET["raw"]))
  print $query . ";\n";
mysql_run_query($query);

$query =
  "UPDATE `ba`" .
  " JOIN (" .
  "SELECT" .
  " `ba_copy`.`id`," .
  "SHA2(" .
    "GROUP_CONCAT(CONCAT(" .
      "IFNULL(`ba_copy`.`color`,\"0\"),\":\"," .
      "IFNULL(`ba_links`.`to`,\"0\"),\":\"," .
      "IFNULL(`ba_links`.`type`,\"0\"),\":\"," .
      "IFNULL(`ba_links_copy`.`from`,\"0\"),\":\"," .
      "IFNULL(`ba_links_copy`.`type`,\"0\")" .
    "))" .
  ",256) AS `hash`" .
  " FROM `ba_copy`" .
  " LEFT JOIN `ba_links` ON `ba_links`.`from`=`ba_copy`.`id`" .
  " LEFT JOIN `ba_links_copy` ON `ba_links_copy`.`to`=`ba_copy`.`id`" .
  " GROUP BY `ba_copy`.`id`" .
  ") AS `grouped_ba` ON `grouped_ba`.`id`=`ba`.`id`" .
  " SET `ba`.`group`=`grouped_ba`.`hash`";
if (isset($_GET["raw"]))
  print $query . ";\n";
mysql_run_query($query);

$query =
  "UPDATE `ba_copy`" .
  " JOIN `ba` ON `ba`.`id`=`ba_copy`.`id`" .
  " SET `ba_copy`.`group`=`ba`.`group`";
if (isset($_GET["raw"]))
  print $query . ";\n";
mysql_run_query($query);

$query =
  "SELECT MAX(`build_assignments`.`id`) AS `id`," .
  "GROUP_CONCAT(CONCAT(" .
  "`architectures`.`name`,\"/\"," .
  "`package_sources`.`pkgbase`" .
  ") SEPARATOR \",\n\") AS `name`," .
  " `ba`.`color`" .
  " FROM `ba`" .
  " JOIN `build_assignments` ON `ba`.`id`=`build_assignments`.`id`" .
  " JOIN `package_sources` ON `build_assignments`.`package_source`=`package_sources`.`id`" .
  " JOIN `architectures` ON `build_assignments`.`architecture`=`architectures`.`id`" .
  " GROUP BY `ba`.`group`";
if (isset($_GET["raw"]))
  print $query . ";\n";
$result = mysql_run_query($query);

while ($row = $result->fetch_assoc())
  $knots .=
    "\"ba" .
    $row["id"] .
    "\" [label = \"" .
    $row["name"] .
    "\",
    fontcolor = \"" .
    $row["color"] .
    "\"];\n";

$query =
  "SELECT MAX(`ba_links`.`to`) AS `dependent`," .
  "`dependency_types`.`name` AS `dep_type`," .
  "MAX(`ba_links`.`from`) AS `depending_on`" .
  " FROM `ba_links`" .
  " JOIN `dependency_types` ON `ba_links`.`type`=`dependency_types`.`id`" .
  " JOIN `ba` ON `ba_links`.`from`=`ba`.`id`" .
  " JOIN `ba_copy` ON `ba_links`.`to`=`ba_copy`.`id`" .
  " GROUP BY CONCAT(`ba`.`group`,\"-\",`ba_copy`.`group`)";
if (isset($_GET["raw"]))
  print $query . ";\n";
$result = mysql_run_query($query);

while ($row = $result->fetch_assoc()) {
  $edges .=
    "\"ba" .
    $row["depending_on"] .
    "\" -> \"ba" .
    $row["dependent"] .
    "\" [color = \"";
  switch ($row["dep_type"]) {
    case "run":
      $edges .= "#000000";
      break;
    case "make":
      $edges .= "#0000ff";
      break;
    case "link":
      $edges .= "#008000";
      break;
    case "check":
      $edges .= "#000080";
      break;
    default:
      $edges .= "#ff00ff";
  }
  $edges .=
    "#000080";
  $edges .=
    "\"];\n";
}

if (isset($_GET["raw"])) {
  print
    "digraph dependencies {\n" .
    "rankdir=LR;\n" .
    "fontname=dejavu;\n" .
    $knots .
    $edges .
    "}\n";
} else {
  $input_file = tempnam("/tmp", "build-list-links.");

  $handle = fopen($input_file,"w");
  fwrite($handle,
    "digraph dependencies {\n" .
    "rankdir=LR;\n" .
    "fontname=dejavu;\n" .
    $knots .
    $edges .
    "}\n"
  );
  fclose($handle);

  header ("Content-type: image/png");
  passthru(
    "dot -Tpng -o/dev/stdout " . $input_file
  );

  unlink($input_file);
}
