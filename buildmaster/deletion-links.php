<?php
require_once "../init.php";
require_once BASE . "/lib/mysql.php";

$edges = "";
$knots = "";

if (isset($_GET["show_all"]))
  $available_filter = " LEFT";
else
  $available_filter = "";

if (isset($_GET["pkgname"]))
  $filter = " AND `binary_packages`.`pkgname` REGEXP from_base64(\"" . base64_encode($_GET["pkgname"]) . "\")";
else
  $filter = "";

$memcache = new Memcache;
$memcache->connect('localhost', 11211) or die ('Memcached Connection Error');
$available_upstream_packages = $memcache->get('available_upstream_packages');
if ($available_upstream_packages === false) {
  $available_upstream_packages = explode(
    "\n",
    shell_exec(
      "find /var/lib/pacman/ -name '*.db' -exec tar -tzf {} \; " .
      "| sed -n 's,-[^-]\+-[^-]\+/$,,;T;p' " .
      "| sort -u"
    )
  );
  $memcache->set('available_upstream_packages',$available_upstream_packages,0,1800);
}

mysql_run_query(
  "CREATE TEMPORARY TABLE `available` (" .
    "`pkgname` VARCHAR(88), " .
    "UNIQUE KEY `name` (`pkgname`)" .
  ")"
);

mysql_run_query(
  "INSERT INTO `available` (`pkgname`) VALUES (\"" .
  implode(array_map("base64_encode", $available_upstream_packages), "\"),(\"") .
  "\")"
);

mysql_run_query(
  "DELETE FROM `available` WHERE `available`.`pkgname`=\"\""
);

mysql_run_query(
  "UPDATE `available` SET `available`.`pkgname`=from_base64(`available`.`pkgname`)"
);

mysql_run_query(
  "CREATE TEMPORARY TABLE `d_bpir` (" .
    "`id` BIGINT, " .
    "`group` VARCHAR(256), " .
    "`color` VARCHAR(7), " .
    "UNIQUE KEY `id` (`id`)" .
  ")"
);

mysql_run_query(
  "INSERT IGNORE INTO `d_bpir` (`id`,`color`)" .
  " SELECT" .
  " `binary_packages_in_repositories`.`id`," .
  "IF(" .
    "`available`.`pkgname` IS NULL," .
    "\"#00ff00\"," .
    "IF(" .
      "`build_assignments`.`is_black_listed` IS NULL," .
      "\"#800000\"," .
      "\"#ff0000\"" .
    ")" .
  ") AS `color`" .
  " FROM `binary_packages_in_repositories`" .
  " JOIN `binary_packages` ON `binary_packages_in_repositories`.`package`=`binary_packages`.`id`" .
  " JOIN `build_assignments` ON `binary_packages`.`build_assignment`=`build_assignments`.`id`" .
  $available_filter .
  " JOIN `available` ON `available`.`pkgname`=`binary_packages`.`pkgname`" .
  " WHERE `binary_packages_in_repositories`.`is_to_be_deleted`" .
  " AND `binary_packages`.`pkgname` NOT LIKE \"lib32-%\"" .
  $filter
);

mysql_run_query(
  "CREATE TEMPORARY TABLE `d_bpir_copy` (" .
    "`id` BIGINT, " .
    "`group` VARCHAR(256), " .
    "`color` VARCHAR(7), " .
    "UNIQUE KEY `id` (`id`)" .
  ")"
);

mysql_run_query(
  "INSERT IGNORE INTO `d_bpir_copy` (`id`,`color`)" .
  " SELECT `d_bpir`.`id`,`d_bpir`.`color`" .
  " FROM `d_bpir`"
);

mysql_run_query(
  "CREATE TEMPORARY TABLE `d_bpir_links` (" .
    "`dependent` BIGINT, " .
    "`depending_on` BIGINT, " .
    "`dep_type` SMALLINT, " .
    "UNIQUE KEY `content` (`dependent`,`depending_on`,`dep_type`)" .
  ")"
);

mysql_run_query(
  "INSERT IGNORE INTO `d_bpir_links` (`dependent`,`depending_on`,`dep_type`)" .
  " SELECT `d_bpir`.`id`," .
  " `itp_bpir`.`id`," .
  " `dependencies`.`dependency_type`" .
  " FROM `d_bpir`" .
  " JOIN `binary_packages_in_repositories` ON `d_bpir`.`id`=`binary_packages_in_repositories`.`id`" .
  " JOIN `dependencies` ON `binary_packages_in_repositories`.`package`=`dependencies`.`dependent`" .
  " JOIN `install_target_providers` ON `install_target_providers`.`install_target`=`dependencies`.`depending_on`" .
  " JOIN `binary_packages_in_repositories` AS `itp_bpir` ON `itp_bpir`.`package`=`install_target_providers`.`package`" .
  " JOIN `d_bpir_copy` ON `itp_bpir`.`id`=`d_bpir_copy`.`id`" .
  " WHERE `dependencies`.`dependent`!=`install_target_providers`.`package`"
);

mysql_run_query(
  "CREATE TEMPORARY TABLE `d_bpir_links_copy` (" .
    "`dependent` BIGINT, " .
    "`depending_on` BIGINT, " .
    "`dep_type` SMALLINT, " .
    "UNIQUE KEY `content` (`dependent`,`depending_on`,`dep_type`)" .
  ")"
);

mysql_run_query(
  "INSERT IGNORE INTO `d_bpir_links_copy` (`dependent`,`depending_on`,`dep_type`)" .
  " SELECT `d_bpir_links`.`dependent`,`d_bpir_links`.`depending_on`,`d_bpir_links`.`dep_type`" .
  " FROM `d_bpir_links`"
);

mysql_run_query(
  "UPDATE `d_bpir`" .
  " JOIN (" .
  "SELECT" .
  " `d_bpir_copy`.`id`," .
  "SHA2(" .
    "GROUP_CONCAT(CONCAT(" .
      "IFNULL(`d_bpir_copy`.`color`,\"0\"),\":\"," .
      "IFNULL(`d_bpir_links`.`depending_on`,\"0\"),\":\"," .
      "IFNULL(`d_bpir_links`.`dep_type`,\"0\"),\":\"," .
      "IFNULL(`d_bpir_links_copy`.`dependent`,\"0\"),\":\"," .
      "IFNULL(`d_bpir_links_copy`.`dep_type`,\"0\")" .
    "))" .
  ",256) AS `hash`" .
  " FROM `d_bpir_copy`" .
  " LEFT JOIN `d_bpir_links` ON `d_bpir_links`.`dependent`=`d_bpir_copy`.`id`" .
  " LEFT JOIN `d_bpir_links_copy` ON `d_bpir_links_copy`.`depending_on`=`d_bpir_copy`.`id`" .
  " GROUP BY `d_bpir_copy`.`id`" .
  ") AS `grouped_d_bpir` ON `grouped_d_bpir`.`id`=`d_bpir`.`id`" .
  " SET `d_bpir`.`group`=`grouped_d_bpir`.`hash`"
);

mysql_run_query(
  "UPDATE `d_bpir_copy`" .
  " JOIN `d_bpir` ON `d_bpir`.`id`=`d_bpir_copy`.`id`" .
  " SET `d_bpir_copy`.`group`=`d_bpir`.`group`"
);

$result = mysql_run_query(
  "SELECT MAX(`d_bpir`.`id`) AS `id`," .
  "GROUP_CONCAT(CONCAT(" .
    "`architectures`.`name`,\"/\"," .
    "`repositories`.`name`,\"/\"," .
    "`binary_packages`.`pkgname`" .
  ") SEPARATOR \",\n\") AS `name`," .
  "`d_bpir`.`color`" .
  " FROM `d_bpir`" .
  " JOIN `binary_packages_in_repositories` ON `d_bpir`.`id`=`binary_packages_in_repositories`.`id`" .
  " JOIN `binary_packages` ON `binary_packages`.`id`=`binary_packages_in_repositories`.`package`" .
  " JOIN `repositories` ON `binary_packages_in_repositories`.`repository`=`repositories`.`id`" .
  " JOIN `architectures` ON `repositories`.`architecture`=`architectures`.`id`" .
  " GROUP BY `d_bpir`.`group`"
);

while ($row = $result->fetch_assoc())
  $knots .=
    "\"p" .
    $row["id"] .
    "\" [label = \"" .
    $row["name"] .
    "\",
    fontcolor = \"" .
    $row["color"] .
    "\"];\n";

$result = mysql_run_query(
  "SELECT MAX(`d_bpir_links`.`dependent`) AS `dependent`," .
  "`dependency_types`.`name` AS `dep_type`," .
  "MAX(`d_bpir_links`.`depending_on`) AS `depending_on`" .
  " FROM `d_bpir_links`" .
  " JOIN `dependency_types` ON `d_bpir_links`.`dep_type`=`dependency_types`.`id`" .
  " JOIN `d_bpir` ON `d_bpir`.`id`=`d_bpir_links`.`dependent`" .
  " JOIN `d_bpir_copy` ON `d_bpir_copy`.`id`=`d_bpir_links`.`depending_on`" .
  " GROUP BY CONCAT(`d_bpir`.`group`,\"-\",`d_bpir_copy`.`group`)"
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
  "timeout 30 dot -Tpng -o/dev/stdout /dev/stdin"
);
