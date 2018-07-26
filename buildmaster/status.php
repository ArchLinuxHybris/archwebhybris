<?php
require_once "../init.php";
include BASE . "/lib/mysql.php";
include BASE . "/lib/style.php";
include BASE . "/lib/converter.php";

$result = mysql_run_query(
  "SELECT MAX(`package_sources`.`commit_time`) AS `last_commit`" .
  " FROM `package_sources`"
);

if ($result -> num_rows > 0) {
  $result = $result->fetch_assoc();
  $last_commit = $result["last_commit"];
}

$result = mysql_run_query(
  "SELECT MAX(`build_assignments`.`return_date`) AS `last_return`" .
  " FROM `build_assignments`"
);

if ($result -> num_rows > 0) {
  $result = $result->fetch_assoc();
  $last_return = $result["last_return"];
}

$result = mysql_run_query(
  "SELECT MAX(`binary_packages_in_repositories`.`last_moved`) AS `last_moved`" .
  " FROM `binary_packages`" .
  " JOIN `binary_packages_in_repositories` ON `binary_packages_in_repositories`.`package`=`binary_packages`.`id`" .
  " JOIN `build_assignments` ON `binary_packages`.`build_assignment`=`build_assignments`.`id`" .
  " WHERE `binary_packages_in_repositories`.`last_moved`>`build_assignments`.`return_date`"
);

if ($result -> num_rows > 0) {
  $result = $result->fetch_assoc();
  $last_moved = $result["last_moved"];
}

$result = mysql_run_query(
  "SELECT " .
  "STDDEV(UNIX_TIMESTAMP(NOW())-UNIX_TIMESTAMP(`binary_packages_in_repositories`.`first_last_moved`)) AS `stddev`," .
  "AVG(UNIX_TIMESTAMP(NOW())-UNIX_TIMESTAMP(`binary_packages_in_repositories`.`first_last_moved`)) AS `avg`" .
  " FROM `binary_packages`" .
  " JOIN (" .
    "SELECT " .
    "`binary_packages_in_repositories`.`package`," .
    "MIN(`binary_packages_in_repositories`.`last_moved`) AS `first_last_moved`" .
    " FROM `binary_packages_in_repositories`" .
    " JOIN `repositories`" .
    " ON `binary_packages_in_repositories`.`repository`=`repositories`.`id`" .
    " JOIN `repository_stabilities`" .
    " ON `repositories`.`stability`=`repository_stabilities`.`id`" .
    " WHERE `repository_stabilities`.`name`=\"testing\"" .
    " GROUP BY `binary_packages_in_repositories`.`package`" .
  ") AS `binary_packages_in_repositories`" .
  " ON `binary_packages_in_repositories`.`package`=`binary_packages`.`id`" .
  " WHERE NOT `binary_packages`.`has_issues`" .
  " AND NOT `binary_packages`.`is_tested`"
);

if ($result -> num_rows > 0) {
  $result = $result->fetch_assoc();
  foreach ($result as $key => $val)
    $testing[$key] = format_time_duration($val);
}

print_header("Build Master Status");

if (isset($last_commit))
  print "      latest package source is from " . $last_commit . ".<br>\n";

if (isset($last_return))
  print "      latest built package is from " . $last_return . ".<br>\n";

if (isset($last_moved))
  print "      latest package move was on " . $last_moved . ".<br>\n";

if (isset($testing))
  print "      age of testing-packages: " . 
    $testing["avg"] . " &pm; " . 
    $testing["stddev"] . ".<br>\n";

print_footer();
