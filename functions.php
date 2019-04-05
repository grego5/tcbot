<?php
//Getting info
function sec_to_time($t){
    $d = (int)($t / 86400);
    $hd = $t % (86400);
    $h = (int)($hd / (3600));
    $md = $t % (3600);
    $m = (int)($md / 60);
    $sd = $md % 60;
    $s = (int)($sd);
    $time = rtrim(($d !== 0 ? $d."days " : "").($h !== 0 ? $h."hrs " : "").
            ($m !== 0 ? $m."min " : "").($s !== 0 ? $s."sec " : ""));
    return $time;
}
function name_to_tag($string) {
    $string = trim(preg_replace('#[-.,_=+]+#', " ", $string));
    $string = str_replace('&', " n ", $string);
    $ex = explode(" ", $string);
    $tag = '';
    $words = count($ex);

    if ($words === 1 && strlen($string) > 4) {
        $ex = preg_split('/(?=[A-Z])/', $string);
        $words = count($ex);
    }

    if ($words !== 1 && strlen($ex[0]) <= 3) {
        $first = array_shift($ex);
        $words--;
    }

    if ($words !== 1) {
        foreach ($ex as $ini)  $tag .= strlen($ini) > 2 &&
                1 !== preg_match('#the#i', $ini) ? ucfirst($ini[0]) : strtolower($ini[0]);
    } else {
        if (strlen($ex[0]) > 4 && strcasecmp($first, "the") !== 0)
            $tag = str_match ('#([^\s]{2,4}).*#', $string);
        else $tag = substr($ex[0], 0, 4);
    }
    return $tag;
}
function curl_get($requests) {
    global $stmts, $ifcfg, $agent;
    $mh = curl_multi_init();
    $results = array();
    $running = 0;
    $index = array();

    while (!empty($requests))
    {
        $stmts['getSessid']->execute();
        $sessid = $stmts['getSessid']->fetchColumn();
        foreach($requests as $i => $request)
        {
            $ch[$i] = curl_init($request);
            curl_setopt_array($ch[$i], array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_BINARYTRANSFER => true,
                CURLOPT_COOKIE => "mode=mobile; jsoff=on; PHPSESSID=$sessid",
                CURLOPT_ENCODING => "gzip",
                CURLOPT_USERAGENT => $agent,
                CURLOPT_HTTPHEADER => array("Content-Type: text/html"),
                CURLOPT_INTERFACE => $ifcfg->ip,
                CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            ));
            curl_multi_add_handle($mh, $ch[$i]);
            $index[(int)$ch[$i]] = $i;
        }

        do
        {
            while (($execrun = curl_multi_exec($mh, $running)) === CURLM_CALL_MULTI_PERFORM);
            if ($execrun !== CURLM_OK){
                echo "curl_multi_exec error: $execrun\n";
                break;
            }

            while ($done = curl_multi_info_read($mh))
            {
                $http_code = curl_getinfo($done['handle'], CURLINFO_HTTP_CODE);
                $curl_error = curl_error($done['handle']);
                $i = $index[(int)$done['handle']];

                if ($http_code === 200 && !$curl_error)
                {
                    $result = curl_multi_getcontent($done['handle']);
                    if (curl_getinfo($done['handle'], CURLINFO_SIZE_DOWNLOAD) < 3000) {
                        curl_multi_remove_handle($mh, $done['handle']);
                        curl_close($done['handle']);
                        file_put_contents("ini error.html", $result);
                        die();
                        unset($requests[$i]);
                        $results[$i] = false;
                        continue;
                    }

                    $results[$i] = $result;
                    unset($requests[$i]);
                } else {
                    if ($curl_error)  echo "curl error: $curl_error $request[1]\n";
                    if ($http_code) echo "http error code: $http_code $request[1]\n";
                }
                curl_multi_remove_handle($mh, $done['handle']);
                curl_close($done['handle']);
            }
            usleep(100000);
        } while ($running > 0);
    }
    curl_multi_close($mh);
    return $results;
}
function str_match($pattern, $string, $x = null) {
    $matches = array();
    if (preg_match($pattern, $string, $matches)) {
        if (!isset($x)) {
            return end($matches);
        } else  return $matches[$x];
    }
    else return null;
}
function str_match_all($pattern, $string, $x = null, $y = null) {
    $matches = array();
    if (preg_match_all($pattern, $string, $matches)) {
        if (!isset($x)) {
            if (!isset($y)) return end($matches);
            else return $matches[count($matches)-1][$y];
        } else {
            if (!isset($y)) return $matches[$x];
            else return $matches[$x][$y];
        }
    }
    else return null;
}
function get_profile($result) {
    if (preg_match("#User could not be found#", $result))
            return array('error' => "\x0314\x02ID does not exist");

    $info = explode("<br>", str_match('%(<font color=#505050>.+)<br>%', $result));
    $statBox = str_match_all('#<font class="level">.+#', $result);
    $rank = str_match_all('#">([\w\s]+)\s*<#U', $statBox[1]);
    $hp = str_match_all("#\s(\d+)#", $info[5]);
    $home = trim(str_match('#>([\s\w]+)[(<]#', $info[6]));
    $name = str_match('#>([\w-]+) #', $info[0]);
    $status = str_match('#statusText">\n.+?\d>(.+)</f#', $result);
    $filter = function($status, $name) {
        $status = str_replace(array("<br></font><font color=#006633>", " <br> ", "<br>"), ". ", $status);
        $status = str_replace("$name is currently", "Is", $status);
        $status = str_replace("Currently in", "In", $status);
        $status = preg_replace("#.^#", "In", $status);
        return $status = rtrim(preg_replace('#(<[^>]+>|\.$)#', "", $status));
    };

    return array (
        'id'        => intval(str_match('#\[(\d+)\]#', $info[0])),
        'id2'       => str_match('#XID=(\d+)#', $status),
        'name'      => $name,
        'faction'   => html_entity_decode(str_match('#Faction:\s*(.+)\s*#',preg_replace('#<[^>]+>#', '', $info[3]))),
        'level'     => str_match('#>(\d+)<#', $statBox[0]),
        'hp'        => array((float)$hp[0],(float)$hp[1]),
        'home'      => $home,
        'rank'      => array($rank[1], $rank[2]),
        'age'       => str_match('#>([\d,]+)\t#', $statBox[2]),
        'status'    => $filter($status, $name),
        'action'    => str_match('# (\d+.+)#', $info[11])
    );
}
function get_targets($result) {
    global $cfg, $stmts;

    $stmts['newTarget']->bindParam(':id', $id, PDO::PARAM_INT);
    $stmts['newTarget']->bindParam(':name', $name, PDO::PARAM_STR);
    $stmts['newTarget']->bindParam(':fac_id', $fac_id, PDO::PARAM_INT);
    $stmts['newTarget']->bindParam(':fac_name', $fac_name, PDO::PARAM_STR);
    $stmts['newTarget']->bindParam(':tag', $tag, PDO::PARAM_STR);
    $stmts['newTarget']->bindValue(':track', -1, PDO::PARAM_INT);

    $rows = str_match_all('#(<tr class="bgAlt1">|<tr class="bgAlt2">)(.+)</tr>#sU', $result);
    foreach ($rows as $row) {
        $fac_id = intval(str_match('/&ID=(\d+)/', $row));
        $fac_name = html_entity_decode(preg_replace('/&#\d+;/', "", str_match('#er of (.+)"#U', $row)));
        $tag = name_to_tag($fac_name);
        $id = (int)str_match('#XID=([0-9]+)#', $row);
        $name = str_match('#XID.+b>(.+)</b.+</d#', $row);
//        $name = str_match('#XID.+alt="(.+) \[#U', $row);
        echo $stmts['newTarget']->execute();
    };
    $dir = "warbase_cache";
    if (!is_dir($dir)) mkdir($dir);
    $cache = scandir($dir);
    if (!in_array($fac_id, $cache)) {
        file_put_contents("$dir/$fac_id", $result);
    }

    $cfg['warbase'][$fac_id] = array($fac_name,$tag);
//    $cfg['warbase'] = $warbase;
}
function get_factions() {
    global $cfg, $stmts;
    output("Fetching data about factions...", $cfg['admin']);
    $result = curl_get(array(
        "http://www.torn.com/factions.php?step=userlist&ID=$cfg[faction]",
        "http://www.torn.com/factions.php?step=hitlist&ID=$cfg[faction]"
        ));

//    if (!($fac_id = str_match("/factionID=([0-9]+)/", $result[0]))) {
//        output("Torn city is not available", $cfg['admin']);
//        die();
//    }

//    $cfg['faction'] = intval($fac_id);
    $cfg['id'] = intval(str_match("/profiles.php\?XID=([0-9]+)/", $result[0]));
    $rows = str_match_all('#(<tr class="bgAlt1">|<tr class="bgAlt2">)(.+)</tr>#sU', $result[0]);
    foreach($rows as $row) {
        $id = (int)str_match('#XID=([0-9]+)#', $row);
        $name = str_match('#XID.+b>(.+)</b.+</d#', $row);
        $stmts['newMember']->execute(array(
           ':id'       => $id,
           ':name'     => $name,
       ));
    }

    $warbase = $result[1];

//    get_targets($result[1]);

    $wars = str_match_all('#<table class="data"[^>]+>.+</table><br>#sU', $result[1]);
    $requests = array();
    $result = array();
    $dir = "warbase_cache";
    if (!is_dir($dir)) mkdir($dir);
    $cache = scandir($dir);
    $now = time();

    foreach ($wars as $war) {
        $id = (int)str_match('#D00000.+ID=(\d+)><#s', $war);
        if (in_array($id, $cache)) {
            if (($now - filemtime("$dir/$id")) < 600) {
                array_push($result, file_get_contents("$dir/$id"));
            } else {
                unlink("$dir/$id");
                array_push($requests, "http://www.torn.com/factions.php?step=userlist&ID=$id");
            }
        } else {
            array_push($requests, "http://www.torn.com/factions.php?step=userlist&ID=$id");
        }
    }

    if (!empty($requests)) $result = array_merge($result, curl_get($requests));

    foreach($result as $data) {
        get_targets($data);
    }
    $d = count($requests);
    $c = count($cfg['warbase']);
    output("Factions data loaded. (Downloaded:$d, Cache:$c)", $cfg['admin']);
    cmd_warbase($warbase, -1);
}
function irc_conn() {
    global $cfg;
    output("Connecting to ".$cfg['server']."...", $cfg['admin']);

    if (($socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false) {
        die ("socket_create() failed: reason: " . socket_strerror(socket_last_error()) . "\n");
    }

    if (socket_connect($socket, $cfg['server'], $cfg['port']) === false) {
        die ("Connection to $cfg[server] failed: reason: " . socket_strerror(socket_last_error($socket)) . "\n");
    } else  output("Connected to $cfg[server]", $cfg['admin']);


    $data = socket_read($socket, 1024);
    echo $data;

    // Send auth info
    if (!empty($cfg['nick'])) {
        socket_write($socket, "USER $cfg[nick] $cfg[nick].com $cfg[nick] :$cfg[nick]\r\n");
        socket_write($socket, "NICK $cfg[nick]\r\n");
    } else {
        output("Nick not specified", $cfg['admin']); die();
    }

    while (1)
    {

        $data = socket_read($socket, 1024);
        echo $data;
        $ex = explode(' ', trim($data, " \r\n"));

        if($ex[0] === "PING") {
            socket_write($socket, "PONG ".$ex[1]."\r\n");
            continue;
        }

        if ($ex[1] === "001" && $cfg['npass']) {
            socket_write($socket, "IDENTIFY $cfg[npass]\r\n");
            continue;
        }

        $ex = explode(':', $data, 3);
        if (isset($ex[2])) {
            $result = trim($ex[2]," \r\n.");

            switch ($result)
            {
                case "Password accepted - you are now recognized":
                case "from your autojoin list";
                case "+ix";
                    socket_write($socket, "JOIN $cfg[channel] $cfg[cpass]\r\n");
                    break 2;
                case "Nickname is already in use":
                    if ($cfg['npass'] !== '') {
                        socket_write($socket, "NICK $cfg[nick]1\r\n");
                        usleep(200000);
                        socket_write($socket, "PART #lobby\r\n");
                        socket_write($socket, "PRIVMSG nickserv :ghost $cfg[nick] $cfg[npass]\r\n");
                        usleep(200000);
                    } else {
                        output("The nick $cfg[nick] is in use. Choose a different nick.", $cfg['admin']); die();
                    }
                    break;
                case "Ghost with your nick has been killed":
                    socket_write($socket, "NICK $cfg[nick]\r\n");
                    usleep(200000);
                    socket_write($socket, "IDENTIFY $cfg[npass]\r\n");
                    usleep(200000);
                    break;
            }
        }
    }
    socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, array("sec"=>0, "usec"=>30000));
    socket_set_nonblock($socket);

    output("Connection successful", $cfg['admin']);
    $cfg['ircconn'] = $socket;
    socket_write($cfg['ircconn'], "WHO $cfg[channel]\r\n");
}
function admin_conn() {
    global $cfg;
    $conn = socket_create(AF_INET, SOCK_STREAM, 0) or die("Could not create socket");
    socket_bind($conn, '127.0.0.1', 1307) or die('Could not bind to address');
    socket_set_nonblock($conn);
    socket_listen($conn) or die('Couldnt not listen to the socket');
    echo "Checking for admin connection...\r\n";
    $read = array($conn); $n = null;
    if (0 < socket_select($read, $n, $n, 3)) $cfg['admin'] = socket_accept($conn);
    else {
        echo "No pending connection detected\r\n";
        $cfg['admin'] = null;
    }
    return $conn;
}
function cl_conn() {
    $conn = socket_create(AF_INET, SOCK_STREAM, 0) or die("Could not create socket");
    socket_bind($conn, '10.0.0.97', 6655) or die('Could not bind to address');
    socket_set_nonblock($conn);
    socket_listen($conn) or die('Couldnt not listen to the socket');
    return $conn;
}
function init() {
    global $cfg;
    output("[initializing process started]", $cfg['admin']);
    output("Reading configuration...", $cfg['admin']);
    //get config
    if(!file_exists("bot.ini")) {
        output("No configuration found in bot.ini. Open the client and fill out the options", $cfg['admin']); die();
    };
    if (!($settings = parse_ini_file("bot.ini"))) {
        output("Invalid bot.ini format. Open the client and fill out the options", $cfg['admin']); die();
    };

    $cfg = array_merge($cfg, $settings);
    irc_conn();

    //build memory and configuration
    $cfg = array_merge($cfg, array (
        'nologin'   => false,
        'aggr'      => 5,
        'id'        => 0,
        'faction'   => 8520,
        'warbase'   => array(),
        'chain'     => array(),
        'w2h'       => array(),
        'order'     => array(),
        'timers'    => array(),
        'nicklist'  => array(),
        'cache'     => array(),
        'clients'   => array()
    ));
    get_factions();
    output("[initializing process complete]", $cfg['admin']);
}
function qnotify($fac_id) {
    global $cfg;
    $counter = $cfg['chain'][$fac_id];
    if ($fac_id === $cfg['faction']) {
        $msg = ($counter['next'] === -1 ? "\x02\x033Chain\x03: Checking - \x033Hits\x03: N/A - \x033Time left\x03: N/A" :
        "\x02\x033Chain:\x03 in progress - \x033Hits:\x03 $counter[hits] - \x033Time left:\x03 ".date("i:s",$counter['time'])).
        " - \x033Rotation:\x03 ".(empty($cfg['order']) ? "\x03 disabled" : "enabled - !help order" );

    } else $msg = "\x035\x02".$cfg['warbase'][$fac_id][1]." Hits:\x034 $counter[hits] \x035- Time:\x034 ".date("i:s",$counter['time']);

    output($msg, $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel']);
}
function get_user_id($nick) {
    global $cfg, $stmts;
    $player = array();

    $nick = preg_replace('#[^\w-]#', "", $nick);
    $str = str_replace("_", "\_", $nick);

    $stmts['getLinkByNick']->execute(array($str));
    if (($id = (int)$stmts['getLinkByNick']->fetchColumn())) {
        $player = array('id' => $id, 'name' => $nick);
    }

    if (empty($player)) {
        $stmts['getPlayerByName']->execute(array("%$str%","%$str%"));
        if (($matches = $stmts['getPlayerByName']->fetchAll(PDO::FETCH_ASSOC))) {
            foreach ($matches as $i => $match) {
                 $l = levenshtein($nick, $match['name']);
                 $diff = isset($diff) ? min($diff, $l) : $l;
                 if ($l === $diff) $k = $i;
            }
            $player = $matches[$k];
        }
    }

    if (empty($player)) {
        if (key_exists(strtolower($nick), $cfg['cache'])) {
            $player = array(
                'id' => $cfg['cache'][$nick],
                'name' => $nick
            );
        }
    }
    return $player;
}
function get_faction_id($ex) {
    global $cfg, $stmts;
    if (!ctype_digit($ex[4]))
    {
        $srch = '';
        for($i=4; $i<count($ex); $i++) $srch .= $ex[$i]." ";
        $srch = rtrim($srch);
        $msg = "\x02\x035Faction\x0314 $srch \x035is not found in our warbase. Try by ID";
    } else {
        $srch = $ex[4];
        $msg = "\x02\x035Faction with ID\x0314 $ex[4] \x035not found in our warbase";
    }
    $stmts['getFaction']->bindParam(':srch', $srch);
    $stmts['getFaction']->execute();
    if (($fac = $stmts['getFaction']->fetch(PDO::FETCH_OBJ)))
            return $fac->fac_id;
    else {
        output($msg, $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel']);
        return null;
    }
}
//Bot processsing
function output($msg = '', $client = null, $irc_conn = null, $from = '', $to = null, $type = 0) {
    switch ($type) {
        case 0: $cmd = "PRIVMSG"; break;
        case 1: $cmd = "NOTICE"; break;
    }
    if (!$msg) echo "empty message\n";

    $ex = explode("\n", $msg);
    if (!empty($from)) $from .= ": ";
    foreach ($ex as $line) {
        if (!empty($irc_conn) && !empty($to)) socket_write($irc_conn, "$cmd $to :$line\r\n");

        $line = preg_replace('/[0-9]+|/', '', $line);
        $line = "[".rtrim(shell_exec("time /T")," \r\n")."] "."$from$line\r\n";

        if (!empty($client)) socket_write($client, $line, strlen($line));
        else echo "$line";
    }
}

function chain_mode($result, $fac_id) {
    global $cfg;
    static $shout = true;
    if (!isset($cfg['chain'][$fac_id])) return null;

    $digits = str_match_all("#[0-9]+#", $result);
    $chain =& $cfg['chain'][$fac_id];

    if (!$digits) {
        if ($chain['next'] > -2) {
            $chain['next'] = $chain['next'] > -1 ? -3 : -2; // if chain was running, mark it as borken, else mark as not started
        } else {
            if ($chain['next'] === -3) {
                if ($fac_id === $cfg['faction']) {
                    $cfg['order'] = array();
                    $msg = ($chain['hits'] < 100 ? "\x034" : "\x033")."\x02Chain ended at $chain[hits] hits";
//                    cmd_track_toggle(array(), "!troff");
                } else {
                    $msg = "\x02\x0314".$cfg['warbase'][$fac_id][1]."\x033 chain broken at\x0314 $chain[hits]\x033 hits";
                }
                output($msg, $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel']);
            } else {
                $msg = "\x02\x0314No chain detected";
                if ($fac_id !== $cfg['faction']) $msg .= " for ".$cfg['warbase'][$fac_id][1];
                output($msg, $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel']);
            }
            unset($cfg['chain'][$fac_id]);
        }
    } else {
        $msg = '';
        $chain['time'] = intval($digits[3])*60 + intval($digits[4]);
        $hits = intval($digits[0]);
//        $n = $chain['hits'] > 0 ? $hits - $chain['hits'] : 1;

        if ($hits > $chain['hits']) {
            $chain['hits'] = $hits;


            if ($fac_id === $cfg['faction']) {
                $shout = true;
                $msg = "\x02\x0314Hits:\x037 $chain[hits]";
//                if ($cfg['nologin'] === false) {
//                    $chain['next'] = 240;
//                    return array(REQ_ATTACKER, "http://www.torn.com/factions.php?step=your&news=2", $n);
//              } else $msg .= " \x0314\x02- attack logs disabled";
            } elseif ($chain['next'] === -1) {
                    qnotify($fac_id);
            } else {
                if ($chain['time'] < 270 || in_array($hits, array(8,9,10,23,24,25,48,49,50,73,74,75,98,99,100)))
                    $msg = "\x035\x02".$cfg['warbase'][$fac_id][1]." Hits:\x034 $chain[hits]";
            }
            $chain['next'] = $fac_id === $cfg['faction'] ? 240 : 120;
            if (!empty($msg)) output($msg, $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel']);
            return;
        }


        if ($chain['hits'] < 100 && $chain['next'] >= $chain['time'])
        {
            $type = 0;
            if ($chain['time'] <= 60)
            {
                if ($fac_id === $cfg['faction'])
                {
                    $msg = "\x037Left:\x034 ".date("i:s", $chain['time']);
                    if ($shout && $chain['hits'] < 100) {
                        cmd_shout(array (":".$cfg['nick']."!", null, null, null, "$msg\x037 - Time is low. Someone hit quick!"));
                        $msg = '';  $shout = false;
                    }
                    if (($delay = $chain['time'] % 5) < 3) $delay += 5;
                } else {
                    $msg = "\x0314".$cfg['warbase'][$fac_id][1]." Left: ".date("i:s", $chain['time']);
                    if (($delay = $chain['time'] % 10) < 3) $delay += 10;
                }

            }
            else
            {
                if ($fac_id === $cfg['faction'])
                {
                    $msg = "\x0314Left:\x037 ".date("i:s", $chain['time']);
                    if ($chain['time'] <= 85)
                    {
                        if (!empty($cfg['w2h'])) {
                            $msg .= " - ";
                            foreach($cfg['w2h'] as $holder) $msg .= "$holder[nick] ";
                            $msg .= " \x034hospitalize now!";
                        }
                        elseif (!empty($cfg['order'])) {
                            $msg .= " \x0314-\x037 ".$cfg['order'][0]." \x034hospitalize now!";
                        } else {
                            $msg .= " \x0314- \x034Someone attack quickly!";
                            $type = 1;
                        }
                    }
                    elseif ($chain['time'] <= 120)
                    {
                        if (!empty($cfg['w2h'])) {
                            $msg .= " \x0314- ";
                            foreach($cfg['w2h'] as $holder) $msg .= "$holder[nick] ";
                            $msg .= "\x02".(count($cfg['w2h']) === 1 ? "is" : "are")." holding";
                        }
                        elseif (!empty($cfg['order'])) {
                            $msg .= " \x0314-\x037 ".$cfg['order'][0]." \x0314attack";
                        } else {
                            $msg .= " \x0314-\x033 Decide who is attacking!";
                            $type = 1;
                        }
                    }
                } else {
                    $msg = "\x0314".$cfg['warbase'][$fac_id][1]." Left: ".date("i:s", $chain['time']);
                }

                if ($chain['time'] <= 120 && $chain['time'] > 85) $delay = $chain['time'] % 85; // 1:25 min attack notify delay
                elseif (($delay = $chain['time'] % 60) < 20 && $chain['time'] > 80) $delay += 60;
            }

            if (!empty($msg)) output("\x02".$msg, $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel'], $type);
            $chain['next'] = max($chain['time'] - $delay, 0);
        }
    }
}
function check_attacker($result, $n) {
    global $cfg, $attacks, $stmts;
    static $history = array();
    $events = array();
    $rows = str_match_all('#(bgAlt1">|bgAlt2">)\n(.+)</tr>#sU', $result);
    $hits = $cfg['chain'][$cfg['faction']]['hits'];

    foreach ($rows as $row)
    {
        $e = str_match("/<font color=#006633>(.+)<\/font>/", $row);
        $time = str_match("#:(\d\d) #", $row);
        if (1 === preg_match("/hospitalized/", $e) && !in_array("$time $e", $history))
        {
            $n--;
            array_push($history, "$time $e");
            array_unshift($events, $e);
            if (count($history) > 5) array_shift($history);
            if ($n === 0) break;
            else $hits--;
        }
    }

    foreach($events as $i => $e) {
        $msg = "\x02\x0314Hits:\x037 ".($hits+$i);
        $player = str_match_all('/XID=([0-9]+)/', $e);
        $ex = explode(" ", preg_replace("#<[^>]+>#", '', $e), 4);
        $msg .= " \x0314- ".$ex[0]." \x02".(isset($ex[3]) ? "\x037retaliatiated\x0314" : $attacks[array_rand($attacks)])." \x02";
        switch ($cfg['chain'][$cfg['faction']]['hits']) {
            case 10: case 25: case 50: case 75: case 100:
                $msg .= "\x037"; break;
            default: $msg .= "\x0314";
        }
        $stmts['getTargetById']->execute(array(intval($player[1])));
        $tag = $stmts['getTargetById']->fetchObject()->tag;
        $msg .= "$tag $ex[2]";

        if (!empty($cfg['w2h'])) check_w2h((int)$player[0]);

        if (!empty($cfg['order']) && count($cfg['order'] > 1)) {
            $stmts['getLinkById']->execute(array((int)$player[0]));
            $nick = $stmts['getLinkById']->fetchColumn();

            if (strcasecmp($cfg['order'][0], $nick) === 0) {
                $prev = array_shift($cfg['order']);
                array_push($cfg['order'], $prev);
                $msg .= " \x0314- \x037Now: ".$cfg['order'][0]." \x0314| Next: ".$cfg['order'][1];
                break;
            }
        }
        output($msg, $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel']);
    }
}
function check_w2h($clear = null) {
    global $cfg;

    if (isset($clear)) {
        foreach ($cfg['w2h'] as $i => $holder) {
            if ($holder['id'] === $clear) {
                array_splice ($cfg['w2h'], $i, 1);
                break;
            }
        }
    }

    if (!empty($cfg['w2h']))
    {
        $msg = '';
        $now = time();
        foreach($cfg['w2h'] as $i => $holder)
        {
            if ((($now - $holder['time']) > 255) && $holder['warn']) {
                $msg = "\x02$holder[nick]\x0314 attack will timeout in less than\x03 ".sec_to_time(300 - ($now - $holder['time']));
                $cfg['w2h'][$i]['warn'] = false;
            } elseif (($now - $holder['time']) >= 300) {
                if($cfg['w2h']['id'] === $holder['id']) $cfg['w2h'] = array();
                array_splice($cfg['w2h'], $i, 1);
            }
        }
        if ($msg) output($msg, $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel']);
    }
}
function check_events($response) {
    global $cfg, $stmts;
    static $history = array();
    $replies = array();
    $stmts['getMembers']->execute();
    $members = $stmts['getMembers']->fetchAll(PDO::FETCH_OBJ);

    $events = json_decode($response)->global;
    $events = array_reverse($events);

    foreach ($events as $event)
    {
        if (!in_array($event->id, $history))
        {
            $msg = '';
            array_push($history, $event->id);
            while (count($history) > 15) array_shift($history);

            $type = trim(preg_replace('#<a[^>]+>(.*)</a>|Someone#U', '', $event->text));
            $id = str_match_all('#ID=([0-9]+)#', $event->text);
            $id_1 = intval($id[0]);
            $id_2 = isset($id[1]) ? intval($id[1]) : null;
            $text = preg_replace('#<[^>]+>#', '', $event->text);

            switch ($type)
            {
                case "mugged":
                case "attacked":
                case "hospitalized":

                    foreach ($members as $m)
                    {
                        if ($id_2 === (int)$m->id || (empty($id_2) && $id_1 === (int)$m->id))
                        {
                            if (!empty($cfg['w2h'])) check_w2h((int)$m->id);
                            $ex = explode(" ", $text, 3);

                            $msg = $type !==  "hospitalized" ? "\x0314\x02$ex[0] ".($id_2 ? "$id_1 " : "")."\x02$ex[1] \x02$ex[2]" :
                            ($ex[0] === "Someone" ? "\x035\x02" : "\x034\x02")."$ex[0]\x035 ".(empty($id_2) ? "" : "$id_1 ")."\x02$ex[1] \x02$ex[2]";

                            if ($type === "hospitalized" && !empty($id_2) && $cfg['warbase']) {
                                $stmts['getTargetById']->execute(array($id_1));
                                if (($enem = $stmts['getTargetById']->fetchObject())) {
                                    $msg = "\x034\x02$enem->tag $ex[0] \x035\x02$ex[1] \x02$ex[2] [\x034Retal: \x03%URL%\x035]";
                                    if (null !== ($reply = shortURL("http://gregos.it.cx/stats/#$id_1", $msg)))
                                        array_push($replies, $reply);
                                    cmd_track_toggle(array(null,null,null,"!tr",$enem->fac_id,2,$id_1,600-ceil($event->secondssince)));
                                    continue 3;
                                }
                            }
                        }
                    }
                    break;
                case "has joined the faction":
                    if ($id_2 === $cfg['faction'])
                    {
                        $ex = explode(" ", $text);
                        $stmts['newMember']->execute(array(
                            ':id'       => $id_1,
                            ':name'     => $ex[0],
                        ));
                        $msg = "\x02\x0314$ex[0]\x03 [%URL%]\x0314 has joined our faction";
                        if (null !== ($reply = shortURL("http://gregos.it.cx/stats/#$id_1", $msg))) {
                            array_push($replies, $reply);
                        }
                    }
                    else
                    {
                        $stmts['getTargetByFac']->execute(array($id_2));
                        $enem = $stmts['getTargetByFac']->fetchObject();
                        if (!empty($enem))
                        {
                            $ex = explode(" ", $text);
                            $msg = "\x02\x0314$ex[0] [\x03%URL%\x0314] has joined $enem->fac_name";
                            $stmts['newTarget']->execute(array(
                                ':id'       => $id_1,
                                ':name'     => $ex[0],
                                ':fac_id'   => $id_2,
                                ':fac_name' => $enem->fac_name,
                                ':tag'      => $enem->tag,
                                ':track'    => (int)$enem->track === 1 ? 1 : -1
                            ));

                            if (null !== ($reply = shortURL("http://gregos.it.cx/stats/#$id_1", $msg))) {
                                array_push($replies, $reply);
                            }
                        }
                    }
                    continue 2;
                case "has overdosed on Vicodin":
                case "has overdosed on Xanax":
                    foreach ($members as $m) {
                        if ($id_1 === (int)$m->id) {
                            output("\x0314\x02$m->name\x02 $type", $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel']);
                            break;
                        }
                    }
                    break;
                case "has joined the company":
                    foreach ($members as $m) {
                        if ($id_1 === (int)$m->id) {
                            output("\x0314\x02$m->name\x02 $type".$text, $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel']);
                            break;
                        }
                    }
                    break;
                case "declared war against":
                    switch ($cfg['faction'])
                    {
                        case $id_1:
                            $f = $id_2;
                            $faction = str_match("#declared war against (.+)#", $text);
                            $msg = "\x0314\x02You declared war on\x037 $faction \x0314[\x03%URL%\x0314]";
                            break;
                        case $id_2:
                            $f = $id_1;
                            $faction = str_match("#(.+) declared war against#", $text);
                            $msg = "\x034\x02$faction [\x03%URL%\x034] \x037declared war on you";
                            break;
                        default: break 2;
                    };
                    $cfg['warbase'][$f][0] = $faction;
                    $cfg['warbase'][$f][1] = name_to_tag($faction);
                    array_push($replies, array(REQ_NEWTRGT, "http://www.torn.com/factions.php?step=userlist&ID=$f"));
                    if (null !== (shortURL("https://www.torn.com/factions.php?step=profile&ID=$f", $msg))) {
                        array_push($replies,$reply);
                    }
                    continue 2;
                case "won the war against":
                    switch ($cfg['faction'])
                    {
                        case $id_1:
                            $f = $id_2;
                            $msg = "\x02\x0314You have defeated\x033 ".$cfg['warbase'][$f][0];
                            break;
                        case $id_2:
                            $f = $id_1;
                            $msg = "\x02\x0314You have been defeated by\x034 ".$cfg['warbase'][$f][0];
                            break;
                        default: break 2;
                    }
                    $stmts['delFaction']->execute(array($f));
                    unset($cfg['warbase'][$f]);
                    break;
            }
            if (!empty($msg)) output($msg, $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel']);
        }
    }
    return $replies;
}
function check_timers() {
    global $cfg;
    $n = null;
    foreach ($cfg['timers'] as $nick => $timers) {
        foreach($timers as $i => $timer) {
            if (time() >= $timer[0]) {
                output("$nick, time is up for $timer[1]", $n, $cfg['ircconn'], $n, $nick);
                array_splice($cfg['timers'][$nick], $i, 1);
            }
        }
        if (empty($cfg['timers'][$nick])) unset($cfg['timers'][$nick]);
    }
}
function check_tracking($result, $fac_id) {
    global $cfg, $attacks, $stmts;
    static $prev = array();
    $replies = array();
    $current = array();

    $rows = str_match_all('#(<tr class="bgAlt1">|<tr class="bgAlt2">)(.+)</tr>#sU', $result); // get idividual players
    if(!$rows) {
        echo "error check tracking\n";
        file_put_contents("result.html", $result);
        return;
    }

    foreach ($rows as $row) // for each player
    {
        $id = intval(str_match('#XID=([0-9]+)#', $row)); // get his id
        array_push($current, $id);
        $stmts['getTrackById']->execute(array($id));
        $enem = $stmts['getTrackById']->fetchObject();
        if (!empty($enem))
        {
            if (($st = preg_match('#006600>#', $row)) !== 1) {
                $cond = str_match('#FF0000>(.+)</f#', $row);
            } else $cond = '';

            if (($on = preg_match('#id="icon1"#', $row)) === 1)
            {
                $msg = ''; $url = '';

                if ((int)$enem->online === 0) {
                    $msg = "\x0314\x02$enem->tag $enem->name \x02came online";
                }

                if ($st === 1 && ((int)$enem->status === 0 || (int)$enem->online < 1))
                {
                    $msg = ($msg ? "\x033\x02$enem->tag $enem->name \x0314\x02came online & " :
                        ((int)$enem->retal !== 0 ? "\x034\x02$enem->tag $enem->name \x035" :
                        "\x033\x02$enem->tag $enem->name \x0314")."\x02").
                    "ready to get ".$attacks[array_rand($attacks)].((int)$enem->retal !== 0 ?
                            " \x02[\x034Retal: \x03%URL%\x035]\x0314 ".date("i:s", (int)$enem->retal-time())." left" : " \x02[\x03%URL%\x0314]");
                    $url= "http://gregos.it.cx/stats/#$id";
                } else {
                    if (!empty($msg)) $msg .= " ($cond)";
                }

                if (!empty($url)) {
                    if (null !== ($reply = shortURL($url, $msg))) array_push($replies, $reply);
                }
                elseif (!empty($msg)) output($msg, $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel']);
            }

            if ($cond === "Traveling" && (int)$enem->travel === 0) {
                $tr = (int)$enem->status === 1 ? time() : 1;
            } elseif ((int)$enem->travel === 1 && $st === 1) {
                $tr = 0;
            } else $tr = (int)$enem->travel;

            $stmts['udTrack']->execute(array($on, $st, $tr, $id));
        }
    }
    if (!isset($prev[$fac_id])) $prev[$fac_id] = $current;
    else {
        foreach($prev[$fac_id] as $id)
            if (!in_array($id, $current)) {
                $stmts['getTargetById']->execute(array($id));
                $enem = $stmts['getTargetById']->fetchObject();
                output("\x0314\x02$enem->name\x02 has left\x02 $enem->fac_name",
                        $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel']);
                $stmts['delTarget']->execute(array($id));
            }
        $prev[$fac_id] = $current;
    }
    return $replies;
}
function shortURL($url, $msg = '') {
    global $cfg, $stmts;
    $stmts['getURL']->execute(array($url));

    if (($hash = $stmts['getURL']->fetch(PDO::FETCH_COLUMN))) {
        if (!empty($msg)) {
            $msg = preg_replace("/%URL%/i", "http://goo.gl/$hash", $msg, 1);
        } else $msg = "http://goo.gl/$hash";
        output($msg, $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel']);
        return null;
    }
    return array(REQ_URL, "https://www.googleapis.com/urlshortener/v1/url", '{"longUrl": "'.$url.'"}', $msg);
}

//IRC commands
function cmd_sub($ex) {
    global $cfg;
    if (isset($ex[4])) return;
    if (!empty($cfg['nicklist']))
    {
        $speaker = str_match("/:(.+)!/", $ex[0]);
        $msg = "\x02\x034ATTENTION:\x037 $speaker reqested for backup \x0314\x02";
        foreach ($cfg['nicklist'] as $nick) {
            if ($nick !== $cfg['nick'] && $nick !== $speaker) $msg .= $nick.' ';
        }
        $msg = rtrim($msg);
    } else $msg .= "\x02\x035Error: \x0314 could not see any users";
    output($msg, $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel']);
}
function cmd_shout($ex = array()) {
    global $cfg;
    if (empty($ex[4])) {
        $msg = "\x02\x035Shout fail: \x0314specify a reason - !shout test";
        output($msg, $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel']);
        return;
    };
    if (!empty($cfg['nicklist'])) {
        $msg = "\x02\x034ATTENTION:\x037 ";
        for ($i = 4; $i < count($ex); $i++) $msg .= $ex[$i].' ';
        $msg .= "\x0314\x02";
        foreach ($cfg['nicklist'] as $nick) if($nick !== $cfg['nick']) $msg .= $nick." ";
        $msg = rtrim($msg);
    }
    else $msg = "\x02\x035Error: \x0314 could not see any users";
    output($msg, $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel']);
}
function cmd_timer($ex) {
    global $cfg;

    $nick = str_match("/:(.+)!/", $ex[0]);
    if (isset($ex[4]))
    {
        if (strtolower($ex[4]) === "del")
        {
            if (key_exists($nick, $cfg['timers'])) {
                $t = $cfg['timers'][$nick];
                if (isset($ex[5])) {
                    $i = (int)$ex[5];
                    if (isset($t[$i])) {
                        array_splice($cfg['timers'][$nick], $i, 1);
                        $msg = "\x02\x0314Timer\x03 ".(empty($t[$i][1]) ? $ex[5] : $t[$i][1])." \x0314has been deleted";
                    } else $msg ="\x02\x0314Timer\x03 $ex[5] \x0314does not exist";
                } else {
                   $del = array_pop($cfg['timers'][$nick]);
                   $msg = "\x02\x0314Timer\x03 ".(empty($del[1]) ? count($t)-1 : $del[1])." \x0314has been deleted";
                }
                if (empty($cfg['timers'][$nick])) unset($cfg['timers'][$nick]);
            } else $msg ="\x02\x0314Could not find any active timers for\x03 $nick";
            output ($msg , $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $ex[2]);
            return;
        }

        $digits = 0;
        for($i=4; $i<count($ex); $i++) {
            if (ctype_digit($ex[$i])) $digits++;
            if ($digits > 3 || !ctype_digit($ex[$i])) break;
        }


        if ($digits === 0) {
            output ("\x02\x035No valid number pssed.\x0314 See !help timer",
                    $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel']);
            return;
        }

        if ($digits > 3) {
            output ("\x02\x035Too many numbers passed. Max is 3: hh mm ss.\x0314 See !help timer",
                    $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel']);
            return;
        }

        $t=0;
        switch($digits) {
            case 3: $t += $ex[4]*3600+$ex[5]*60+$ex[6]; break;
            case 2: $t += $ex[4]*60+$ex[5]; break;
            case 1: $t += $ex[4]*60; break;
        }

        if (isset($ex[4+$digits])) {
            for($i=4+$digits; $i<count($ex); $i++) $note .= $ex[$i]." ";
            $note = rtrim($note);
        } else $note = '';
        if (!isset($cfg['timers'][$nick])) $cfg['timers'][$nick] = array();
        array_push($cfg['timers'][$nick], array(time()+$t, $note));

        $msg = "\x02\x0314Timer ID\x03 ".(count($cfg['timers'][$nick])-1)."\x0314 is set for $nick. Notify in\x03 ".sec_to_time($t);
        output ($msg, $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $ex[2]);
    }
    else
    {
        if (key_exists($nick, $cfg['timers'])) {
            $msg = '';
            output ("\x02\x0314Timers for $nick:", $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel']);
            foreach ($cfg['timers'][$nick] as $i => $timer) {
                $msg .= "\x0314ID: $i - ".(empty($timer[1]) ? "Untitled" : $timer[1])." - Left: ".sec_to_time($timer[0]-time())."\n";
            }
        } else $msg = "\x02\x0314No timers set for $nick. See !help timer";
            output ($msg, $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $ex[2]);
    }
}
function cmd_info($result, $player, $type, $cmd, $ex) {
    global $cfg;

    if (preg_match("#Profile Error#", $result) === 1) {
        output ("\x02\x035User\x0314 $player[name]\x035 is not found",
                $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel']);
        return;
    }
    $data = get_profile($result);
    if (!$data) file_put_contents ("error.html", $result);

    if ($type === REQ_CMD_INFO_B) {
        $cfg['cache'][strtolower($data['name'])] = $data['id'];
        if (count($cfg['cache']) > 50) array_shift($cfg['cache']);
        $data['status'] = preg_replace('#<[^>]+>#', "", $data['status']);
    }

    switch ($cmd)
    {
        case "!id":
            output ("\x0314\x02$data[name] - $data[id]",
                $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel']);
            break;
        case "!attack": case "!a":
            output ("\x02\x033Attack\x0314 $data[name]\x03 https://www.torn.com/attack.php?PID=$data[id]",
                $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel']);
            break;
        case "!hp": case "!hpp":
            $msg = '';
            if ($data['hp'][1] !== $data['hp'][0]) {
                if (isset($cfg['timers'][$player['name']])) {
                    foreach($cfg['timers'][$player['name']] as $i => $timer) {
                        if (preg_match('#(\d+)% hp#', $timer[1]) === 1) {
                            if (!isset($ex[4])) {
                            $msg = "\x02$data[name] \x0314- ";
                            $p = $data['hp'][0] !== 0  ? (float)$data[hp][0]/(float)$data[hp][1]*100 : 0;
                            if ($p > 41) $msg .= "\x033 "; elseif ($p > 21) $msg .= "\x037 "; else $msg .= "\x035 ";
                                $msg .= number_format($data['hp'][0])."\x0314 (".(int)$p."%) -\x03 ".
                                sec_to_time($timer[0]-time())."\x0314 to $timer[1]";
                            } else {
                                array_splice($cfg['timers'][$player['name']], $i, 1);
                                if (empty($cfg['timers'][$player['name']])) unset ($cfg['timers'][$player['name']]);
                            }
                            break;
                        }
                    }
                }
                if (empty($msg)) {
                    $p = (int)($data['hp'][0] !== 0  ? (float)$data[hp][0]/(float)$data[hp][1]*100 : 0);
                    $set = (float)(isset($ex[4]) && ctype_digit($ex[4]) ? $ex[4] / 100 : 1);
                    $msg = "\x02$data[name]\x0314:";
                    if ($p > 51) $msg .= "\x033 "; elseif ($p > 21) $msg .= "\x037 "; else $msg .= "\x035 ";
                    $msg .= number_format($data[hp][0])."\x0314 ($p%) - ";

                    if (!isset($ex[4]) || $set*100 > $p) {
                        $d = $cmd === "!hp" ? 5 : 3.32;
                        $t = (15 * (ceil((($data['hp'][1]*$set) - $data['hp'][0]) / ($data['hp'][1] / $d))) - ((int)date('i') % 15))*60-(int)date('s');
                        if (!isset($cfg['timers'][$player['name']])) $cfg['timers'][$player['name']] = array();
                        array_push($cfg['timers'][$player['name']], array(time()+$t, ($set*100)."% hp"));
                        $msg .= (100*$set)."% in\x03 ".sec_to_time($t);
                    } else $msg .= "set higher $";
                }
            } else $msg = "\x02$data[name]\x0314, your hp is full -\x033 ".number_format($data['hp'][0]);
            output($msg, $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel']);
            break;
        case "!xan": case "!xanax":
            $to = date("Y-m-d"); $from = date("Y-m-d", strtotime("today - 7 days"));
            return array(REQ_CMD_XAN,
                    "http://www.torn.com/torncity/stats/data?userid=$data[id]&field=xantaken&from=$from&to=$to");
        case "!stats": case "!st":
            return array(REQ_CMD_STATS, "http://gregos.it.cx/stats/fetch-stats.php", $data);
        default:
            $msg = "\x02\x0314$data[name] [\x03%URL%\x0314] the ".$data['rank'][1]." -".
            "\x033 $data[level] @ $data[age] \x0314- HP:";

            $p = (int)($data['hp'][0] !== 0  ? (float)$data[hp][0]/(float)$data[hp][1]*100 : 0);
            if ($p > 51) $msg .= "\x033 "; elseif ($p > 21) $msg .= "\x037 "; else $msg .= "\x035 ";

            $msg .= number_format($data[hp][0])."\x0314 ($p%) -".
            (stristr($data['status'], "Is okay") ? "\x033 " : "\x035 ").$data['status'];

            $url_1 = "http://gregos.it.cx/stats/#$data[id]";

            $active = time() - strtotime($data['action']);
            if ($active < 300) $msg .= " \x0314-\x033 ";
            elseif ($active < 900) $msg .= " \x0314-\x037 ";
            else $msg .= " \x0314-\x035 ";
            $msg .= $active > 0 ? sec_to_time($active): "0min";
            if (null !== ($reply = shortURL($url_1, $msg))) return $reply;
    }
}

function cmd_order_add($ex) {
    global $cfg, $stmts;
    if (!isset($ex[5])) $ex[5] = str_match("/:(.+)!/", $ex[0]);
    $added = null; $not_added = null;
    $c = count($ex);
    for($i = 5; $i < $c; $i++)
    {
        $stmts['getLinkByNick']->execute($ex[$i]);
        if ($stmts['getLinkByNick']->fetchColumn())
        {
            if (!empty($cfg['order'])) {
                foreach ($cfg['order'] as $nick) {
                    if (strcasecmp($nick, $ex[$i]) === 0) {
                        output("\x0314\x02$nick \x035already in rotation",
                                $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel']);
                        continue 2;
                    }
                }
            }
            array_push($cfg['order'], $ex[$i]);
            $added .= "$ex[$i] ";
        } else $not_added .= "$ex[$i] ";
    }

    if (!empty($added)) {
        $msg = "\x02\x033Added\x0314 $added\x033to the rotation";
        output($msg, $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel']);
    }

    if (!empty($not_added)) {
        $msg = "\x02\x035Cannot add\x0314 $not_added - \x035This IRC nick is not linked with TC ID-\x0314 !help link";
        output($msg, $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel']);
    }
}
function cmd_order_list() {
    global $cfg;
    if (empty($cfg['order'])) {
        $msg = "\x02\x035The rotation list is empty";
    } else {
        $msg = "\x0314\x02";
        foreach ($cfg['order'] as $i => $name) {
            $msg .= ($i+1).":$name ";
        }
    }
    output(rtrim($msg), $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel']);
}
function cmd_order_del($ex) {
    global $cfg;
    $dlted = array();
    $not_dlted = array();
    if (!isset($ex[5])) $ex[5] = str_match("/:(.+)!/", $ex[0]);
    $c = count($ex);

    for ($i=5; $i<$c; $i++) {
        foreach ($cfg['order'] as $k => $nick) {
            if (strcasecmp($nick, $ex[$i]) === 0) {
                array_splice($cfg['order'], $k, 1);
                array_push($dlted, $ex[$i]);
                break;
            }
        }
        if (!in_array($ex[$i], $dlted)) array_push($not_dlted, $ex[$i]);
    }

    if (!empty($not_dlted)) {
        output("\x0314\x02".implode(" ", $not_dlted)." \x035".(count($not_dlted) > 1 ? "are" : "is")." not in the rotation",
                $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel']);
    }

    if (!empty($dlted)) {
    output("\x0314\x02".implode(" ", $dlted)." \x033".(count($dlted) > 1 ? "are" : "is")." removed from the rotation",
            $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel']);
    }
}
function cmd_order_set($ex) {
    global $cfg;
    if (isset($ex[6]) && ctype_digit($ex[6])) {
        $change = $ex[5];
        $pos = (int)$ex[6];
    }
    elseif (isset($ex[5]) && ctype_digit($ex[5])) {
        $change = str_match("/:(.+)!/", $ex[0]);
        $pos = (int)$ex[5];
    } else {
        output("\x02\x035Invalid format for !set.\x0314 Use !order set NICK # / !order set #",
                $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel']);
        return;
    }

    $k1 = null;
    foreach ($cfg['order'] as $i => $nick) {
        if (strcasecmp($nick, $change) === 0) {
            $k1 = $i;
            break;
        }
    }

    if(null === $k1) {
        output("\x02\x035The nick \x0314\"$change\" \x035is not in the list",
                $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel']);
        return;
    }

    if (count($cfg['order']) <= $pos) {
        array_splice($cfg['order'], $k1, 1);
        array_push($cfg['order'], $change);
        output("\x0314\x02$change \x033is now last", $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel']);
        return;
    }

    if (1 >= $pos) {
        array_splice($cfg['order'], $k1, 1);
        array_unshift($cfg['order'], $change);
        output("\x0314\x02$change \x033is now first", $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel']);
        return;
    }

    $k2 = $pos-1;
    $temp = $cfg['order'][$k2];
    $cfg['order'][$k2] = $change;
    $cfg['order'][$k1] = $temp;
    output("\x0314\x02$change \x033is now \x0314#".$pos." \x033in the list",
            $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel']);
}
function cmd_help($ex) {
    global $cfg;
    $bot_cmds = array (
        "!chain"      => "Start tracking the hit counter. (Chain must be started). Example: !chain | !on",
        "!link"       => "Associate your IRC nick with Torn ID. (Nick must be registered and identified with nickserv. /msg NickServ HELP). Example: !link 1234 | !l $cfg[nick] 1234",
        "w2h"         => "Register you temporarily as \"ready to hit\". Notify sent at 2:00 and 1:25 or holding timeout. (Requires !link. Chain mode only. Silent). Example: w2h | wth",
        "!order"      => "Show current rotation. Valid options are: add nick, del nick, set nick #. (If nick not specified your nick will be used. Chain mode only. Requies !link) Example: !order add | !o set 4 | !r set $cfg[nick] 0",
        "!next"       => "Skips one turn in rotation. (Requires chain mode and active rotation). Example: !next | !n",
        "!back"       => "Rewind the roration (Requires chain mode and active rotation) !back | !b",
        "!shout"      => "Sends notfify to all people in channel. (Requires notify message as parameter). Example: !shout $cfg[nick] 1 2 3 | !sht help | !s chain",
        "sub"         => "Sends quick notify to people in the channel. (Do not pass message as parameter. Chain mode only). Example: sub",
        "!info"       => "Show your or other player profile info inline. (Players from your faction only). Example !inf | !info $cfg[nick] | !i",
        "!hp"         => "Set timer for full hp, or specified percent.(!hpp for 30% regen rate) Example: !hp | !hp 60",
        "!xan"      => "",
        "!timer"      => "Set private notification timer with optional title. To delete !timer del or !timer del <id>. (Title cannot start with a number) Format: min | min sec | hr min sec. Example: !t 1 30 0 land in tc | !timer 600 xanax",
        "!track"      => "Initialize tracking for one of the faction in the warbase, displaying when person comes online and can be attacked. (From warbase only) Example: !track $cfg[faction] | !tr | !troff",
        "!warbase"    => "",
        "!calc"       => ""
        );

    if (!isset($ex[4])) {
        $cmds = array_keys($bot_cmds);
        $msg = "\x0314List of commands: ";
        foreach ($cmds as $cmd) $msg .= $cmd." ";
        $msg = rtrim($msg);
        output($msg.". For more info !help cmd", $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel']);
    } else {
        switch ($ex[4]) {
            case "!chain":  case "chain":   output("\x0314".$bot_cmds['!chain'], $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel']); break;
            case "!link":   case "link":    output("\x0314".$bot_cmds['!link'], $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel']); break;
            case "w2h":     case "wth":     output("\x0314".$bot_cmds['w2h'], $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel']); break;
            case "!order":  case "order":   output("\x0314".$bot_cmds['!order'], $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel']); break;
            case "!next":   case "next":    output("\x0314".$bot_cmds['!next'], $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel']); break;
            case "!back":   case "back":    output("\x0314".$bot_cmds['!back'], $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel']); break;
            case "!shout":  case "shout":   output("\x0314".$bot_cmds['!shout'], $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel']); break;
            case "!sub":    case "sub":     output("\x0314".$bot_cmds['sub'], $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel']); break;
            case "!info":   case "info":    output("\x0314".$bot_cmds['!info'], $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel']); break;
            case "!timer":  case "timer":   output("\x0314".$bot_cmds['!timer'], $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel']); break;
            case "!hp":     case "hp":      output("\x0314".$bot_cmds['!hp'], $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel']); break;
            case "!track":  case "track":   output("\x0314".$bot_cmds['!track'], $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel']); break;
        }
    }
}
function cmd_link($ex) {
    global $cfg;
    if (isset($ex[4]) && ctype_digit($ex[4])) {
        $new = array('nick' => str_match("/:(.+)!/U", $ex[0]), 'id' => intval($ex[4]));
    }
    elseif (isset($ex[5]) && ctype_digit($ex[5])) {
        $new = array('nick' => $ex[4], 'id' => intval($ex[5]));
    } else {
        output("\x02\x035Invalid format.\x0314 !link ID or !link NICK ID",
                $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel']);
        return;
    }

    $query = "PRIVMSG NickServ STATUS $new[nick]\r\n";
    socket_write($cfg['ircconn'], $query, strlen($query));
    return $new;
}
function cmd_wth($ex) {
    global $cfg, $stmts;
    $w2h = str_match("/:(.+)!/", $ex[0]);

    $stmts['getLinkByNick']->execute(array($w2h));

    if (!($id = (int)$stmts['getLinkByNick']->fetchColumn())) {
        output("\x035\x02The IRC nick \x0314\"$w2h\" is not linked to TC ID -\x0314 !help link",
                $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel']);
        return;
    }

    $now = time();
    $new = array(
        'nick' => $w2h,
        'id' => $id,
        'time' => $now,
        'warn' => true
    );

    foreach($cfg['w2h'] as $holder){
        if ($holder['id'] === $id) {
            $msg = $msg = "\x02$holder[nick]\x0314 got\x03 ".sec_to_time(300 - ($now - $holder['time']))."\x0314 left before attack timeout";
            output($msg, $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel']);
            return;
        }
    }
    array_unshift($cfg['w2h'], $new);
}
function cmd_order_current() {
    global $cfg; $msg = null;
    if (!empty($cfg['w2h'])) $msg .= "\x037".$cfg['w2h']['nick']."\x0317 is holding - ";
    if (!empty($cfg['order'])) {
        if (count($cfg['order']) > 1) $msg .= "\x0314Now turn of\x037 ".$cfg['order'][0]." \x0314- Next is ".$cfg['order'][1];
        else $msg = "\x0314".$cfg['order'][0]." \s035is the only player in the list!";
    } elseif (!empty($msg))
        $msg = rtrim($msg, "- ");
    else
        $msg = "\x035The rotation list is empty";
    output("\x02".$msg, $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel']);
}
function cmd_order_skip() {
    global $cfg;
    if (!empty($cfg['order'])) {
    $current = array_shift($cfg['order']);
    array_push($cfg['order'], $current);
    output("\x02\x0314Now turn of\x037 ".$cfg['order'][0]." \x0314- Next is ".$cfg['order'][1],
            $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel']);
    }
}
function cmd_order_prev() {
    global $cfg;
    if (!empty($cfg['order'])) {
        $prev = end($cfg['order']);
        array_pop($cfg['order']);
        array_unshift($cfg['order'], $prev);
        output("\x02\x0314Now turn o4f\x037 ".$cfg['order'][0]." \x0314- Next is ".$cfg['order'][1],
                $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel']);
    }
}
function cmd_order_clr() {
    global $cfg;
    $cfg['order'] = array();
    output("\x02\x033Cleanred", $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel']);
}
function cmd_track_toggle($ex, $cmd = '') {
    global $cfg, $stmts;
    $fac = null;
    $msg = '';

    if (isset($ex[4])) {
        if (!ctype_digit($ex[4]))
        {
            $srch = '';
            for($i=4; $i<count($ex); $i++) $srch .= $ex[$i]." ";
            $srch = rtrim($srch);
            $msg = "\x02\x035Faction\x0314 $srch \x035is not found in our warbase. Try by ID";
        } else {
            $srch = $ex[4];
            $msg = "\x02\x035Faction with ID\x0314 $ex[4] \x035not found in our warbase";
        }
        $stmts['getFaction']->bindParam(':srch', $srch);
        $stmts['getFaction']->execute();
        $fac = $stmts['getFaction']->fetchObject();

        if (!empty($fac))
        {
            $msg = '';
            if ($cmd === "!trackoff" || $cmd === "!troff")
            {
                $stmts['delTrackById']->execute(array($fac->fac_id));
                if ($stmts['delTrackById']->rowCount() > 0) {
                    $msg = "\x02\x0314Tracking disabled for $fac->fac_name";
                } else $msg = "\x02\x0314You are not tracking the faction $fac->fac_name";
            }
            else
            {
                $id = isset($ex[6]) ? (int)$ex[6] : 0;
                $cd = isset($ex[7]) ? (int)$ex[7] : 0;

                if ($id !== 0 && $cd !== 0) {
                    $stmts['setRetal']->execute(array(time()+$cd,$id));
                    return;
                } else {
                    $fac_id = (int)$fac->fac_id;
                    $stmts['setTrack']->execute(array($fac_id));
                    $cfg['aggr'] = isset($ex[5]) ? (float)$ex[5] : 5;

                    if (isset($cfg['chain'][$fac_id]) && $cfg['chain'][$fac_id]['next'] > -1) qnotify($fac_id);
                    else {
                        $cfg['chain'][$fac_id] = array(0 => false, 'hits' => 0, 'time' => 0, 'next' => -1);
                        $msg = "\x02\x0314Tracking enabled for faction\x033 $fac->fac_name";
                        if ($cfg['nologin']) $msg .= " \x0314- \x02\suspended";
                    }
                }
            }
        }
    }
    if ($msg) output($msg, $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel']);
}
function cmd_track ($cmd) {
    global $cfg, $stmts;
    if ($cmd === "!trackoff" || $cmd === "!troff") {
        $stmts['delTrack']->execute();
        if ($stmts['delTrack']->rowCount() > 0) {
            $msg = "\x02\x0314Tracking disabled globally";
        } else $msg = "\x02\x0314You are not tracking any faction";
    } else {
        if ($cfg['nologin'])
            $msg = "\x02\x035Tracking suspended";
        else
        {
            $replies = array();
            $stmts['getTrack']->execute();
            if (($tr = $stmts['getTrack']->fetchAll(PDO::FETCH_OBJ))) {
                if ($cmd === "!trtr") {
                    $c = 0;
                    foreach($tr as $enem) {
                        if ((int)$enem->travel > 0) {
                            $c++;
                            array_push($replies, array(
                                REQ_CMD_TRTR,
                                "http://www.torn.com/profiles.php?XID=$enem->id",
                                2 =>& $c,
                                (int)$enem->travel
                            ));
                        }
                    }
                } else {
                    global $attacks;
                    foreach($tr as $enem) {
                        if ((int)$enem->status === 1 && ((int)$enem->online === 1 || $cmd === "!trall")) {

                    $msg = ((int)$enem->retal !== 0 ? "\x034\x02$enem->tag $enem->name \x035" : "\x033\x02$enem->tag $enem->name \x0314")."\x02".
                    "ready to get ".$attacks[array_rand($attacks)].((int)$enem->retal !== 0 ?
                        " \x02[\x03%URL%\x035]\x0314 ".date("i:s", (int)$enem->retal-time())." left" : " \x02[\x03%URL%\x0314]");
                            $url= "http://gregos.it.cx/stats/#$enem->id";
                            if (null !== ($reply = shortURL($url, $msg))) array_push($replies, $reply);
                        }
                    }

                    $stmts['getTrack']->execute();
                    $tr = array_keys($stmts['getTrack']->fetchAll(PDO::FETCH_UNIQUE));
                    foreach ($tr as $i) {
                        if (isset($cfg['chain'][$i]) && $cfg['chain'][$i]['next'] > -1) qnotify($i);
                        else {
                            $cfg['chain'][$i] = array(0 => false, 'hits' => 0, 'time' => 0, 'next' => -1);
                        }
                    }
                }
                return $replies;
            } else $msg = "\x02\x0314You are not tracking any faction";
        }
    }
    if ($msg) output($msg, $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel']);
}
function cmd_update_stats($ex, $buffer) {
    global $cfg, $stmts;
    $id = null; $stats = array();

    if (!($id = str_match('#name:\s*[\w-]+\W*([0-9,]+)#i', $buffer)))
    {
        $nick = str_match('#:([\w-]+)!#', $ex[0]);
        $str = str_replace("_", "\_", $nick);

        $stmts['getLinkByNick']->execute(array($str));
        if (!($id = $stmts['getLinkByNick']->fetchColumn())) {
            output("\x035\x02The IRC nick\x0314 $nick\x035 is not linked to TC ID -\x0314 !help link",
                    $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel']);
        }
    };

    if ($id)
    {
        if (!($stats[0] = str_match("/str[^:]*:\s*([0-9,]+)[\.a-z\r\n]/Ui", $buffer))) $stats[0] = 0; else $stats[0] = (float)(str_replace(",", "", $stats[0]));
        if (!($stats[1] = str_match("/sp[^:]*:\s*([0-9,]+)[\.a-z\r\n]/Ui", $buffer))) $stats[1] = 0; else $stats[1] = (float)(str_replace(",", "", $stats[1]));
        if (!($stats[2] = str_match("/def[^:]*:\s*([0-9,]+)[\.a-z\r\n]/Ui", $buffer))) $stats[2] = 0; else $stats[2] = (float)(str_replace(",", "", $stats[2]));
        if (!($stats[3] = str_match("/dex[^:]*:\s*([0-9,]+)[\.a-z\r\n]/Ui", $buffer))) $stats[3] = 0; else $stats[3] = (float)(str_replace(",", "", $stats[3]));
        if (!($stats[4] = str_match("/tot[^:]*:\s*([0-9,]+)[\.a-z\r\n]/Ui", $buffer))) $stats[4] = 0; else $stats[4] = (float)(str_replace(",", "", $stats[4]));

        $c=0; $miss = 0;
        foreach ($stats as $i => $stat) {
            if ($stat === 0) {
                $c++;
                $miss = $i;
            }
        }
        if ($c === 1) {
            $sum = $stats[0]+$stats[1]+$stats[2]+$stats[3];
            $stats[$miss] = $miss === 4 ? $sum : $stats[4] - $sum;
        } else if ($c === 5) return;

        $data = json_encode(array(array($id, $stats[0], $stats[1], $stats[2], $stats[3], $stats[4])));
        return array(REQ_UDSTATS, "http://gregos.it.cx/stats/add-stats.php", $data);
    }
}
function cmd_stats_updated($result) {
    $data = json_decode($result, true);
    $msg = "\x02\x033Stats for\x0314 ".$data[0][0][1]." [\x03%URL%\x0314] \x033updated";
    if (!empty($data[1]) && array_sum($data[1][0]) > 0)
    {
        for($i=1; $i<=5; $i++)
        {
            if ($data[1][$i] > 0)
            {
                $msg .= "\n\x02\x033+".number_format($data[1][$i])." \x0314";
                switch($i)
                {
                    case 1: $msg .= "Strength"; break;
                    case 2: $msg .= "Speed"; break;
                    case 3: $msg .= "Defence"; break;
                    case 4: $msg .= "Dexterity"; break;
                    case 5:
                        $time = '';
                        $msg .= "Total";
                        if ($data[1][0][0] !== 0) $time .= " ".$data[1][0][0]." days";
                        if ($data[1][0][1] !== 0) $time .= " ".$data[1][0][1]." hrs";
                        if ($data[1][0][2] !== 0) $time .= " ".$data[1][0][2]." min";
                        if (!empty($time)) $msg .= " over$time";
                }
            }
        }
    }
    if (null !== ($reply = shortURL("http://gregos.it.cx/stats/#".$data[0][0][0], $msg))) return $reply;
}
function cl_warbase($result) {
    global $cfg;
    $targets = array();
    $rows = str_match_all('#(<tr class="bgAlt1">|<tr class="bgAlt2">)(.+)</tr>#sU', $result);
    foreach ($rows as $row) {
        $name = str_match_all("#><b>(\w+)</b>#", $row);
        array_push($targets, array (
            intval(str_match('#XID=([0-9]+)#', $row)), // player ID
            isset($name[2]) ? $name[1] : $name[0], // player name
            utf8_encode(str_match('/er of (.+)"/U', $row)), // faction name
            preg_match('#id="icon1"#', $row), // online
            preg_match("#006600>#", $row) // condition
        ));
    }
    $data = "@5@ ".json_encode($targets);
    socket_write($cfg['admin'], $data, strlen($data));
}
function cmd_warbase($result, $fac_id = null) {
    global $cfg, $stmts;
    $day = (int)date('d');
    $filename = "wars.json";
    if (file_exists($filename)) {
        $stats = json_decode(file_get_contents($filename), true);
        $stats['factions'] = array_intersect_key($stats['factions'], $cfg['warbase']);
    } else $stats['day'] = $day;

    $wars = str_match_all('#<table class="data"[^>]+>.+</table><br>#sU', $result);
    $stmts['getFaction']->bindParam(':srch', $id);
    foreach ($wars as $war)
    {
        $id = (int)str_match('#D00000.+ID=(\d+)><#s', $war);
        $ctrl = (int)str_match("#control : (\d+)#", $war);
        $msg = "\x0314\x02$ctrl%";

        $stmts['getFaction']->execute();
        $fac = $stmts['getFaction']->fetchObject();

        if (isset($stats['factions'][$id])) {
            $diff = $ctrl - $stats['factions'][$id]['base'];
            $msg .= ($diff === 0 ? " (0)" : ($diff > 0 ? " (\x033+$diff" : " (\x034$diff")."\x0314)");

            if ($stats['day'] !== $day) { // day change
                $stats['factions'][$id] = array('base' => $ctrl, 'gain' => 0);
            } else { // update
                $stats['factions'][$id]['gain'] = $diff;
            }
        } else $stats['factions'][$id] = array ('base' => $ctrl, 'gain' => 0);

        $msg .= " - [$fac->tag]\x033 $fac->fac_name \x0314- $id";
        if ((int)$fac->track === 1) $msg .= " -\x033 Tracking";
        if ($fac_id === 0 || $fac_id === $id) output($msg, $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel']);
    }
    $stats['day'] = $day;
    file_put_contents($filename, json_encode($stats));
}
function cmd_calc($ex) {
    global $cfg;
    $str = '';
    $c = count($ex);
    for($i=4; $i<$c; $i++) $str .= $ex[$i]." ";
    eval('$x = '.preg_replace('/[^0-9\+\-\*\/\(\)\.]/', '', $str).';');
    output("\x0314$str =\x02\x033 ".number_format($x, 2), $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel']);
}
function nrg_to_sec($e, $max) {
    $r = 300 / $max * 5;
    return ((($max-$e) / 5 * $r) - (int)(date('i')) % $r) * 60 - date('s');
}

class ifcfg
{
    /**
     * @var string $dump full infromation about interface ip address
     * @var string $ip current ip address
     */

    var $dump;
    var $ip;
    private $m;

    function __construct()
    {
        $this->renew();
    }

    function renew()
    {
        $this->dump = shell_exec("netsh interface ipv4 show ipaddresses interface=22");
        preg_match('#10\.211\.\d+\.\d+#', $this->dump, $this->m);
        $this->ip = $this->m[0];
    }
}