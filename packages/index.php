<?php

  include "lib/mysql.php";

  foreach (array("bugs","sort") as $expected_param)
    if (! isset($_GET[$expected_param]))
      $_GET[$expected_param] = "";

  $result = mysql_run_query(
    "SELECT `name` FROM `architectures` ORDER BY `name`"
  );

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
            <div>
                <label for="id_arch" title="Limit results to a specific CPU architecture">
                    Arch</label><select multiple="multiple" id="id_arch" name="arch">
<?php

while ($row = $result -> fetch_assoc()) {
  $archs[$row["name"]] = $row["name"];
  print "<option value=\"" . $row["name"] . "\"";
  if (strpos("&".$_SERVER["QUERY_STRING"]."&", "&arch=".$row["name"]."&") !== false)
    print " selected=\"selected\"";
  print ">" . $row["name"] . "</option>\n";
}
?>
</select></div>
            <div>
                <label for="id_repo" title="Limit results to a specific respository">
                    Repository</label><select multiple="multiple" id="id_repo" name="repo">
<?php
if (! $result = $mysql -> query(
  "SELECT `name` FROM `repositories` WHERE `is_on_master_mirror` ORDER BY `stability`,`name`"
  ))
  die ($mysql -> error);

while ($row = $result -> fetch_assoc()) {
  $repos[$row["name"]] = $row["name"];
  print "<option value=\"" . $row["name"] . "\"";
  if (strpos("&".$_SERVER["QUERY_STRING"]."&", "&repo=".$row["name"]."&") !== false)
    print " selected=\"selected\"";
  print ">" . $row["name"] . "</option>\n";
}
?>
</select></div>
            <div>
                <label for="id_q" title="Enter keywords as desired">
                    Keywords</label><input id="id_q" name="q" size="30" type="text" <?php
if (isset($_GET["q"]))
  print "value=\"".$_GET["q"]."\"";
?>/></div>
            <div>
                <label for="id_bugs" title="Limit results based on bug-tracker status">
                    Bugs</label><select id="id_bugs" name="bugs">
<?php
  $bugs_drop_down = array("All", "Bugs", "No Bugs");
  foreach ($bugs_drop_down as $label) {
    print "<option value=\"";
    if ($label != "All")
      print $label;
    print "\"";
    if ($_GET["bugs"]==$label)
      print " selected=\"selected\"";
    print ">" . $label . "</option>\n";
  }
?>
</select></div>
            <div ><label>&nbsp;</label><input title="Search for packages using this criteria"
                type="submit" value="Search" /></div>
        </fieldset>
    </form>
</div>

<?php

$filter = " WHERE 1";
if (isset($_GET["arch"])) {
  $filter .= " AND `architectures`.`name` IN (";
  foreach ($archs as $arch)
    if (strpos("&".$_SERVER["QUERY_STRING"]."&", "&arch=".$arch."&") !== false)
      $filter .= "\"" . $arch . "\",";
  $filter .= "\"\")";
}
if (isset($_GET["repo"])) {
  $filter .= " AND `repositories`.`name` IN (";
  foreach ($repos as $repo)
    if (strpos("&".$_SERVER["QUERY_STRING"]."&", "&repo=".$repo."&") !== false)
      $filter .= "\"" . $repo . "\",";
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
  $filter . $exact_filter .
  " ORDER BY ";

if (isset($_GET["sort"])) {
  if (isset($sorts[$_GET["sort"]]["mysql"]))
    $query .= $sorts[$_GET["sort"]]["mysql"] . ",";
  elseif (isset($sorts[substr($_GET["sort"],1)]["mysql"]))
    $query .= $sorts[substr($_GET["sort"],1)]["mysql"] . " DESC,";
}

$query .= "`binary_packages`.`pkgname`,`repositories`.`stability`,`repositories`.`name`,`architectures`.`name`";

if (! $result = $mysql -> query(
  "SELECT " .
  "`binary_packages`.`pkgname`," .
  "`repositories`.`name` AS `repo`," .
  "`architectures`.`name` AS `arch`," .
  "CONCAT(IF(`binary_packages`.`epoch`=\"0\",\"\",CONCAT(`binary_packages`.`epoch`,\":\"))," .
  "`binary_packages`.`pkgver`,\"-\"," .
  "`binary_packages`.`pkgrel`,\".\"," .
  "`binary_packages`.`sub_pkgrel`) AS `version`," .
  "IF(`binary_packages`.`has_issues`,1,0) AS `has_issues`" .
  $query
  ))
  die ($mysql -> error);

  function print_results() {
    global $result;

    $oddity="odd";
    while ($row = $result -> fetch_assoc()) {
      print "<tr class=\"" . $oddity . "\">\n";
      print "<td>" . $row["arch"] . "</td>\n";
      print "<td>" . $row["repo"] . "</td>\n";
      print "<td><a href=\"/" . $row["repo"] . "/" . $row["arch"] . "/" . $row["pkgname"] ."/\" ";
      print "title=\"View package details for " . $row["pkgname"] . "\">" . $row["pkgname"] ."</a></td>\n";
      print "<td>" . $row["version"] . "</td>\n";
      print "<td>";
      if ($row["has_issues"])
        print "has open bug reports";
      else
        print "&nbsp;";
      print "</td>\n";
      print "</tr>\n";
      if ($oddity == "odd" )
        $oddity = "even";
      else
        $oddity = "odd";
    }
  }

if ($result -> num_rows > 0) {
?>
<div id="exact-matches" class="box">
    <div class="pkglist-stats">
        <p><?php print $result -> num_rows; ?> exact match<?php if ($result -> num_rows != 1) print "es"; ?> found.</p>
    </div>
    <table class="results">
        <thead>
            <tr>
                <th>Arch</th>
                <th>Repo</th>
                <th>Name</th>
                <th>Version</th>
                <th>Bugs</th>
            </tr>
        </thead>
        <tbody>
<?php
  print_results();
?>
        </tbody>
    </table>
</div>
<?php
}

?>

<div id="pkglist-results" class="box">
    
<?php

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
    )
  );

$query = " FROM `binary_packages`" .
  " JOIN `architectures` ON `architectures`.`id`=`binary_packages`.`architecture`" .
  " JOIN `repositories` ON `repositories`.`id`=`binary_packages`.`repository`" .
  " AND `repositories`.`is_on_master_mirror`" .
  $filter . $fuzzy_filter .
  " ORDER BY ";

if (isset($_GET["sort"])) {
  if (isset($sorts[$_GET["sort"]]["mysql"]))
    $query .= $sorts[$_GET["sort"]]["mysql"] . ",";
  elseif (isset($sorts[substr($_GET["sort"],1)]["mysql"]))
    $query .= $sorts[substr($_GET["sort"],1)]["mysql"] . " DESC,";
}

$query .= "`binary_packages`.`pkgname`,`repositories`.`stability`,`repositories`.`name`,`architectures`.`name`";

if (! $result = $mysql -> query(
  "SELECT COUNT(1)" . $query
  ))
  die ($mysql -> error);

  $num_results = implode($result -> fetch_assoc());

  $pages = max(ceil($num_results / 100), 1);
  if (isset($_GET["page"]))
    $page = max(min($_GET["page"]+0, $pages),1);
  else
    $page = 1;

if (! $result = $mysql -> query(
  "SELECT " .
  "`binary_packages`.`pkgname`," .
  "`repositories`.`name` AS `repo`," .
  "`architectures`.`name` AS `arch`," .
  "CONCAT(IF(`binary_packages`.`epoch`=\"0\",\"\",CONCAT(`binary_packages`.`epoch`,\":\"))," .
  "`binary_packages`.`pkgver`,\"-\"," .
  "`binary_packages`.`pkgrel`,\".\"," .
  "`binary_packages`.`sub_pkgrel`) AS `version`," .
  "IF(`binary_packages`.`has_issues`,1,0) AS `has_issues`" .
  $query .
  " LIMIT " . (($page-1)*100) . ", 100"
  ))
  die ($mysql -> error);


  function header_and_footer() {

    global $page, $pages, $num_results;

    print "<div class=\"pkglist-stats\">\n";

    print "<p>" . $num_results . " matching package";
    if ($num_results != 1)
      print "s";
    print " found.\n";

    if ($pages != 1) {
      print "Page " . $page . " of " . $pages . ".</p>\n";

      print "<div class=\"pkglist-nav\">\n";
      print "<span class=\"prev\">\n";

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
      print "</span>\n";
      print "<span class=\"next\">\n";

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
      print "</span>\n";
      print "</div>\n";
    };
    print "</div>\n";

  };

  header_and_footer();

?>

<table class="results">
    <thead>
        <tr>
<?php

  foreach ($sorts as $get => $sort) {
    print "<th><a href=\"/?";
    print substr(str_replace(
      "&sort=".$_GET["sort"]."&",
      "&",
      "&".$_SERVER["QUERY_STRING"]."&"
    ),1)."sort=";
    if ($_GET["sort"] == $get)
      print "-";
    print $get."\" ";
    print " title=\"Sort package by ".$sort["title"]."\">".$sort["label"]."</a></th>\n";
  }
?>
        </tr>
    </thead>
    <tbody>
<?php

  print_results();

?>                
    </tbody>
</table>

<?php

  header_and_footer();

?>

<div id="pkglist-about" class="box">
    <p>Can't find what you are looking for? Try searching again
    using different criteria, or try
    searching the <a href="https://aur.archlinux.org/">AUR</a>
    to see if the package can be found there.</p>

    <p>You are browsing the Arch Linux 32 package database. From here you can find
    detailed information about packages located in the 32 bit repositories.</p>
</div>

        <div id="footer">
            <p>Copyright © 2002-2018 <a href="mailto:jvinet@zeroflux.org"
                title="Contact Judd Vinet">Judd Vinet</a> and <a href="mailto:aaron@archlinux.org"
                title="Contact Aaron Griffin">Aaron Griffin</a>.</p>

            <p>The Arch Linux name and logo are recognized
            <a href="https://wiki.archlinux.org/index.php/DeveloperWiki:TrademarkPolicy"
                title="Arch Linux Trademark Policy">trademarks</a>. Some rights reserved.</p>

            <p>The registered trademark Linux® is used pursuant to a sublicense from LMI,
            the exclusive licensee of Linus Torvalds, owner of the mark on a world-wide basis.</p>
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
