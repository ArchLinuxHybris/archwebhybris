<?php

include "lib/mysql.php";

$edges = "";
$knots = "";

mysql_run_query(
  "CREATE TEMPORARY TABLE `d_bp` (" .
    "`id` BIGINT, " .
    "UNIQUE KEY `id` (`id`)" .
  ")"
);

mysql_run_query(
  "INSERT IGNORE INTO `d_bp` (`id`)" .
  " SELECT `binary_packages`.`id`" .
  " FROM `binary_packages`" .
  " WHERE `binary_packages`.`is_to_be_deleted`" .
  " AND `binary_packages`.`pkgname` NOT LIKE \"lib32-%\""
);

mysql_run_query(
  "CREATE TEMPORARY TABLE `d_bp_copy` (" .
    "`id` BIGINT, " .
    "UNIQUE KEY `id` (`id`)" .
  ")"
);

mysql_run_query(
  "INSERT IGNORE INTO `d_bp_copy` (`id`)" .
  " SELECT `d_bp`.`id`" .
  " FROM `d_bp`"
);

mysql_run_query(
  "CREATE TEMPORARY TABLE `d_it` (" .
    "`id` BIGINT, " .
    "UNIQUE KEY `id` (`id`)" .
  ")"
);

mysql_run_query(
  "INSERT IGNORE INTO `d_it` (`id`)" .
  " SELECT `install_target_providers`.`install_target`" .
  " FROM `install_target_providers`" .
  " JOIN `d_bp` ON `d_bp`.`id`=`install_target_providers`.`package`" .
  " WHERE NOT EXISTS (" .
    "SELECT 1 FROM `install_target_providers` AS `subst_itp`" .
    " JOIN `binary_packages` AS `subst_bp` ON `subst_itp`.`package`=`subst_bp`.`id`" .
    " WHERE NOT `subst_bp`.`is_to_be_deleted`" .
    " AND `subst_itp`.`install_target`=`install_target_providers`.`install_target`" .
  ")"
);

$result = mysql_run_query(
  "SELECT `binary_packages`.`id`," .
  "`binary_packages`.`pkgname`," .
  "`repositories`.`name` AS `repo`," .
  "IF(`build_assignments`.`is_black_listed` IS NULL,\"#800000\",\"#ff0000\") AS `color`" .
  " FROM `binary_packages`" .
  " JOIN `d_bp` ON `d_bp`.`id`=`binary_packages`.`id`" .
  " JOIN `build_assignments` ON `binary_packages`.`build_assignment`=`build_assignments`.`id`" .
  " JOIN `repositories` ON `binary_packages`.`repository`=`repositories`.`id`"
);

while ($row = $result->fetch_assoc())
  $knots .=
    "\"p" .
    $row["id"] .
    "\" [label = \"" .
    $row["repo"] .
    "/" .
    $row["pkgname"] .
    "\",
    fontcolor = \"" .
    $row["color"] .
    "\"];\n";

$result = mysql_run_query(
  "SELECT `dependencies`.`dependent`," .
  "`dependency_types`.`name` AS `dep_type`," .
  "`install_target_providers`.`package` AS `depending_on`" .
  " FROM `dependencies`" .
  " JOIN `d_bp` ON `d_bp`.`id`=`dependencies`.`dependent`" .
  " JOIN `dependency_types` ON `dependencies`.`dependency_type`=`dependency_types`.`id`" .
  " JOIN `install_target_providers` ON `install_target_providers`.`install_target`=`dependencies`.`depending_on`" .
  " JOIN `d_it` ON `d_it`.`id`=`install_target_providers`.`install_target`" .
  " WHERE `dependencies`.`dependent`!=`install_target_providers`.`package`"
);

while ($row = $result->fetch_assoc()) {
  $edges .=
    "\"p" .
    $row["depending_on"] .
    "\" -> \"p" .
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

$knots = str_replace("\$","\\\$",$knots);
$edges = str_replace("\$","\\\$",$edges);


header ("Content-type: image/png");
passthru(
  "dot -Tpng -o/dev/stdout /dev/stdin <<EOF\n" .
  "digraph dependencies {\n" .
  "rankdir=LR;\n" .
  "fontname=dejavu;\n" .
  $knots .
  $edges .
  "}\n" .
  "EOF\n"
);
