<?php
require_once "../init.php";

require_once BASE . "/lib/mysql.php";
require_once BASE . "/lib/style.php";
require_once BASE . "/lib/format.php";


  foreach (array("bugs","sort","del","uses_upstream","uses_modification") as $expected_param)
    if (! isset($_GET[$expected_param]))
      $_GET[$expected_param] = "";

  $multi_select_search_criteria = array(
    "arch" => array(
      "name" => "arch",
      "title" => "CPU architecture",
      "label" => "Arch",
      "table" => "architectures",
      "extra_condition" => "",
      "values" => array()
    ),
    "repo" => array(
      "name" => "repo",
      "title" => "respository",
      "label" => "Repository",
      "table" => "repositories",
      "extra_condition" => " WHERE `repositories`.`is_on_master_mirror`",
      "values" => array()
    )
  );

  foreach ( $multi_select_search_criteria as $criterium => $content ) {
    $result = mysql_run_query(
      "SELECT `name` FROM `" . $content["table"] . "`" . $content["extra_condition"] . " ORDER BY `name`"
    );
    while ($row = $result -> fetch_assoc())
      $multi_select_search_criteria[$criterium]["values"][] = $row["name"];
  }

  $filter = " WHERE 1";
  foreach ($multi_select_search_criteria as $criterium)
    if (isset($_GET[$criterium["name"]])) {
      $filter .= " AND `" . $criterium["table"] . "`.`name` IN (";
      foreach ($criterium["values"] as $value)
        if (strpos("&" . $_SERVER["QUERY_STRING"] . "&", "&" . $criterium["name"] . "=" . $value . "&") !== false)
          $filter .= "\"" . $value . "\",";
      $filter .= "\"\")";
    }

  $single_select_search_criteria = array(
    "bugs" => array(
      "name" => "bugs",
      "label" => "Bugs",
      "title" => "bug-tracker status",
      "options" => array(
        "All" => "1",
        "Bugs" => "`binary_packages`.`has_issues`",
        "No Bugs" => "NOT `binary_packages`.`has_issues`"
      )
    ),
    "del" => array(
      "name" => "del",
      "label" => "To Be Deleted",
      "title" => "to-be-deleted status",
      "options" => array(
        "All" => "1",
        "To Be Deleted" => "`binary_packages_in_repositories`.`is_to_be_deleted`",
        "Not To Be Deleted" => "NOT `binary_packages_in_repositories`.`is_to_be_deleted`"
      )
    ),
    "uses_upstream" => array(
      "name" => "uses_upstream",
      "label" => "Upstream",
      "title" => "wether upstream source exists",
      "options" => array(
        "All" => "1",
        "Uses Upstream" => "`package_sources`.`uses_upstream`",
        "Does Not Use Upstream" => "NOT `package_sources`.`uses_upstream`"
      )
    ),
    "uses_modification" => array(
      "name" => "uses_modification",
      "label" => "Modification",
      "title" => "wether modification exists",
      "options" => array(
        "All" => "1",
        "Uses Modification" => "`package_sources`.`uses_modification`",
        "Does Not Use Modification" => "NOT `package_sources`.`uses_modification`"
      )
    )
  );

  foreach ($single_select_search_criteria as $criterium)
    if (isset($_GET[$criterium["name"]]) &&
      isset($criterium["options"][$_GET[$criterium["name"]]]))
      $filter .= " AND " . $criterium["options"][$_GET[$criterium["name"]]];

  if (isset($_GET["q"])) {
    $exact_filter = " AND `binary_packages`.`pkgname` = from_base64(\"".base64_encode($_GET["q"])."\")";
    $fuzzy_filter = " AND `binary_packages`.`pkgname` LIKE from_base64(\"".base64_encode("%".$_GET["q"]."%")."\")";
  } else {
    $exact_filter = " AND 0";
    $fuzzy_filter = "";
  }

  $query = " FROM `binary_packages`" .
    " JOIN `architectures` ON `architectures`.`id`=`binary_packages`.`architecture`" .
    " JOIN `binary_packages_in_repositories` ON `binary_packages`.`id`=`binary_packages_in_repositories`.`package`" .
    " JOIN `repositories` ON `binary_packages_in_repositories`.`repository`=`repositories`.`id`" .
    " AND `repositories`.`is_on_master_mirror`" .
    " JOIN `build_assignments` ON `build_assignments`.`id`=`binary_packages`.`build_assignment`" .
    " JOIN `package_sources` ON `package_sources`.`id`=`build_assignments`.`package_source`" .
    $filter . $exact_filter .
    " ORDER BY ";

  $query .= "`binary_packages`.`pkgname`,`repositories`.`stability`,`repositories`.`name`,`architectures`.`name`";

  $result = mysql_run_query(
    "SELECT " .
    "`binary_packages`.`pkgname`," .
    "`repositories`.`name` AS `repo`," .
    "`architectures`.`name` AS `arch`," .
    "CONCAT(IF(`binary_packages`.`epoch`=\"0\",\"\",CONCAT(`binary_packages`.`epoch`,\":\"))," .
    "`binary_packages`.`pkgver`,\"-\"," .
    "`binary_packages`.`pkgrel`,\".\"," .
    "`binary_packages`.`sub_pkgrel`) AS `version`," .
    "IF(`binary_packages`.`has_issues`,1,0) AS `has_issues`," .
    "`build_assignments`.`return_date` AS `build_date`," .
    "`binary_packages_in_repositories`.`last_moved` AS `move_date`," .
    "IF(`binary_packages_in_repositories`.`is_to_be_deleted`,1,0) AS `is_to_be_deleted`" .
    $query
  );
  $exact_matches = array();
  while ($row = $result -> fetch_assoc())
    $exact_matches[] = $row;

  $sorts = array(
    "arch" => array(
      "title" => "architecture",
      "label" => "Arch",
      "mysql" => "`architectures`.`name`"
    ),
    "repo" => array(
      "title" => "repository",
      "label" => "Repo",
      "mysql" => "`repositories`.`name`"
    ),
    "pkgname" => array(
      "title" => "package name",
      "label" => "Name",
      "mysql" => "`binary_packages`.`pkgname`"
    ),
    "pkgver" => array(
      "title" => "package version",
      "label" => "Version",
      "mysql" => "CONCAT(`binary_packages`.`epoch`,\":\",`binary_packages`.`pkgver`,\"-\",`binary_packages`.`pkgrel`,\".\",`binary_packages`.`sub_pkgrel`)"
    ),
    "bugs" => array(
      "title" => "bug status",
      "label" => "Bugs",
      "mysql" => "NOT `binary_packages`.`has_issues`"
    ),
    "build_date" => array(
      "title" => "build date",
      "label" => "Build Date",
      "mysql" => "IFNULL(`build_assignments`.`return_date`,\"00-00-0000 00:00:00\")"
    ),
    "move_date" => array(
      "title" => "last update",
      "label" => "Last Updated",
      "mysql" => "IFNULL(`binary_packages_in_repositories`.`last_moved`,\"00-00-0000 00:00:00\")"
    ),
    "del" => array(
      "title" => "to be deleted",
      "label" => "Delete",
      "mysql" => "`binary_packages_in_repositories`.`is_to_be_deleted`"
    )
  );

  $query = " FROM `binary_packages`" .
    " JOIN `architectures` ON `architectures`.`id`=`binary_packages`.`architecture`" .
    " JOIN `binary_packages_in_repositories` ON `binary_packages`.`id`=`binary_packages_in_repositories`.`package`" .
    " JOIN `repositories` ON `binary_packages_in_repositories`.`repository`=`repositories`.`id`" .
    " AND `repositories`.`is_on_master_mirror`" .
    " JOIN `build_assignments` ON `build_assignments`.`id`=`binary_packages`.`build_assignment`" .
    " JOIN `package_sources` ON `package_sources`.`id`=`build_assignments`.`package_source`" .
    $filter . $fuzzy_filter .
    " ORDER BY ";

  if (isset($_GET["sort"])) {
    if (isset($sorts[$_GET["sort"]]["mysql"]))
      $query .= $sorts[$_GET["sort"]]["mysql"] . ",";
    elseif (isset($sorts[substr($_GET["sort"],1)]["mysql"]))
      $query .= $sorts[substr($_GET["sort"],1)]["mysql"] . " DESC,";
  }

  $query .= "`binary_packages`.`pkgname`,`repositories`.`stability`,`repositories`.`name`,`architectures`.`name`";

  $result = mysql_run_query(
    "SELECT COUNT(1)" . $query
  );
  $num_results = implode($result -> fetch_assoc());

  $pages = max(ceil($num_results / 100), 1);
  if (isset($_GET["page"]))
    $page = max(min($_GET["page"]+0, $pages),1);
  else
    $page = 1;

  $result = mysql_run_query(
    "SELECT " .
    "`binary_packages`.`pkgname`," .
    "`repositories`.`name` AS `repo`," .
    "`architectures`.`name` AS `arch`," .
    "CONCAT(IF(`binary_packages`.`epoch`=\"0\",\"\",CONCAT(`binary_packages`.`epoch`,\":\"))," .
    "`binary_packages`.`pkgver`,\"-\"," .
    "`binary_packages`.`pkgrel`,\".\"," .
    "`binary_packages`.`sub_pkgrel`) AS `version`," .
    "IF(`binary_packages`.`has_issues`,1,0) AS `has_issues`," .
    "`build_assignments`.`return_date` AS `build_date`," .
    "`binary_packages_in_repositories`.`last_moved` AS `move_date`," .
    "IF(`binary_packages_in_repositories`.`is_to_be_deleted`,1,0) AS `is_to_be_deleted`" .
    $query .
    " LIMIT " . (($page-1)*100) . ", 100"
  );
  $fuzzy_matches = array();
  while ($row = $result -> fetch_assoc())
    $fuzzy_matches[] = $row;

  function print_results($results) {
    $oddity="odd";
    foreach ($results as $row) {
      print "          <tr class=\"" . $oddity . "\">\n";
      print "            <td>\n";
      print "              " . $row["arch"] . "\n";
      print "            </td>\n";
      print "            <td>\n";
      print "              " . $row["repo"] . "\n";
      print "            </td>\n";
      print "            <td>\n";
      print "              <a href=\"/" . $row["repo"] . "/" . $row["arch"] . "/" . $row["pkgname"] ."/\" ";
      print "title=\"View package details for " . $row["pkgname"] . "\">" . $row["pkgname"] . "</a>\n";
      print "            </td>\n";
      print "            <td>\n";
      print "              " . $row["version"] . "\n";
      print "            </td>\n";
      print "            <td>\n";
      print "              ";
      if ($row["has_issues"])
        print "has open bug reports";
      else
        print "&nbsp;";
      print "\n";
      print "            </td>\n";
      print "            <td>\n";
      print "              ";
      if (isset($row["build_date"]))
        print $row["build_date"];
      else
        print "&nbsp;";
      print "\n";
      print "            </td>\n";
      print "            <td>\n";
      print "              ";
      if (isset($row["move_date"]))
        print $row["move_date"];
      else
        print "&nbsp;";
      print "\n";
      print "            </td>\n";
      print "            <td>\n";
      print "              ";
      if ($row["is_to_be_deleted"])
        print "to be deleted";
      else
        print "&nbsp;";
      print "\n";
      print "            </td>\n";
      print "          </tr>\n";
      if ($oddity == "odd" )
        $oddity = "even";
      else
        $oddity = "odd";
    }
  }

  function header_and_footer() {

    global $page, $pages, $num_results;

    print "        <div class=\"pkglist-stats\">\n";
    print "          <p>\n";
    print "            " . $num_results . " matching package";
    if ($num_results != 1)
      print "s";
    print " found.\n";

    if ($pages != 1)
      print "            Page " . $page . " of " . $pages . ".\n";

    print "          </p>\n";

    if ($pages != 1) {
      print "          <div class=\"pkglist-nav\">\n";
      print "            <span class=\"prev\">\n";

      print "              ";
      if ($page > 1) {
        print "<a href=\"?";
        print substr(str_replace(
          "&page=".$page."&",
          "&",
          "&".$_SERVER["QUERY_STRING"]."&"
        ),1)."page=".($page-1);
        print "\" title=\"Go to previous page\">";
      };
      print "&lt; Prev";
      if ($page > 1)
        print "</a>";
      print "\n";
      print "            </span>\n";
      print "            <span class=\"next\">\n";

      print "              ";
      if ($page < $pages) {
        print "<a href=\"?";
        print substr(str_replace(
          "&page=".$page."&",
          "&",
          "&".$_SERVER["QUERY_STRING"]."&"
        ),1)."page=".($page+1);
        print "\" title=\"Go to next page\">";
      };
      print "Next &gt;";
      if ($page < $pages)
        print "</a>";
      print "\n";
      print "            </span>\n";
      print "          </div>\n";
    };
    print "        </div>\n";

  };

  if (isset($_GET["exact"])) {
    export_as_requested(
      array(
        "All" => $exact_matches
      )
    );
    die();
  };

  if (isset($_GET["fuzzy"])) {
    export_as_requested(
      array(
        "All" => $fuzzy_matches
      )
    );
    die();
  };

  print_header("Package Search");

?>
      <div id="pkglist-search" class="box filter-criteria">
        <h2>Package Search</h2>
        <form id="pkg-search" method="get" action="/">
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
?>/>
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

if (count($exact_matches) > 0) {
?>
      <div id="exact-matches" class="box">
        <div class="pkglist-stats">
          <p><?php print count($exact_matches); ?> exact match<?php if (count($exact_matches) != 1) print "es"; ?> found.</p>
        </div>
        <table class="results">
          <thead>
            <tr>
<?php

  foreach ($sorts as $get => $sort) {
    print "              <th>\n";
    print "                ".$sort["label"]."\n";
    print "              </th>\n";
  }
?>
            </tr>
          </thead>
          <tbody>
<?php
  print_results($exact_matches);
?>
          </tbody>
        </table>
      </div>
<?php
}

?>
      <div id="pkglist-results" class="box">
<?php

  header_and_footer();

?>
        <table class="results">
          <thead>
            <tr>
<?php

  foreach ($sorts as $get => $sort) {
    print "              <th>\n";
    print "                <a href=\"/?";
    print substr(str_replace(
      "&sort=".$_GET["sort"]."&",
      "&",
      "&".$_SERVER["QUERY_STRING"]."&"
    ),1)."sort=";
    if ($_GET["sort"] == $get)
      print "-";
    print $get."\" title=\"Sort package by ".$sort["title"]."\">".$sort["label"]."</a>\n";
    print "              </th>\n";
  }
?>
            </tr>
          </thead>
          <tbody>
<?php

  print_results($fuzzy_matches);

?>
          </tbody>
        </table>
<?php

  header_and_footer();

?>
      </div>
      <div id="pkglist-about" class="box">
        <p>
          Can't find what you are looking for? Try searching again
          using different criteria, or try
          searching the <a href="https://aur.archlinux.org/">AUR</a>
          to see if the package can be found there.
        </p>
        <p>
          You are browsing the Arch Linux 32 package database. From here you can find
          detailed information about packages located in the 32 bit repositories.
        </p>
      </div>
<?php

  print_footer();
