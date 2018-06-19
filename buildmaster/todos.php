<?php
require_once "../init.php";
include BASE . "/lib/mysql.php";

$result = mysql_run_query(
  "SELECT DISTINCT " .
  "`todos`.`id`," .
  "`todos`.`file`," .
  "`todos`.`line`," .
  "`todos`.`description` " .
  "FROM `todos`;"
);

if (isset($_GET["graph"])) {

  if ($result -> num_rows > 0) {

    while ($row = $result->fetch_assoc())
      $knot_rows[$row["id"]] =
        $row["file"]. " (line ".$row["line"].") #".$row["id"].":\\n".
        str_replace("\"","\\\"",$row["description"]);

    $knots="";
    foreach ($knot_rows as $knot)
      $knots=$knots . "\"" . $knot . "\";\n";

  }

  $result = mysql_run_query(
    "SELECT DISTINCT " .
    "`todo_links`.`dependent`," .
    "`todo_links`.`depending_on` " .
    "FROM `todo_links`;"
  );

  if ($result -> num_rows > 0) {
    $count = 0;
    while ($row = $result->fetch_assoc()) {
      $link_rows[$count]["dependent"] =
        $knot_rows[$row["dependent"]];
      $link_rows[$count]["depending_on"] =
        $knot_rows[$row["depending_on"]];
      $count++;
    }

    $edges="";
    foreach ($link_rows as $link)
      $edges=$edges . "\"" . $link["depending_on"] . "\" -> \"" . $link["dependent"] . "\";\n";
  }

  header ("Content-type: image/png");
  passthru(
    "echo \"" . base64_encode(
      "digraph dependencies {\n" .
      "rankdir=LR;\n" .
      "fontname=dejavu;\n" .
      $knots .
      $edges .
      "}\n"
    ) . "\" | " .
    "base64 -d | " .
    "dot -Tpng -o/dev/stdout /dev/stdin"
  );

} else { // isset($_GET["graph"])

  if ($result -> num_rows > 0) {

    print "<html>\n";
    print "<head>\n";
    print "<title>Todos in the build scripts</title>\n";
    print "</head>\n";
    print "<body>\n";
    show_warning_on_offline_slave();

    while ($row = $result->fetch_assoc()) {
      print "<a href=\"#TODO" . $row["id"] . "\" name=\"TODO" . $row["id"] ."\">TODO #" . $row["id"] . "</a>";
      print " - ";
      print "<a href=\"https://git.archlinux32.org/archlinux32/builder/src/branch/master/" . $row["file"] . "#L" . $row["line"] . "\">" . $row["file"] . "(line " . $row["line"] . ")</a>";
      print ":<br>\n";
      print str_replace("\\n","<br>\n",$row["description"]);
      print "<br>\n";
      print "<br>\n";
    }

    print "</body>\n";
    print "</html>\n";

  }

}

?>
