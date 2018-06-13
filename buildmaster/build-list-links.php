<?php

include "lib/mysql.php";

$edges = "";
$knots = "";

mysql_run_query(
  "CREATE TEMPORARY TABLE `ba` (" .
    "`id` BIGINT, " .
    "UNIQUE KEY `id` (`id`)" .
  ")"
);

mysql_run_query(
  "INSERT IGNORE INTO `ba` (`id`)" .
  " SELECT `build_assignments`.`id`" .
  " FROM `binary_packages_in_repositories`" .
  " JOIN `binary_packages` ON `binary_packages_in_repositories`.`package`=`binary_packages`.`id`" .
  " JOIN `repositories` ON `binary_packages_in_repositories`.`repository`=`repositories`.`id`" .
  " JOIN `build_assignments` ON `binary_packages`.`build_assignment`=`build_assignments`.`id`" .
  " WHERE `repositories`.`name`=\"build-list\""
);

mysql_run_query(
  "CREATE TEMPORARY TABLE `ba_copy` (" .
    "`id` BIGINT, " .
    "UNIQUE KEY `id` (`id`)" .
  ")"
);

mysql_run_query(
  "INSERT IGNORE INTO `ba_copy` (`id`)" .
  " SELECT `ba`.`id`" .
  " FROM `ba`"
);

$result = mysql_run_query(
  "SELECT `build_assignments`.`id`," .
  "`architectures`.`name` AS `arch`," .
  "`package_sources`.`pkgbase`," .
  " IF(`build_assignments`.`is_broken`,\"#ff0000\",IF(`build_assignments`.`is_blocked` IS NULL,\"#000000\",\"#800000\")) AS `color`" .
  " FROM `ba`" .
  " JOIN `build_assignments` ON `ba`.`id`=`build_assignments`.`id`" .
  " JOIN `package_sources` ON `build_assignments`.`package_source`=`package_sources`.`id`" .
  " JOIN `architectures` ON `build_assignments`.`architecture`=`architectures`.`id`"
);

while ($row = $result->fetch_assoc())
  $knots .=
    "\"ba" .
    $row["id"] .
    "\" [label = \"" .
    $row["arch"] .
    "/" .
    $row["pkgbase"] .
    "\",
    fontcolor = \"" .
    $row["color"] .
    "\"];\n";

$result = mysql_run_query(
  "SELECT DISTINCT `d_bp`.`build_assignment` AS `dependent`," .
  "`dependency_types`.`name` AS `dep_type`," .
  "`i_bp`.`build_assignment` AS `depending_on`" .
  " FROM `ba`" .
  " JOIN `binary_packages` AS `d_bp` ON `d_bp`.`build_assignment`=`ba`.`id`" .
  " JOIN `dependencies` ON `d_bp`.`id`=`dependencies`.`dependent`" .
  " JOIN `dependency_types` ON `dependencies`.`dependency_type`=`dependency_types`.`id`" .
  " JOIN `install_target_providers` ON `install_target_providers`.`install_target`=`dependencies`.`depending_on`" .
  " JOIN `binary_packages` AS `i_bp` ON `i_bp`.`id`=`install_target_providers`.`package`" .
  " JOIN `ba_copy` ON `i_bp`.`build_assignment`=`ba_copy`.`id`" .
  " WHERE `dependencies`.`dependent`!=`install_target_providers`.`package`"
);

while ($row = $result->fetch_assoc()) {
  $edges .=
    "\"b" .
    $row["depending_on"] .
    "\" -> \"b" .
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
