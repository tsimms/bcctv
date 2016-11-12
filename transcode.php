<?php

function encode_args($array) {
  $new_array = array();
  foreach ($array as $item) {
    $keyval = explode("=", $item);
    array_push($new_array, $keyval[0] . "=" . urlencode($keyval[1]));
  }
  return $new_array;
}

$arg = "";
if (count($argv) > 1) {
  $arguments = $argv;
  array_shift($arguments);
  $arg = implode("&", encode_args($arguments));
}

$email = "cam@bridgeway.cc,bcctv-admin@bridgeway.cc,fit@bridgeway.cc";
$baseindir="/var/media";
$baseoutdir="/var/media/library/services";
$links = array();
$isProcessing = false;
if (count($argv) > 2 && ($argv[2] == "yes" || $argv[2] == "true"))
  $isProcessing = true;
$url = "http://www.bcctv.org/scripts/transcode.php?$arg";
echo "Fetching information from: $url...";
$src = file_get_contents($url);
echo "complete.\n";

$return = json_decode($src);
if (! $return->{'node'}) {
  print "No clip information found.\n";
  exit(1);
}

$node = $return->{'node'};
$arg = $return->{'arg'};
$record_date = $arg->{'date'};
$record_time = $arg->{'time'};
$record_segment = $arg->{'segment'};
$all = $arg->{'all'};

$filename = $node->field_filename[0]->{'value'};
$path = $node->field_path[0]->{'value'};
$folder = preg_replace("/_.*?$/", "", $filename);

// Set path info
$infile = $baseindir . "/" . $folder . "/" . preg_replace("/\\\\/", "/", $path) . "/" . $filename;
$path = preg_replace("/\\\\/", "/", $path);
$ministry_year = preg_replace("#^.*/#", '', $path);

// Get file duration
$file_duration = `/usr/local/bin/ffduration $infile`;
print "file duration: $file_duration\n";

$parse = array();
if (!preg_match ('#^(?<hours>[\d]{2}):(?<mins>[\d]{2}):(?<secs>[\d]{2})$#',$file_duration,$parse)) {
          // Throw error, exception, etc
          throw new RuntimeException ("Hour Format not valid");
}
$file_duration = (int) $parse['hours'] * 3600 + (int) $parse['mins'] * 60 + (int) $parse['secs'];


// Fetch and process segment time info
$found = false;
foreach ($node->field_segment_name as $index=>$array) {
  if ((strtolower($array->{'value'}) == strtolower($record_segment)) || (strtolower($all) == "true" &&
      ! in_array( strtolower($array->{'value'}), array('default', 'service in 90', 'up to welcome in 30' ))   )) {
    $start = $node->field_offset_start[$index]->{'approx_seconds'};
    $stop = $node->field_offset_end[$index]->{'approx_seconds'};
    $duration = ($stop ? $stop - $start : $file_duration);
    //$segment_name = strtolower($array->{'value'});
    $segment_name = $array->{'value'};
    foreach (array("mp4", "MP3") as $type) {
      $outfile = doTranscode($type, $baseoutdir, $path, $record_date, $segment_name, $infile, $start, $duration, $isProcessing);
      $url = "http://cdn.bcctv.org/media/library/services/$outfile";
      print "$segment_name -> $url\n";
      array_push($links, "$segment_name -> $url\n");
    }
    $found = true;
  }
}
if (! $found) {
        echo "Segment \"$record_segment\" not found.\n";
        exit(1);
} else {
  mail ($email, "[BCCTV.org] $record_date Service Elements", implode("\n", $links));
}

$fadeoutframe = intval(($duration-2)*29.97);

function doTranscode($type, $baseoutdir, $path, $record_date, $record_segment, $infile, $start, $duration, $isProcessing) {
  $stopwatch_start = time();
  $nice = ($isProcessing ? "-re" : "") ;
  switch($type) {
    case "mp4":  $options = array("process"=>$nice, "file"=>"-s 640x360 -r 29.97 -minrate:v 655360 -b:v 524288 -ab 131072 -c:v libx264 -c:a libfaac -vf \"fade=in:0:60\""); break;
    case "MP3" : $options = array("process"=>$nice, "file"=>"-acodec libmp3lame"); break;
  }
  //$outpath = $outdir . "/" . $record_date . "_" . $record_segment . "." . $type;
  $outfile = $baseoutdir . "/" . $path . "/" . $record_date . "_" . preg_replace("/[^A-Za-z0-9-]+/", "_", $record_segment) . "." . $type;
  $link = $path . "/" . $record_date . "_" . preg_replace("/[^A-Za-z0-9-]+/", "_", $record_segment) . "." . $type;
  $cmd = "nice -n 19 /usr/local/bin/ffmpeg -v 0 -y " . $options['process'] . " -i $infile -ss $start -t $duration " . $options['file'] . " $outfile 2>&1 ";
  echo "executing:\n$cmd\n";
  $output = shell_exec($cmd);
  echo $output;
  $stopwatch_end = time();
  echo "Duration: " . ($stopwatch_end - $stopwatch_start) . " seconds\n";
  return $link;
}

?>
