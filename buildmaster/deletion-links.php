<?php
require "../init.php";
require BASE . "/lib/mysql.php";

$edges = "";
$knots = "";

mysql_run_query(
  "CREATE TEMPORARY TABLE `d_bpir` (" .
    "`id` BIGINT, " .
    "`arch` MEDIUMINT, " .
    "UNIQUE KEY `id` (`id`)" .
  ")"
);

mysql_run_query(
  "INSERT IGNORE INTO `d_bpir` (`id`,`arch`)" .
  " SELECT `binary_packages_in_repositories`.`id`,`repositories`.`architecture`" .
  " FROM `binary_packages_in_repositories`" .
  " JOIN `binary_packages` ON `binary_packages_in_repositories`.`package`=`binary_packages`.`id`" .
  " JOIN `repositories` ON `binary_packages_in_repositories`.`repository`=`repositories`.`id`" .
  " WHERE `binary_packages_in_repositories`.`is_to_be_deleted`" .
  " AND `binary_packages`.`pkgname` NOT LIKE \"lib32-%\""
);

mysql_run_query(
  "CREATE TEMPORARY TABLE `d_bpir_copy` (" .
    "`id` BIGINT, " .
    "`arch` MEDIUMINT, " .
    "UNIQUE KEY `id` (`id`)" .
  ")"
);

mysql_run_query(
  "INSERT IGNORE INTO `d_bpir_copy` (`id`,`arch`)" .
  " SELECT `d_bpir`.`id`,`d_bpir`.`arch`" .
  " FROM `d_bpir`"
);

mysql_run_query(
  "CREATE TEMPORARY TABLE `d_it` (" .
    "`id` BIGINT, " .
    "`arch` MEDIUMINT, " .
    "UNIQUE KEY `id` (`id`)" .
  ")"
);

mysql_run_query(
  "INSERT IGNORE INTO `d_it` (`id`,`arch`)" .
  " SELECT `install_target_providers`.`install_target`,`d_bpir`.`arch`" .
  " FROM `d_bpir`" .
  " JOIN `binary_packages_in_repositories` ON `binary_packages_in_repositories`.`id`=`d_bpir`.`id`" .
  " JOIN `install_target_providers` ON `binary_packages_in_repositories`.`package`=`install_target_providers`.`package`" .
  " WHERE NOT EXISTS (" .
    "SELECT 1 FROM `install_target_providers` AS `subst_itp`" .
    " JOIN `binary_packages_in_repositories` AS `subst_bpir` ON `subst_bpir`.`package`=`subst_itp`.`package`" .
    " JOIN `repositories` ON `subst_bpir`.`repository`=`repositories`.`id`" .
    " WHERE NOT `subst_bpir`.`is_to_be_deleted`" .
    " AND `subst_itp`.`install_target`=`install_target_providers`.`install_target`" .
    " AND `repositories`.`architecture`=`d_bpir`.`arch`" .
  ")"
);

$result = mysql_run_query(
  "SELECT `binary_packages_in_repositories`.`id`," .
  "`architectures`.`name` AS `arch`," .
  "`binary_packages`.`pkgname`," .
  "`repositories`.`name` AS `repo`," .
  "IF(`build_assignments`.`is_black_listed` IS NULL,\"#800000\",\"#ff0000\") AS `color`" .
  " FROM `d_bpir`" .
  " JOIN `binary_packages_in_repositories` ON `d_bpir`.`id`=`binary_packages_in_repositories`.`id`" .
  " JOIN `binary_packages` ON `binary_packages`.`id`=`binary_packages_in_repositories`.`package`" .
  " JOIN `build_assignments` ON `binary_packages`.`build_assignment`=`build_assignments`.`id`" .
  " JOIN `repositories` ON `binary_packages_in_repositories`.`repository`=`repositories`.`id`" .
  " JOIN `architectures` ON `repositories`.`architecture`=`architectures`.`id`"
);

while ($row = $result->fetch_assoc())
  $knots .=
    "\"p" .
    $row["id"] .
    "\" [label = \"" .
    $row["arch"] .
    "/" .
    $row["repo"] .
    "/" .
    $row["pkgname"] .
    "\",
    fontcolor = \"" .
    $row["color"] .
    "\"];\n";

$result = mysql_run_query(
  "SELECT `d_bpir`.`id` AS `dependent`," .
  "`dependency_types`.`name` AS `dep_type`," .
  "`itp_bpir`.`id` AS `depending_on`" .
  " FROM `d_bpir`" .
  " JOIN `binary_packages_in_repositories` ON `d_bpir`.`id`=`binary_packages_in_repositories`.`id`" .
  " JOIN `dependencies` ON `binary_packages_in_repositories`.`package`=`dependencies`.`dependent`" .
  " JOIN `dependency_types` ON `dependencies`.`dependency_type`=`dependency_types`.`id`" .
  " JOIN `install_target_providers` ON `install_target_providers`.`install_target`=`dependencies`.`depending_on`" .
  " JOIN `d_it` ON `d_it`.`id`=`install_target_providers`.`install_target`" .
  " JOIN `binary_packages_in_repositories` AS `itp_bpir` ON `itp_bpir`.`package`=`install_target_providers`.`package`" .
  " JOIN `d_bpir_copy` ON `itp_bpir`.`id`=`d_bpir_copy`.`id`" .
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

header ("Content-type: image/png");
passthru(
  "echo '" . base64_encode(
    "digraph dependencies {\n" .
    "rankdir=LR;\n" .
    "fontname=dejavu;\n" .
    $knots .
    $edges .
    "}\n"
  ) . "' | " .
  "base64 -d | " .
  "dot -Tpng -o/dev/stdout /dev/stdin"
);
