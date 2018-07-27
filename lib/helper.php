<?php

# do not include twice
if (function_exists("format_time_duration"))
  return;

require_once "../init.php";

function format_time_duration($val) {
  $val = floor($val);
  $result = "";
  $result =
    sprintf(
      "%02d",
      $val % 60
    );
  $val = floor($val / 60);
  if ($val == 0)
    return $result;
  $result =
    sprintf(
      "%02d:%s",
      $val % 60,
      $result
    );
  $val = floor($val / 60);
  if ($val == 0)
    return $result;
  $result =
    sprintf(
      "%d:%s",
      $val % 24,
      $result
    );
  $val = floor($val / 24);
  if ($val == 0)
    return $result;
  $tmp = $val % 7;
  $printed_conjunction = true;
  if ($tmp > 1)
    $result =
      sprintf(
        "%d days and %s",
        $tmp,
        $result
      );
  elseif ($tmp == 1)
    $result =
      sprintf(
        "%d day and %s",
        $tmp,
        $result
      );
  else
    $printed_conjunction = false;
  $val = floor($val / 7);
  if ($val == 0)
    return $result;
  if ($printed_conjunction)
    $result =
      sprintf(
        ", %s",
        $result
      );
  else
    $result =
      sprintf(
        " and %s",
        $result
      );
  if ($val>1)
    $result =
      sprintf(
        "%d weeks%s",
        $val,
        $result
      );
  else
    $result =
      sprintf(
        "%d week%s",
        $val,
        $result
      );
  return $result;
};

function git_url($repository,$type,$commit,$path,$line = null,$commit_is_hash = null) {
  global $git_available;
  # TODO: we might want to cache this value (with memcached ?)
  if (!isset($git_available)) {
    $git_available =
      preg_match(
        "/ 200 OK$/",
        get_headers("https://git.archlinux32.org/archlinux32/packages")[0]
      ) == 1;
  }
  if (!isset($commit_is_hash))
    $commit_is_hash = preg_match("/^[0-9a-f]{40}$/",$commit)==1;
  if ($git_available) {
    if (isset($line))
      $line = "#L" . $line;
    else
      $line = "";
    if ($commit_is_hash)
      $commit = "commit/" . $commit;
    else
      $commit = "branch/" . $commit;
    switch ($type) {
      case "tree":
        return
          "https://git.archlinux32.org/archlinux32/" .
          $repository .
          "/src/" .
          $commit .
          "/" .
          $path .
          $line;
      case "log":
        return
          "https://git.archlinux32.org/archlinux32/" .
          $repository .
          "/commits/" .
          $commit .
          "/" .
          $path .
          $line;
    }

  } else {
    if (isset($line))
      $line = "#n" . $line;
    else
      $line = "";
    if ($commit_is_hash)
      $commit = "?id=" . $commit;
    else
      $commit = "?h=" . $commit;
    switch ($type) {
      case "tree":
        return
          "https://git2.archlinux32.org/Archlinux32/" .
          $repository .
          "/tree/" .
          $path .
          $commit .
          $line;
      case "log":
        return
          "https://git2.archlinux32.org/Archlinux32/" .
          $repository .
          "/log/" .
          $path .
          $commit .
          $line;
    }
  };
};
