<pre>

Algorithms for auto-generation and mapping of nodes:

Create mapping from event to episodes (for existing media):
  Get admin-defined list of programs (strtotime spec)
  Loop through and build timestamp list of all events
  Cross-map list with existing, active, event timestamps
  Process episode selection logic (most recent, least recent, random, least run, most requested, related)
  Map event to chosen episode


<?php

chdir($_SERVER['DOCUMENT_ROOT']);


require_once "./includes/bootstrap.inc";
drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);

function test($nid) {
        printNode(node_load($nid));
}

function printNode($node) {
        print '<pre>';
        print "\n" . $node->type . " node\n";
        print_r ($node);
        print "\n";
        print '</pre>';
}

function getMinistryYear($timestamp) {
        $date = getdate($timestamp);
        //$labor_day = strtotime("this year september first monday", $timestamp);
        // new Labor Day spec
        $labor_day = strtotime("first Monday of September", $timestamp);
        $ministry_year_start = strtotime("sunday midnight", $labor_day);
        //$ministry_year = (time < $ministry_year_start ? $date["year"] - 1 : $date["year"]);
        $ministry_year = ($timestamp < $ministry_year_start ? $date["year"] - 1 : $date["year"]);
        $ministry_year .= "-" . ($ministry_year+1);
        return $ministry_year;
}

function getUtcTime($timestamp) {
        list($time,$zone) = preg_split('/\+/', gmdate(DATE_ATOM, $timestamp));
        return $time;
}

function getTimestamp($air_time) {
        return strtotime($air_time . " this week");
}

function getRules() {
        // select all published automation nodes
        $q = "select nid from {node} where type='automation' and status=1";
        $r = db_query($q);
        $nids = array();
        while ($s = db_fetch_array($r)) {
                array_push($nids, $s['nid']);
                echo "Got Automation node: " . $s['nid'] . "\n";
        }
        $rules = array();
        // Loop through each automation node
        foreach ($nids as $nid) {
                //$nid = 140;
                $node = node_load($nid,null,true);
                $look_ahead = $node->field_event_lookahead[0]['value'];
                foreach ($node->field_program as $index=>$value) {
                        $rule = $node->field_rules[$index]['value'];
                        $key = strtotime($look_ahead . " " . $rule, strtotime("Sunday"));
                        if ($node->field_event_start_view[$index])
                                $rules[$key]["published_start"] = $node->field_event_start_view[$index];
                        $rules[$key]["live"] = $node->field_is_live[$index]['value'];
                        $rules[$key]["encoder"] = $node->field_encoder[$index]['nid'];
                        $rules[$key]["master"] = $node->field_replay_source[$index]['value'];
                        $rules[$key]["imported"] = $node->field_is_imported[$index]['value'];
                        $rules[$key]["program"] = $value['nid'];
                }
        }
        ksort ($rules);
        return $rules;
}

function newNode() {
        $node = new stdClass();
        $node->uid = 5;
        $node->name = "BCCTV Master Control";
        $node->format = 2;
        $node->status = 1;
        $node->promote = 0;
        return $node;
}

function createClip($air_timestamp, $event_start_view,  $name, $program_nid) {
        $node = newNode();
        $node->type = "clip";

        //$air_timestamp = strtotime($air_time . " next week");
        $ministry_year = getMinistryYear($air_timestamp);
        $path = "$ministry_year";
        $air_date = getdate(strtotime($event_start_view));
        $filename = sprintf ("%s_%04d-%02d-%02d_%02d%02d.mp4",
                $name,
                $air_date["year"], $air_date["mon"], $air_date["mday"],
                $air_date["hours"], $air_date["minutes"]
        );

        $node->field_source_type = Array( Array ("value"=>"file"));
        $node->field_path = Array( Array ("value"=>$path));
        $node->field_filename = Array( Array( "value"=>$filename));
        $node->field_primary_program = Array( Array( "nid"=>$program_nid));

        $program = node_load($program_nid);
        $short_code = "clip";
        if ($program) {
                $field_short_code = $program->field_short_name;
                $short_code = $field_short_code[0]["value"];
        }
        $node->title = $short_code . "_" . preg_replace('/^.*?([\d][\d-_]+[\d]).*?$/', '\1', $filename);

        return $node;
}

function createEpisode($clip, $program, $date, $stream, $encoder) {
        $node = newNode();
        $node->type = "episode";
        $node->title = "Episode for $date";
        $series = "";
        $node->body = "";
        $node->field_subtitle = Array( Array( "value"=>$series));
        $node->field_stream = Array( Array( "value"=>$stream));
        $node->field_encoder = Array( Array( "nid"=>$encoder));
        $node->field_clip = Array( Array( "nid"=>$clip));
        $node->field_program = Array( Array( "nid"=>$program));
        return $node;
}

function findEvent($air_timestamp) {
        $t = gmdate('Y-m-d\TH:i:s', $air_timestamp);
        $q = "SELECT n.nid FROM {node} n LEFT JOIN {content_type_event} e ON e.nid=n.nid WHERE LOWER(type)='event' and field_event_start_value = '$t' ORDER BY n.nid DESC LIMIT 1";
        $result = db_query($q);
        if ($node = db_fetch_array($result)) {
          return $node['nid'];
        }
        return false;
}

function createEvent($air_timestamp, $program_nid, $event_start_view) {
        $node = newNode();
        $node->type = "event";
        $node->status = 0;

        $time = getUtcTime($air_timestamp);
        $timezone = "America/New York";         // TO-DO: Get timezone from Drupal settings
        $timezone_db = "UTC";                   // TO-DO: Does this work for everything?
        $data_type = "date";
        $event_start = Array("value"=>$time,"rrule"=>null,"timezone"=>$timezone,"timezone_db"=>$timezone_db,"data_type"=>$data_type);

        $node->field_event_start = Array( $event_start );
        $node->field_program = Array( Array("nid"=>$program_nid));
        if ($event_start_view && $event_start_view["value"])
          $node->field_event_start_view = Array( $event_start_view );

        return $node;
}

function automateEvents($rules) {
        foreach ($rules as $k => $v) {
                $timestamp = $k;
                $program_nid = $v['program'];
                $event_start_view = $v['published_start'];
                if (! (findEvent($timestamp)))
                {
                        print "timestamp not found: " . getUtcTime($timestamp) . "<br/>";
                        $new_event = createEvent($timestamp, $program_nid, $event_start_view);
                        // If master bit is set, then we trigger sticky, which is used for identifying master events
                        if ($v['master'] == "on")
                                $new_event->sticky = 1;
                        node_save(node_submit($new_event));
                }
                else
                        print "timestamp skipped: " . getUtcTime($timestamp) . "<br/>";
        }
        return $rules;
}

function attachEvents($rules) {
        // Do node_load (multi record return w/ cck search parameters) or db query to find event nids where
        //       rule exists that has program = program and start time matches
        //       store event nid in rule
        $events = array();
        foreach ($rules as $k => $v) {
                $timestamp = $k;
                if ($event = findEvent($timestamp)) {
                        $events[$k] = $v;
                        $events[$k]['event'] = node_load($event);
                        $events[$k]['program'] = node_load($v['program']);
                }
        }
        return $events;
}

function connectEpisodeToEvent($event, $episode_nid) {
        $event->field_episode = Array( Array("nid"=>$episode_nid));
        $event->status = 1;
        node_save($event);
}

function automateLiveEpisode($rules) {
        // Iterate rules; if event nid exists, create clip
        $rules = attachEvents($rules);
        foreach ($rules as $k => $v) {
                if (($v['live'] == 'on' && $event = $v['event']) ||
                    ($v['imported'] == 'on' && $event = $v['event'])) {
                        $timestamp = $k;
                        $short_name = $v['program']->field_short_name[0]['value'];
                        $program_nid = $v['program']->nid;
                        include_once "time_routines.php";
                        $event_start_view = getPublishedTime($v["event"]);
                        $new_clip = createClip($timestamp, $event_start_view, $short_name, $program_nid);
                        $clip = (node_submit($new_clip));
                        if ($errors = form_get_errors()) {
                                print_r($errors);
                        }
                        else {
                                node_save($clip);
                                $clip_nid = $clip->nid;
                                node_save($clip);
                                $live = ($v['live'] == "on" ? "live" : "file");
                                $encoder = ($live == "live" ? $v['encoder'] : "");
                                $new_episode = createEpisode($clip_nid, $program_nid, date("m/d/Y G:i", $timestamp), $live, $encoder);
                                node_save(node_submit($new_episode));
                                connectEpisodeToEvent($v['event'], $new_episode->nid);
                        }
                }
        }
        return $rules;
}


echo "started... " . time() . "\n";
$rules = getRules();
automateEvents($rules);
print_r(automateLiveEpisode($rules));

echo "ended..." . time() . "\n";
exit;


?>
