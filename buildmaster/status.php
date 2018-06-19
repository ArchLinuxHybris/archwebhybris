<?php
require_once "../init.php";
include BASE . "/lib/mysql.php";
include BASE . "/lib/style.php";

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

print_header("Build Master Status");

if (isset($last_commit))
  print "      latest package source is from " . $last_commit . ".<br>\n";

if (isset($last_return))
  print "      latest built package is from " . $last_return . ".<br>\n";

if (isset($last_return))
  print "      latest package move was on " . $last_moved . ".<br>\n";

print_footer("Copyright © 2018 <a href=\"mailto:arch@eckner.net\" title=\"Contact Erich Eckner\">Erich Eckner</a>.");
