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
}
