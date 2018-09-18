<?php
require_once "../init.php";

require_once BASE . "/lib/helper.php";
require_once BASE . "/lib/mysql.php";
require_once BASE . "/lib/style.php";

$filter = " WHERE ";

if (isset($_GET["invq"]))
  $filter .= "NOT ";

if (isset($_GET["q"]))
  $filter .= "`ba_q`.`pkgbase` LIKE from_base64(\"".base64_encode("%".$_GET["q"]."%")."\")";
else
  $filter .= "1";

$multi_select_search_criteria = array(
  "arch" => array(
    "name" => "arch",
    "title" => "CPU architecture",
    "label" => "Arch",
    "source_table" => "architectures",
    "query_pre" => "`ba_q`.`arch` IN (",
    "query_in_pre" => "\"",
    "query_in_post" => "\",",
    "query_post" => "\"\")",
    "values" => array()
  ),
  "failures" => array(
    "name" => "failures",
    "title" => "Fail Reasons",
    "label" => "Failures",
    "source_table" => "fail_reasons",
    "query_pre" => "(0",
    "query_in_pre" => " OR `fr_q`.`fail_reasons_raw` LIKE \"%,",
    "query_in_post" => ",%\"",
    "query_post" => ")",
    "values" => array()
  )
);

foreach ( $multi_select_search_criteria as $criterium => $content ) {
  $result = mysql_run_query(
    "SELECT `name` FROM `" . $content["source_table"] . "` ORDER BY `name`"
  );
  while ($row = $result -> fetch_assoc())
    $multi_select_search_criteria[$criterium]["values"][] = $row["name"];
}

foreach ( $multi_select_search_criteria as $criterium ) {
  if (isset($_GET[$criterium["name"]])) {
    $filter .= " AND " . $criterium["query_pre"];
    foreach ($criterium["values"] as $value)
      if (strpos("&" . $_SERVER["QUERY_STRING"] . "&", "&" . $criterium["name"] . "=" . $value . "&") !== false)
        $filter .= $criterium["query_in_pre"] . $value . $criterium["query_in_post"];
    $filter .= $criterium["query_post"];
  }
}

$single_select_search_criteria = array(
  "broken" => array(
    "name" => "broken",
    "label" => "Is Broken",
    "title" => "is broken",
    "options" => array(
      "All" => "1",
      "Broken" => "(`ba_q`.`is_broken` OR `ba_q`.`is_blocked` IS NOT NULL)",
      "Not Broken" => "NOT (`ba_q`.`is_broken` OR `ba_q`.`is_blocked` IS NOT NULL)"
    )
  ),
  "next" => array(
    "name" => "next",
    "label" => "Can Be Built",
    "title" => "can be built",
    "options" => array(
      "All" => "1",
      "Can" => "(`l_q`.`loops` IS NOT NULL OR (`rd_q`.`run_dependencies_pending` IS NULL AND `md_q`.`make_dependencies_pending` IS NULL))",
      "Can't" => "NOT (`l_q`.`loops` IS NOT NULL OR (`rd_q`.`run_dependencies_pending` IS NULL AND `md_q`.`make_dependencies_pending` IS NULL))"
    )
  )
);

foreach ($single_select_search_criteria as $criterium)
  if (isset($_GET[$criterium["name"]]) &&
    isset($criterium["options"][$_GET[$criterium["name"]]]))
    $filter .= " AND " . $criterium["options"][$_GET[$criterium["name"]]];

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
    "mysql_query" => "IFNULL(`rd_q`.`run_dependencies_pending`,0)+IFNULL(`md_q`.`make_dependencies_pending`,0)",
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
    "mysql_query" => "`fr_q`.`fail_reasons_print`",
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
    "COUNT(DISTINCT `dependency_bp`.`build_assignment`) AS `run_dependencies_pending`" .
    " FROM `binary_packages` AS `dependent_bp`" .
    " JOIN `dependencies` ON `dependencies`.`dependent` = `dependent_bp`.`id`" .
    " JOIN `dependency_types` ON `dependencies`.`dependency_type` = `dependency_types`.`id`" .
    " JOIN `install_target_providers` ON `install_target_providers`.`install_target` = `dependencies`.`depending_on`" .
    " JOIN `binary_packages` AS `dependency_bp` ON `dependency_bp`.`id` = `install_target_providers`.`package`" .
    " JOIN `binary_packages_in_repositories` ON `dependency_bp`.`id` = `binary_packages_in_repositories`.`package`" .
    " JOIN `repositories` ON `binary_packages_in_repositories`.`repository` = `repositories`.`id`" .
    " WHERE `dependency_bp`.`build_assignment` != `dependent_bp`.`build_assignment`" .
    " AND `dependency_types`.`relevant_for_building`" .
    " AND `dependency_types`.`relevant_for_binary_packages`" .
    " AND `repositories`.`name`=\"build-list\"" .
    " GROUP BY `dependent_bp`.`build_assignment`" .
  ") AS `rd_q` ON `rd_q`.`build_assignment`=`ba_q`.`id`" .
  " LEFT JOIN" .
  " (" .
    "SELECT " .
    "`dependent_bp`.`build_assignment`," .
    "COUNT(DISTINCT `dependencies`.`id`) AS `make_dependencies_pending`" .
    " FROM `binary_packages` AS `dependent_bp`" .
    " JOIN `dependencies` ON `dependencies`.`dependent` = `dependent_bp`.`id`" .
    " JOIN `dependency_types` ON `dependencies`.`dependency_type` = `dependency_types`.`id`" .
    " WHERE NOT EXISTS(" .
      "SELECT 1 FROM `install_target_providers`" .
      " JOIN `binary_packages` AS `dependency_bp` ON `dependency_bp`.`id` = `install_target_providers`.`package`" .
      " JOIN `binary_packages_in_repositories` ON `dependency_bp`.`id` = `binary_packages_in_repositories`.`package`" .
      " JOIN `repositories` ON `binary_packages_in_repositories`.`repository` = `repositories`.`id`" .
      " WHERE `install_target_providers`.`install_target` = `dependencies`.`depending_on`" .
      " AND `repositories`.`is_on_master_mirror`" .
    ")" .
    " AND `dependency_types`.`relevant_for_building`" .
    " AND NOT `dependency_types`.`relevant_for_binary_packages`" .
    " GROUP BY `dependent_bp`.`build_assignment`" .
  ") AS `md_q` ON `md_q`.`build_assignment`=`ba_q`.`id`" .
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
    ") AS `fail_reasons_print`," .
    "CONCAT(" .
      "\",\"," .
      "GROUP_CONCAT(" .
        "`fail_reasons`.`name`" .
      ")," .
      "\",\"" .
    ") AS `fail_reasons_raw`" .
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
  $filter .
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

print_header("List of Package Builds");

?>
      <a href="https://buildmaster.archlinux32.org/build-logs/">build logs</a>
      <div id="pkglist-search" class="box filter-criteria">
        <h2>Package Build Search</h2>
        <form id="pkg-search" method="get" action="/buildmaster/build-list.php">
          <p><input id="id_sort" name="sort" type="hidden" /></p>
          <fieldset>
            <legend>Enter search criteria</legend>
<?php

foreach ($multi_select_search_criteria as $criterium) {
  print "            <div>\n";
  print "              <label for=\"id_" . $criterium["name"] . "\" title=\"Limit results to a specific " . $criterium["title"] . "\">";
  print $criterium["label"];
  print "</label>\n";
  print "              <select multiple=\"multiple\" id=\"id_" . $criterium["name"] . "\" name=\"" . $criterium["name"] . "\">\n";
  foreach ($criterium["values"] as $value) {
    print "                <option value=\"" . $value . "\"";
    if (strpos( "&" . $_SERVER["QUERY_STRING"] . "&", "&" . $criterium["name"] . "=" . $value . "&") !== false)
      print " selected=\"selected\"";
    print ">" . $value . "</option>\n";
  }
  print "              </select>\n";
  print "            </div>\n";
}

?>
            <div>
              <label for="id_q" title="Enter keywords as desired">Keywords</label>
              <input id="id_q" name="q" size="30" type="text" <?php
if (isset($_GET["q"]))
  print "value=\"".$_GET["q"]."\"";
?>/><br>
              <input id="id_invq" name="invq" type="checkbox" value="invq" title="list all non-matching package builds"<?php
if (isset($_GET["invq"]))
  print " checked";
?>>
              invert match
            </div>
<?php

foreach ($single_select_search_criteria as $criterium) {
  print "            <div>\n";
  print "              <label for=\"id_";
  print $criterium["name"];
  print "\" title=\"Limit results based on ";
  print $criterium["title"];
  print "\">";
  print $criterium["label"];
  print "</label><select id=\"id_";
  print $criterium["name"];
  print "\" name=\"";
  print $criterium["name"];
  print "\">\n";
  foreach ($criterium["options"] as $label => $option) {
    print "                <option value=\"";
    if ($label != "All")
      print $label;
    print "\"";
    if ($_GET[$criterium["name"]]==$label)
      print " selected=\"selected\"";
    print ">" . $label . "</option>\n";
  }
  print "              </select>\n";
  print "            </div>\n";
}
?>
            <div>
              <label>&nbsp;</label>
              <input title="Search for packages using this criteria" type="submit" value="Search">
            </div>
          </fieldset>
        </form>
      </div>
<?php

if ($count > 0) {

?>
      <div id="pkglist-results" class="box">
        <table class="results">
          <thead>
            <tr>
<?php

foreach ($columns as $column) {

  print "            <th>\n";
  print "              <a href=\"?";
  print substr(
    str_replace(
      "&sort=".$_GET["sort"]."&",
      "&",
      "&" . $_SERVER["QUERY_STRING"] . "&"
    ),
    1
  ) . "sort=";
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
