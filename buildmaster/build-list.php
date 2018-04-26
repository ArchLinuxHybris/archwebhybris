<?php

include "lib/mysql.php";
include "lib/style.php";

if (isset($_GET["show"]))
  $to_show=$_GET["show"];
else
  $to_show="all";

if ($to_show == "all")
  $match = "";
elseif ($to_show == "broken")
  $match = " AND (`build_assignments`.`is_broken` OR `build_assignments`.`is_blocked` IS NOT NULL)";
elseif ($to_show == "next")
  $match = "";
else
  die_500("Unknown parameter for \"show\".");

$result = mysql_run_query(
  "SELECT DISTINCT " .
  "`build_assignments`.`id`," .
  "`build_assignments`.`is_blocked`," .
  "`package_sources`.`pkgbase`," .
  "`package_sources`.`git_revision`," .
  "`package_sources`.`mod_git_revision`," .
  "`package_sources`.`uses_upstream`," .
  "`package_sources`.`uses_modification`," .
  "`package_sources`.`commit_time`," .
  "`upstream_repositories`.`name` AS `package_repository`," .
  "`git_repositories`.`name` AS `git_repository`," .
  "`architectures`.`name` AS `arch`," .
  "EXISTS (SELECT 1 " .
    "FROM `binary_packages` AS `broken_bin` " .
    "JOIN `dependencies` ON `dependencies`.`dependent` = `broken_bin`.`id` " .
    "JOIN `install_target_providers` ON `install_target_providers`.`install_target` = `dependencies`.`depending_on` " .
    "JOIN `binary_packages` `to_be_built` ON `to_be_built`.`id` = `install_target_providers`.`package` " .
    "JOIN `repositories` ON `to_be_built`.`repository` = `repositories`.`id` " .
    "WHERE `broken_bin`.`build_assignment`=`build_assignments`.`id` ".
    "AND `repositories`.`name`=\"build-list\" " .
    "AND `to_be_built`.`build_assignment`!=`build_assignments`.`id`" .
  ") AS `dependencies_pending`," .
  "(SELECT count(1) " .
    "FROM `build_dependency_loops` " .
    "WHERE `build_dependency_loops`.`build_assignment`=`build_assignments`.`id`" .
  ") AS `loops`, " .
  "`build_slaves`.`name` AS `build_slave` " .
  "FROM `build_assignments` " .
  "JOIN `architectures` ON `build_assignments`.`architecture` = `architectures`.`id` " .
  "JOIN `package_sources` ON `build_assignments`.`package_source` = `package_sources`.`id` " .
  "JOIN `upstream_repositories` ON `package_sources`.`upstream_package_repository` = `upstream_repositories`.`id` " .
  "JOIN `git_repositories` ON `upstream_repositories`.`git_repository`=`git_repositories`.`id` " .
  "JOIN `binary_packages` ON `binary_packages`.`build_assignment` = `build_assignments`.`id` " .
  "JOIN `repositories` ON `binary_packages`.`repository` = `repositories`.`id` " .
  "LEFT JOIN `build_slaves` ON `build_slaves`.`currently_building`=`build_assignments`.`id` " .
  "WHERE `repositories`.`name`=\"build-list\"" . $match
);

if ($result -> num_rows > 0) {

  $count = 0;

  while($row = $result->fetch_assoc()) {

    if (($to_show == "next") &&
      ($row["loops"]==0) &&
      ($row["dependencies_pending"]==1))
      continue;

    $fail_result = mysql_run_query(
      "SELECT " .
      "`fail_reasons`.`name`, " .
      "`failed_builds`.`log_file` " .
      "FROM `failed_builds` " .
      "JOIN `fail_reasons` ON `failed_builds`.`reason`=`fail_reasons`.`id` " .
      "WHERE `failed_builds`.`build_assignment`=".$row["id"]." " .
      "ORDER BY `failed_builds`.`date`"
    );

    unset($reasons);
    $rows[$count]["trials"] = $fail_result -> num_rows;
    if ($rows[$count]["trials"] > 0) {
      while($fail_row = $fail_result->fetch_assoc()) {
        $reasons[$fail_row["name"]] = $fail_row["log_file"];
      }
    }
    if (isset($reasons)) {
      $to_print="";
      foreach ($reasons as $reason => $last_log) {
        $to_print= $to_print .
          ", <a href=\"https://buildmaster.archlinux32.org/build-logs/error/" .
          $last_log .
          "\">" .
          $reason .
          "</a>";
      }
      $rows[$count]["fail_reasons"]=substr($to_print,2);
    } else {
      $rows[$count]["fail_reasons"]="&nbsp;";
    }

    $rows[$count]["loops"] = $row["loops"];
    $rows[$count]["pkgbase"] = $row["pkgbase"];
    if ($row["dependencies_pending"]=="0")
      $rows[$count]["pkgbase_print"] = $rows[$count]["pkgbase"];
    else
      $rows[$count]["pkgbase_print"] = "(" . $rows[$count]["pkgbase"] . ")";
    $rows[$count]["pkgbase_print"] =
      "<a href=\"/buildmaster/dependencies.php?b=" .
      $rows[$count]["pkgbase"] .
      "&r=build-list\">" .
      $rows[$count]["pkgbase_print"] .
      "</a>";
    if ($row["uses_upstream"]) {
      $rows[$count]["git_revision"] =
        "<a href=\"https://git.archlinux.org/svntogit/" .
        $row["git_repository"] . ".git/tree/" .
        $row["pkgbase"] . "/repos/" .
        $row["package_repository"] . "-";
      if ($row["arch"]=="any")
        $rows[$count]["git_revision"] =
          $rows[$count]["git_revision"] . "any";
      else
        $rows[$count]["git_revision"] =
          $rows[$count]["git_revision"] . "x86_64";
      $rows[$count]["git_revision"] =
        $rows[$count]["git_revision"] . "?id=" .
        $row["git_revision"];
      $rows[$count]["git_revision"] =
        $rows[$count]["git_revision"] . "\">" .
        $row["git_revision"] . "</a>";
    } else
      $rows[$count]["git_revision"] = $row["git_revision"];
    if ($row["uses_modification"])
      $rows[$count]["mod_git_revision"] =
        "<a href=\"https://github.com/archlinux32/packages/tree/" .
        $row["mod_git_revision"] . "/" .
        $row["package_repository"] . "/" .
        $row["pkgbase"] . "\">" .
        $row["mod_git_revision"] . "</a>";
    else
      $rows[$count]["mod_git_revision"] = $row["mod_git_revision"];
    $rows[$count]["package_repository"] = $row["package_repository"];
    $rows[$count]["commit_time"] = $row["commit_time"];
    if ($row["is_blocked"]=="") {
      $rows[$count]["is_blocked"]="&nbsp;";
    }
    else {
      $rows[$count]["is_blocked"] = preg_replace(
        array (
          "/FS32#(\\d+)/",
          "/FS#(\\d+)/"
        ),
        array (
          "<a href=\"https://bugs.archlinux32.org/index.php?do=details&task_id=$1\">$0</a>",
          "<a href=\"https://bugs.archlinux.org/task/$1\">$0</a>"
        ),
        $row["is_blocked"]
      );
    }
    if (isset($row["build_slave"]))
      $rows[$count]["build_slave"] = $row["build_slave"];
    else
      $rows[$count]["build_slave"] = "&nbsp;";
    $count++;
  }

}

$columns = array(
  "package" => "pkgbase_print",
  "git revision" => "git_revision",
  "modification git revision" => "mod_git_revision",
  "package repository" => "package_repository",
  "commit time" => "commit_time",
  "compilations" => "trials",
  "loops" => "loops",
  "build error" => "fail_reasons",
  "blocked" => "is_blocked",
  "handed out to" => "build_slave"
);

print_header("List of " . strtoupper(substr($to_show,0,1)) . substr($to_show,1) . " Package Builds");

show_warning_on_offline_slave();

print "<a href=\"https://buildmaster.archlinux32.org/build-logs/\">build logs</a><br>\n";

if ($count > 0) {

  usort(
    $rows,
    function (array $a, array $b) {
      if ($a["trials"] < $b["trials"])
        return -1;
      if ($a["trials"] > $b["trials"])
        return 1;
      return strcmp($a["pkgbase"],$b["pkgbase"]);
    }
  );

?>
      <div id="pkglist-results" class="box">
        <table class="results">
          <thead>
            <tr>
<?php

foreach ($columns as $title => $content) {

  print "            <th>\n";
  print "              " . $title . "\n";
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

  foreach ($columns as $title => $content) {

    print "              <td>\n";
    print "                " . $row[$content] . "\n";
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

print_footer("Copyright © 2018 <a href=\"mailto:arch@eckner.net\" title=\"Contact Erich Eckner\">Erich Eckner</a>.");
