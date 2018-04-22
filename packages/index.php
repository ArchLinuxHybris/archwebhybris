<?php

  include "lib/mysql.php";

  foreach (array("bugs","sort") as $expected_param)
    if (! isset($_GET[$expected_param]))
      $_GET[$expected_param] = "";

  $search_criteria = array(
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

  foreach ( $search_criteria as $criterium => $content ) {
    $result = mysql_run_query(
      "SELECT `name` FROM `" . $content["table"] . "`" . $content["extra_condition"] . " ORDER BY `name`"
    );
    while ($row = $result -> fetch_assoc())
      $search_criteria[$criterium]["values"][] = $row["name"];
  }

  $filter = " WHERE 1";
  foreach ($search_criteria as $criterium)
    if (isset($_GET[$criterium["name"]])) {
      $filter .= " AND `" . $criterium["table"] . "`.`name` IN (";
      foreach ($criterium["values"] as $value)
        if (strpos("&" . $_SERVER["QUERY_STRING"] . "&", "&" . $criterium["name"] . "=" . $value . "&") !== false)
          $filter .= "\"" . $value . "\",";
      $filter .= "\"\")";
    }

  if ($_GET["bugs"] == "Bugs")
    $filter .= " AND `binary_packages`.`has_issues`";
  if ($_GET["bugs"] == "No Bugs")
    $filter .= " AND NOT `binary_packages`.`has_issues`";

  if (isset($_GET["q"])) {
    $exact_filter = " AND `binary_packages`.`pkgname` = from_base64(\"".base64_encode($_GET["q"])."\")";
    $fuzzy_filter = " AND `binary_packages`.`pkgname` LIKE from_base64(\"".base64_encode("%".$_GET["q"]."%")."\")";
  } else {
    $exact_filter = " AND 0";
    $fuzzy_filter = "";
  }

  $query = " FROM `binary_packages`" .
    " JOIN `architectures` ON `architectures`.`id`=`binary_packages`.`architecture`" .
    " JOIN `repositories` ON `repositories`.`id`=`binary_packages`.`repository`" .
    " AND `repositories`.`is_on_master_mirror`" .
    " JOIN `build_assignments` ON `build_assignments`.`id`=`binary_packages`.`build_assignment`" .
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
    "`binary_packages`.`last_moved` AS `move_date`" .
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
      "label" => "Last Update",
      "mysql" => "IFNULL(`binary_packages`.`last_moved`,\"00-00-0000 00:00:00\")"
    )
  );

  $query = " FROM `binary_packages`" .
    " JOIN `architectures` ON `architectures`.`id`=`binary_packages`.`architecture`" .
    " JOIN `repositories` ON `repositories`.`id`=`binary_packages`.`repository`" .
    " AND `repositories`.`is_on_master_mirror`" .
    " JOIN `build_assignments` ON `build_assignments`.`id`=`binary_packages`.`build_assignment`" .
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
    "`binary_packages`.`last_moved` AS `move_date`" .
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

?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <title>Arch Linux 32 - Package Search</title>
    <link rel="stylesheet" type="text/css" href="/static/archweb.css" media="screen, projection" />
    <link rel="icon" type="image/x-icon" href="/static/favicon.ico" />
    <link rel="shortcut icon" type="image/x-icon" href="/static/favicon.ico" />
  </head>
  <body class="">
<?php show_warning_on_offline_slave(); ?>
    <div id="archnavbar" class="anb-packages">
      <div id="archnavbarlogo"><h1><a href="/" title="Return to the main page">Arch Linux</a></h1></div>
      <div id="archnavbarmenu">
        <ul id="archnavbarlist">
          <li id="anb-home"><a href="https://www.archlinux32.org/">Home</a></li>
          <li id="anb-news"><a href="https://news.archlinux32.org/">News</a></li>
          <li id="anb-packages"><a href="https://packages.archlinux32.org/">Packages</a></li>
          <li id="anb-forums"><a href="https://bbs.archlinux32.org/">Forums</a></li>
          <li id="anb-bugs"><a href="https://bugs.archlinux32.org/" title="Report and track bugs">Bugs</a></li>
          <li id="anb-mailing-list"><a href="https://lists.archlinux.org/listinfo/arch-ports">Mailing List</a></li>
          <li id="anb-download"><a href="https://www.archlinux32.org/download/" title="Get Arch Linux">Download</a></li>
          <li id="anb-arch-linux-official"><a href="https://www.archlinux.org/">Arch Linux Official</a></li>
        </ul>
      </div>
    </div>
    <div id="content">
      <div id="pkglist-search" class="box filter-criteria">
        <h2>Package Search</h2>
        <form id="pkg-search" method="get" action="/">
          <p><input id="id_sort" name="sort" type="hidden" /></p>
          <fieldset>
            <legend>Enter search criteria</legend>
<?php
  foreach ($search_criteria as $criterium) {
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
            <div>
              <label for="id_bugs" title="Limit results based on bug-tracker status">Bugs</label><select id="id_bugs" name="bugs">
<?php
  $bugs_drop_down = array("All", "Bugs", "No Bugs");
  foreach ($bugs_drop_down as $label) {
    print "                <option value=\"";
    if ($label != "All")
      print $label;
    print "\"";
    if ($_GET["bugs"]==$label)
      print " selected=\"selected\"";
    print ">" . $label . "</option>\n";
  }
?>
              </select>
            </div>
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
      <div id="footer">
        <p>
          Copyright © 2002-2018 <a href="mailto:jvinet@zeroflux.org" title="Contact Judd Vinet">Judd Vinet</a> and <a href="mailto:aaron@archlinux.org" title="Contact Aaron Griffin">Aaron Griffin</a>.
        </p>
        <p>
          The Arch Linux name and logo are recognized <a href="https://wiki.archlinux.org/index.php/DeveloperWiki:TrademarkPolicy" title="Arch Linux Trademark Policy">trademarks</a>. Some rights reserved.
        </p>
        <p>
          The registered trademark Linux® is used pursuant to a sublicense from LMI, the exclusive licensee of Linus Torvalds, owner of the mark on a world-wide basis.
        </p>
      </div>
    </div>
    <script type="application/ld+json">
    {
      "@context": "http://schema.org",
      "@type": "WebSite",
      "url": "/",
      "potentialAction": {
        "@type": "SearchAction",
        "target": "/?q={search_term}",
        "query-input": "required name=search_term"
      }
    }
    </script>
  </body>
</html>
