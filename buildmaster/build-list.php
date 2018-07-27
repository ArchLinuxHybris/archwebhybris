<?php
require_once "../init.php";

require_once BASE . "/lib/helper.php";
require_once BASE . "/lib/mysql.php";
require_once BASE . "/lib/style.php";

if (isset($_GET["show"]))
  $to_show=$_GET["show"];
else
  $to_show="all";

$to_shows = array(
  "all" => "",
  "broken" => " WHERE (`ba_q`.`is_broken` OR `ba_q`.`is_blocked` IS NOT NULL)",
  "next" => " WHERE (`loops` IS NOT NULL OR `dependencies_pending` IS NULL)"
);

$columns = array(
  "priority" => array(
    "label" => "Priority",
    "mysql_name" => "priority",
    "mysql_query" => "`ba_q`.`priority`",
    "sort" => "priority",
    "title" => "priority"
  ),
  "deps" => array(
    "label" => "Deps",
    "mysql_name" => "dependencies_pending",
    "mysql_query" => "IFNULL(`d_q`.`dependencies_pending`,0)",
    "sort" => "deps",
    "title" => "number of dependencies on the build-list"
  ),
  "arch" => array(
    "label" => "Arch",
    "mysql_name" => "arch",
    "mysql_query" => "`ba_q`.`arch`",
    "sort" => "arch",
    "title" => "arch"
  ),
  "pkgbase" => array(
    "label" => "Package",
    "mysql_name" => "pkgbase_print",
    "mysql_query" =>
      "CONCAT(" .
        "\"<a href=\\\"/buildmaster/dependencies.php?b=\"," .
        "`ba_q`.`pkgbase`," .
        "\"&r=build-list\\\">\"," .
        "`ba_q`.`pkgbase`," .
        "\"</a>\"" .
      ")",
    "sort" => "pkgbase",
    "title" => "package"
  ),
  "git_rev" => array(
    "label" => "Git Revision",
    "mysql_name" => "git_revision_print",
    "mysql_query" =>
      "IF(`ba_q`.`uses_upstream`," .
        "CONCAT(" .
          "\"<a href=\\\"https://git.archlinux.org/svntogit/\"," .
          "`ba_q`.`git_repository`," .
          "\".git/tree/\"," .
          "`ba_q`.`pkgbase`," .
          "\"/repos/\"," .
          "`ba_q`.`package_repository`," .
          "\"-\"," .
          "IF(`ba_q`.`arch`=\"any\",\"any\",\"x86_64\")," .
          "\"?id=\"," .
          "`ba_q`.`git_revision`," .
          "\"\\\">\"," .
          "`ba_q`.`git_revision`," .
          "\"</a>\"" .
        ")," .
        "`ba_q`.`git_revision`" .
      ")",
    "sort" => "git_rev",
    "title" => "revision hash of upstream git repository"
  ),
  "mod_git_rev" => array(
    "label" => "Modification Git Revision",
    "mysql_name" => "mod_git_revision_print",
    "mysql_query" =>
      "IF(`ba_q`.`uses_modification`," .
        "CONCAT(" .
          "\"<a href=\\\"" .
          git_url(
            "packages",
            "tree",
            "\",`ba_q`.`mod_git_revision`,\"",
            "\",`ba_q`.`package_repository`,\"/\",`ba_q`.`pkgbase`,\"",
            null,
            true
          ) .
          "\\\">\"," .
          "`ba_q`.`mod_git_revision`," .
          "\"</a>\"" .
        ")," .
        "`ba_q`.`mod_git_revision`" .
      ")" ,
    "sort" => "mod_git_rev",
    "title" => "revision hash of modification git repository"
  ),
  "repo" => array(
    "label" => "Repository",
    "mysql_name" => "package_repository",
    "mysql_query" => "`ba_q`.`package_repository`",
    "sort" => "repo",
    "title" => "package repository"
  ),
  "commit_time" => array(
    "label" => "Commit Time",
    "mysql_name" => "commit_time",
    "mysql_query" => "`ba_q`.`commit_time`",
    "sort" => "commit_time",
    "title" => "commit time of the source"
  ),
  "trials" => array(
    "label" => "Compilations",
    "mysql_name" => "trials",
    "mysql_query" => "IFNULL(`t_q`.`trials`,0)",
    "sort" => "trials",
    "title" => "number of compilations"
  ),
  "loops" => array(
    "label" => "Loops",
    "mysql_name" => "loops",
    "mysql_query" => "IFNULL(`l_q`.`loops`,0)",
    "sort" => "loops",
    "title" => "number of loops"
  ),
  "failure" => array(
    "label" => "Failures",
    "mysql_name" => "fail_reasons",
    "mysql_query" => "`fr_q`.`fail_reasons`",
    "sort" => "failure",
    "title" => "reason of build failure"
  ),
  "blocked" => array(
    "label" => "Blocked",
    "mysql_name" => "is_blocked",
    "mysql_query" => "`ba_q`.`is_blocked`",
    "sort" => "blocked",
    "title" => "block reason"
  ),
  "build_slave" => array(
    "label" => "Build Slave",
    "mysql_name" => "build_slave",
    "mysql_query" => "`bs_q`.`build_slave`",
    "sort" => "build_slave",
    "title" => "whom it is handed out to"
  )
);

$match = $to_shows[$to_show];
if (!isset($_GET["sort"]))
  $_GET["sort"]="trials";

if (substr($_GET["sort"],0,1) == "-") {
  $direction = " DESC";
  $sort = substr($_GET["sort"],1);
} else {
  $direction = " ASC";
  $sort = $_GET["sort"];
}

if (isset($columns[$sort]))
  $order = "IFNULL(" . $columns[$sort]["mysql_name"] . ",0) " . $direction . ",";
else
  $order = "";

function combine_fields($cln) {
  return $cln["mysql_query"] . " AS `" . $cln["mysql_name"] . "`";
}

$result = mysql_run_query(
  "SELECT " .
  implode(",",array_map("combine_fields",$columns)) .
  " FROM" .
  " (" .
    "SELECT DISTINCT " .
    "`build_assignments`.`id`," .
    "`build_assignments`.`is_blocked`," .
    "`build_assignments`.`is_broken`," .
    "`build_assignments`.`priority`," .
    "`package_sources`.`pkgbase`," .
    "`package_sources`.`git_revision`," .
    "`package_sources`.`mod_git_revision`," .
    "`package_sources`.`uses_upstream`," .
    "`package_sources`.`uses_modification`," .
    "`package_sources`.`commit_time`," .
    "`upstream_repositories`.`name` AS `package_repository`," .
    "`git_repositories`.`name` AS `git_repository`," .
    "`architectures`.`name` AS `arch`" .
    " FROM `build_assignments`" .
    " JOIN `architectures` ON `build_assignments`.`architecture` = `architectures`.`id`" .
    " JOIN `package_sources` ON `build_assignments`.`package_source` = `package_sources`.`id`" .
    " JOIN `upstream_repositories` ON `package_sources`.`upstream_package_repository` = `upstream_repositories`.`id`" .
    " JOIN `git_repositories` ON `upstream_repositories`.`git_repository`=`git_repositories`.`id`" .
    " JOIN `binary_packages` ON `binary_packages`.`build_assignment` = `build_assignments`.`id`" .
    " JOIN `binary_packages_in_repositories` ON `binary_packages`.`id` = `binary_packages_in_repositories`.`package`" .
    " JOIN `repositories` ON `binary_packages_in_repositories`.`repository` = `repositories`.`id`" .
    " WHERE `repositories`.`name`=\"build-list\"" .
  ") AS `ba_q`".
  " LEFT JOIN" .
  " (" .
    "SELECT " .
    "`dependent_bp`.`build_assignment`," .
    "COUNT(DISTINCT `dependency_bp`.`build_assignment`) AS `dependencies_pending`" .
    " FROM `binary_packages` AS `dependent_bp`" .
    " JOIN `dependencies` ON `dependencies`.`dependent` = `dependent_bp`.`id` " .
    " JOIN `dependency_types` ON `dependencies`.`dependency_type` = `dependency_types`.`id`" .
    " JOIN `install_target_providers` ON `install_target_providers`.`install_target` = `dependencies`.`depending_on` " .
    " JOIN `binary_packages` AS `dependency_bp` ON `dependency_bp`.`id` = `install_target_providers`.`package` " .
    " JOIN `binary_packages_in_repositories` ON `dependency_bp`.`id` = `binary_packages_in_repositories`.`package` " .
    " JOIN `repositories` ON `binary_packages_in_repositories`.`repository` = `repositories`.`id` " .
    " WHERE `dependency_bp`.`build_assignment` != `dependent_bp`.`build_assignment`" .
    " AND `dependency_types`.`relevant_for_building`" .
    " AND `repositories`.`name`=\"build-list\"" .
    " GROUP BY `dependent_bp`.`build_assignment`" .
  ") AS `d_q` ON `d_q`.`build_assignment`=`ba_q`.`id`" .
  " LEFT JOIN" .
  " (" .
    "SELECT " .
    "`build_dependency_loops`.`build_assignment`," .
    "COUNT(1) AS `loops`" .
    " FROM `build_dependency_loops`" .
    " GROUP BY `build_dependency_loops`.`build_assignment`" .
  ") AS `l_q` ON `l_q`.`build_assignment`=`ba_q`.`id`" .
  " LEFT JOIN" .
  " (" .
    "SELECT " .
    "`rfb`.`build_assignment`," .
    "GROUP_CONCAT(" .
      "CONCAT(" .
        "\"<a href=\\\"https://buildmaster.archlinux32.org/build-logs/error/\"," .
        "`rfb`.`log_file`," .
        "\"\\\">\"," .
        "`fail_reasons`.`name`," .
        "\"</a>\"" .
      ")" .
      " ORDER BY `fail_reasons`.`name`" .
    ") AS `fail_reasons`" .
    " FROM (" .
      "SELECT " .
      "`failed_builds`.`build_assignment`," .
      "`failed_builds`.`reason`," .
      "MAX(`failed_builds`.`date`) AS `max_date`" .
      " FROM `failed_builds`" .
      " GROUP BY `failed_builds`.`build_assignment`,`failed_builds`.`reason`" .
    ") AS `cfb`" .
    " JOIN" .
    " (" .
      "SELECT DISTINCT " .
      "`failed_builds`.*" .
      " FROM `failed_builds`" .
      " GROUP BY `failed_builds`.`build_assignment`,`failed_builds`.`reason`,`failed_builds`.`date`" .
    ") AS `rfb`" .
    " ON `cfb`.`build_assignment`=`rfb`.`build_assignment`" .
    " AND `cfb`.`reason`=`rfb`.`reason`" .
    " AND `cfb`.`max_date`=`rfb`.`date`" .
    " JOIN `fail_reasons` ON `rfb`.`reason`=`fail_reasons`.`id`" .
    " GROUP BY `rfb`.`build_assignment`" .
  ") AS `fr_q` ON `fr_q`.`build_assignment`=`ba_q`.`id`" .
  " LEFT JOIN" .
  " (" .
    "SELECT " .
    "`failed_builds`.`build_assignment`," .
    "COUNT(`failed_builds`.`id`) AS `trials`" .
    " FROM `failed_builds`" .
    " GROUP BY `failed_builds`.`build_assignment`" .
  ") AS `t_q` ON `t_q`.`build_assignment`=`ba_q`.`id`" .
  " LEFT JOIN" .
  " (" .
    "SELECT " .
    "`build_slaves`.`currently_building`," .
    "GROUP_CONCAT(`build_slaves`.`name`) AS `build_slave`" .
    " FROM `build_slaves`" .
    " GROUP BY `build_slaves`.`currently_building`" .
  ") AS `bs_q` ON `bs_q`.`currently_building`=`ba_q`.`id`" .
  $match .
  " ORDER BY " . $order . "`trials` " . $direction . ",`dependencies_pending` " . $direction . ",`is_blocked` " . $direction . ",`pkgbase` " . $direction
);

$count = 0;

while($row = $result->fetch_assoc()) {

  foreach ($row as $name => $value) {
    if (!isset($row[$name]))
      $rows[$count][$name] = "&nbsp;";
    elseif ($name == "is_blocked")
      $rows[$count][$name] = preg_replace(
        array (
          "/FS32#(\\d+)/",
          "/FS#(\\d+)/"
        ),
        array (
          "<a href=\"https://bugs.archlinux32.org/index.php?do=details&task_id=$1\">$0</a>",
          "<a href=\"https://bugs.archlinux.org/task/$1\">$0</a>"
        ),
        $value
      );
    else
      $rows[$count][$name] = $value;
  }

  $count++;
}

print_header("List of " . strtoupper(substr($to_show,0,1)) . substr($to_show,1) . " Package Builds");

print "<a href=\"https://buildmaster.archlinux32.org/build-logs/\">build logs</a>\n";

foreach ($to_shows as $link => $dummy) {
  print "-\n";
  if ($link != $to_show)
    print "<a href=\"?show=" . $link . "\">";
  print $link . " package builds";
  if ($link != $to_show)
    print "</a>";
  print "\n";
}

if ($count > 0) {

?>
      <div id="pkglist-results" class="box">
        <table class="results">
          <thead>
            <tr>
<?php

foreach ($columns as $column) {

  print "            <th>\n";
  print "              <a href=\"?show=" . $to_show . "&sort=";
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
