<?php

  include "lib/mysql.php";

  $result = mysql_run_query(
    "SELECT " .
    "`repositories`.`name` AS `repo`," .
    "`binary_packages`.`pkgname`," .
    "`binary_packages`.`epoch`," .
    "`binary_packages`.`pkgver`," .
    "`binary_packages`.`pkgrel`," .
    "`binary_packages`.`sub_pkgrel`," .
    "`architectures`.`name` AS `arch` " .
    "FROM `binary_packages` " .
    "JOIN `architectures` ON `binary_packages`.`architecture`=`architectures`.`id` " .
    " JOIN `binary_packages_in_repositories` ON `binary_packages`.`id`=`binary_packages_in_repositories`.`package`" .
    " JOIN `repositories` ON `binary_packages_in_repositories`.`repository`=`repositories`.`id`" .
    "WHERE `binary_packages_in_repositories`.`is_to_be_deleted` " .
    "AND `repositories`.`is_on_master_mirror`"
  );

  $available = explode(
    "\n",
    shell_exec("find /var/lib/pacman/ -name '*.db' -exec tar -tzf {} \; | sed -n 's,-[^-]\+-[^-]\+/$,,;T;p'")
  );
  $available = array_combine( $available, $available);
?>
<html>
<head>
<title>List of packages to be deleted</title>
<link rel="stylesheet" type="text/css" href="/static/style.css">
</head>
<body>
<?php

show_warning_on_offline_slave();

if ($result -> num_rows > 0) {

  $count = 0;

  while ($row = $result->fetch_assoc()) {

    if (isset($available[$row["pkgname"]]))
      $color = "#FF0000";
    else
      $color = "#00FF00";

    $rows[$count] =
      "<font color=\"" . $color . "\">" .
      $row["repo"] . "/" .
      $row["pkgname"] . "-";
    if ($row["epoch"] != "0")
      $rows[$count] =
        $rows[$count] .
        $row["epoch"] . ":";
    $rows[$count] =
      $rows[$count] .
      $row["pkgver"] . "-" .
      $row["pkgrel"] . "." .
      $row["sub_pkgrel"] . "-" .
      $row["arch"] . ".pkg.tar.xz</font>";
    $count++;
  }

  sort($rows);

  foreach ($rows as $row) {
    print $row."<br>\n";
  }
} else {
  print "No packages are to be deleted.\n";
}

?>
</body>
</html>
