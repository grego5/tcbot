<?php
date_default_timezone_set('Europe/London');
set_time_limit(0);
ini_set('display_errors', 'on');
ini_set('pcre.backtrack_limit', '200000');
//$agent = "Mozilla/5.0 (Windows NT 6.1; WOW64; rv:21.0) Gecko/20100101 Firefox/21.0";
$agent = "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/27.0.1453.93 Safari/537.36";

//include functions
function exe_resource($file) {
    return defined('EMBEDED') ? 'res:///PHP/'.strtoupper(md5($file)):(getcwd() . "/$file");
}
include exe_resource('./functions.php');

//IE
//$base = rtrim(shell_exec("echo %USERPROFILE%"))."/AppData/Roaming/Microsoft/Windows/Cookies/Low";
//$files = scandir($base);
//foreach ($files as $filename) {
//    if (strstr($filename, '.txt')) {
//        $read = file_get_contents("$base/$filename");
//        if (strstr($read, 'PHPSESSID')) {
//            $ex = explode("\n", $read);
//            echo $ex[1];
//            break;
//        }
//    }
//}

//Firefox
//$base = rtrim(shell_exec("echo %USERPROFILE%"))."\AppData\Roaming\Mozilla\Firefox\Profiles";
//$profiles = scandir($base);
//$cookies = "$base\\$profiles[2]\\cookies.sqlite";

//Chrome
$user = rtrim(shell_exec("echo %USERPROFILE%"));
$cookies = $user."\AppData\Local\Google\Chrome\User Data\Default\Cookies";


$cj = new PDO("sqlite:$cookies");
$cj->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
unset($user, $cookies);

$db = new PDO('sqlite:db.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec("CREATE TABLE IF NOT EXISTS shortURL (url TEXT PRIMARY KEY, hash TEXT)");
$db->exec("CREATE TABLE IF NOT EXISTS links (id INTEGER PRIMARY KEY, nick TEXT UNIQUE)");

$mem = new PDO('sqlite::memory:');
$mem->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->sqliteCreateFunction('lev', 'levenshtein', 2);
$mem->exec("CREATE TABLE hitlist (id INTEGER PRIMARY KEY NOT NULL, name TEXT,
    fac_id INTEGER, fac_name TEXT, tag TEXT, track INTEGER DEFAULT 0,
    online INTEGER DEFAULT -1, status INTEGER DEFAULT -1, travel INTEGER DEFAULT 0, retal INTEGER DEFAULT 0)");
$mem->exec("CREATE TABLE members (id INTEGER PRIMARY KEY NOT NULL, name TEXT)");

$stmts = array(
//    'getSessid' => $cj->prepare("SELECT value FROM moz_cookies WHERE baseDomain='torn.com' AND name='PHPSESSID' LIMIT 1"),
    'getSessid' => $cj->prepare("SELECT value FROM cookies WHERE (host_key='.torn.com' OR host_key='www.torn.com') AND name='PHPSESSID' LIMIT 1"),
    'getSecret' => $cj->prepare("SELECT value FROM cookies WHERE (host_key='.torn.com' OR host_key='www.torn.com') AND name='secret' LIMIT 1"),

    'getURL' => $db->prepare("SELECT hash FROM shortURL WHERE url=? LIMIT 1"),
    'newURL' => $db->prepare("INSERT OR IGNORE INTO shortURL (url, hash) VALUES (?, ?)"),
    'newLink'=> $db->prepare("INSERT OR REPLACE INTO links (id,nick) VALUES (?, ?)"),

    'getTargetByFac' => $mem->prepare("SELECT name,fac_name,tag,track FROM hitlist WHERE fac_id=? LIMIT 1"),
    'getTargetById' => $mem->prepare("SELECT name,tag,fac_name,fac_id FROM hitlist WHERE id=? LIMIT 1"),
    'getMembers'=> $mem->prepare("SELECT * FROM members"),
    'getMemberById' => $mem->prepare("SELECT name FROM members WHERE id=?"),
    'getPlayerByName' => $mem->prepare("SELECT id,name FROM members WHERE name LIKE ? ESCAPE '\'
        UNION SELECT id,name FROM hitlist WHERE name LIKE ? ESCAPE '\'"),
    'getFaction' => $mem->prepare("SELECT fac_id,fac_name,tag,track FROM hitlist
        WHERE fac_id=:srch OR tag LIKE :srch ESCAPE '\' OR fac_name LIKE :srch ESCAPE '\' LIMIT 1"),
    'getTrack'   => $mem->prepare("SELECT fac_id,name,tag,id,online,status,retal,travel FROM hitlist WHERE track=1 OR retal!=0"),
    'getTrackById' => $mem->prepare("SELECT name,tag,online,status,retal,travel FROM hitlist WHERE id=? AND (track=1 OR retal!=0) LIMIT 1"),
    'getRetal'=> $mem->prepare("SELECT id,retal FROM hitlist WHERE retal!=0"),
    'getLinkById' => $db->prepare("SELECT nick FROM links WHERE id=?"),
    'getLinkByNick' => $db->prepare("SELECT id FROM links WHERE nick LIKE ? ESCAPE '\'"),

    'newTarget' => $mem->prepare("INSERT OR IGNORE INTO hitlist (id, name, fac_id, fac_name, tag, track)
        VALUES (:id, :name, :fac_id, :fac_name, :tag, :track)"),
    'newMember' => $mem->prepare("INSERT OR IGNORE INTO members (ID, name) VALUES (:id, :name)"),

    'setTrack' => $mem->prepare("UPDATE hitlist SET track=1,online=-1,status=-1 WHERE fac_id=?"),
    'setRetal'=> $mem->prepare("UPDATE hitlist SET status=1,online=1,retal=? WHERE id=?"),
    'udTrack' => $mem->prepare("UPDATE hitlist SET online=?,status=?,travel=? WHERE id=?"),

    'delTarget' => $mem->prepare("DELETE FROM hitlist WHERE id=?"),
    'delTrack'  => $mem->prepare("UPDATE hitlist SET track=0 WHERE track=1 AND retal=0"),
    'delTrackById'  => $mem->prepare("UPDATE hitlist SET track=0 WHERE track=1 AND retal=0 AND fac_id=?"),
    'delRetal'=> $mem->prepare("UPDATE hitlist SET retal=0 WHERE id=?"),
    'delFaction' => $mem->prepare("DELETE FROM hitlist WHERE fac_id=?"),

);

$stmts['getSessid']->execute();
$sessid = $stmts['getSessid']->fetchColumn();
$stmts['getSecret']->execute();
$secret = $stmts['getSecret']->fetchColumn();
echo "SESSID: $sessid\nSecret: $secret\n";

$cfg = array();

// allow client connection and accept if pending request
$admin_conn = admin_conn();
$cl_conn = cl_conn();
$ifcfg = new ifcfg;

init();

$attacks = array (
    "owned", "destroyed", "smashed", "killed", "obliterated", "extinguished", "spoiled",
    "eliminated", "wrecked", "raped", "sacked", "razed", "demolished", "broke", "purged",
    "wiped out", "terminated", "murdered", "slayed", "annihilated", "assassinated",
    "crucified", "eradicated", "executed", "punished", "exterminated", "finished off",
    "liquidated", "evaporated", "massacred", "slaughtered", "wasted", "kiboshed",
    "butchered", "razed", "ruined", "wracked", "squashed", "mangled", "kevorked", "offed",
    "flattened", "stomped", "squished", "crushed", "beated", "pulverized", "lamed",
    "knocked", "totaled", "crippled", "dismembered", "dismantled", "rendered", "tore apart",
    "seized", "snapped", "shattered", "mutilated", "disabled", "decimated",
    "thrashed", "threshed", "banged", "dominated", "smacked", "maimed", "castrated",
    "iced", "ganked", "hosed", "smoked", "popped", "wetted", "whacked", "whooped"
);

$null = null;
$running = 0;
$now = microtime(1);
$next = array(0, 0, 0, $now+600);
$mh = curl_multi_init();
$requests = array();
$index = array();
$day = date('j');
$retal = true;
$trtr = array();

define('REQ_EVENT', 0);         define('REQ_COUNTER', 1);    define('REQ_URL', 2);
define('REQ_CMD_XAN', 3);       define('REQ_BAZAAR', 4);     define('REQ_CMD_INFO_A', 14);
define('REQ_CMD_INFO_B', 15);   define('REQ_UDSTATS', 16);   define('REQ_CMD_TRTR', 17);
define('REQ_CMD_STATS', 18);    define('REQ_CMD_PW', 19);

define('REQ_TRACK', 20);     define('REQ_ATTACKER', 21);   define('REQ_NEWTRGT', 22);
define('REQ_CL_WARBS', 23);  define('REQ_CMD_WARBS', 24);  define('REQ_CITY', 25);

while(1) {

    //    if ($next[3] <= $now) {
//        array_push($requests, array(
//            REQ_BAZAAR, "http://www.torn.com/imarket.php?type=283&step=shop"
//        ));
//        $next[3] = $now+300;
//    }


    if ($next[3] <= $now) {
            array_push($requests, array(REQ_CITY, "http://www.torn.com/city.php"
        ));
        $next[3] = $now+rand(600, 7200);
    }


    if ($next[2] <= $now) {
        if (!empty($cfg['chain'])) {
            $chains = array_keys($cfg['chain']);
            foreach ($chains as $fac_id) {
                $request = array(REQ_COUNTER, "http://www.torn.com/includes/chainbar.php?force=1&factionID=$fac_id", $fac_id);
                array_push($requests, $request);
            }
             $next[2] = $now+2.5;
        } else $next[2] = $retal ? $now+4 : $now+10;
    }

    if ($next[1] <= $now)
    {
        $stmts['getTrack']->execute();
        $tr = array_keys($stmts['getTrack']->fetchAll(PDO::FETCH_UNIQUE|PDO::FETCH_ASSOC));

        if (!$cfg['nologin'] && !empty($tr)) {

            $r = 0;
            $stmts['getRetal']->execute();
            while ($enem = $stmts['getRetal']->fetchObject()) {
                $r++;
                if ($enem->retal <= $now) {
                    $stmts['delRetal']->execute(array($enem->id));
                    $r--;
                }
            }

//            $c = count($tr);
//            if ($c > 1)
//                array_push($requests, array(REQ_TRACK, "http://www.torn.com/factions.php?step=hitlist", $tr));
//            elseif ($c === 1)
                array_push($requests, array(REQ_TRACK, "http://www.torn.com/factions.php?step=userlist&ID=$tr[0]", (int)$tr[0]));
            $next[1] = $now + ($r === 0 ? max(2, $cfg['aggr']) : 2);
        } else $next[1] = $retal ? $now+4 : $now+10;
    }

    if (empty($requests) || $next[0] <= $now) {
        $request = array(REQ_EVENT, "http://www.torn.com/torncity/global-events?boxes={\"global\":{\"type\":\"events\"}}&_=".time());
        array_push($requests, $request);
        $next[0] = $retal ? $now+4 : $now+10;
    }

    while (!empty($requests))
    {
        foreach($requests as $i => $request)
        {
            $ch[$i] = curl_init($request[1]);
            curl_setopt_array($ch[$i], array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_BINARYTRANSFER => true,
                CURLOPT_ENCODING => "gzip",
                CURLOPT_CONNECTTIMEOUT => 14,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_DNS_CACHE_TIMEOUT => 600,
                CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            ));
            switch ($request[0])
            {
                case REQ_UDSTATS:
                    curl_setopt_array($ch[$i], array(
                        CURLOPT_POST => true,
                        CURLOPT_POSTFIELDS => array('player' => urlencode($request[2])),
                    ));
                    break;
                case REQ_CMD_STATS:
                        curl_setopt_array($ch[$i], array(
                        CURLOPT_POST => true,
                        CURLOPT_POSTFIELDS => array(
                            'id' => $request[2]['id'],
                            'name'=> $request[2]['name']
                        ),
                    ));
                    break;
                case REQ_URL:
                    curl_setopt_array($ch[$i], array(
                        CURLOPT_CUSTOMREQUEST => "POST",
                        CURLOPT_HTTPHEADER => array("Content-type: application/json"),
                        CURLOPT_POSTFIELDS => $request[2],
                        CURLOPT_USERAGENT => "gzip",
                        CURLOPT_SSL_VERIFYPEER => false
                    ));
                    break;
                case REQ_CMD_INFO_A: case REQ_CMD_INFO_B:
                case REQ_EVENT: case REQ_CMD_TRTR:
                    break;
                case REQ_CMD_PW:
                    curl_setopt($ch[$i], CURLOPT_USERPWD, "O432VpnG89385AO:S0r5RW56oI8J74R");
                    break;
                default:
                    curl_setopt_array($ch[$i], array(
                        CURLOPT_INTERFACE => $ifcfg->ip,
                        CURLOPT_USERAGENT => $agent,
                        CURLOPT_HTTPHEADER => array("Content-Type: text/html"),
                        CURLOPT_COOKIE => "mode=mobile; jsoff=on; PHPSESSID=$sessid; secret=$secret"
                    ));
            }
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
                $request = $requests[$i];

                if ($http_code === 200 && !$curl_error)
                {
                    $result = curl_multi_getcontent($done['handle']);

                    if (($request[0] < 10 && curl_getinfo($done['handle'], CURLINFO_SIZE_DOWNLOAD) > 2500) || 1 !== preg_match('#>\s*ERROR\s*<#', $result))
                    {
                        switch ($request[0])
                        {
                            case REQ_EVENT:
                                if ($retal) {
                                    if (null !== ($reply = check_events($result))) foreach ($reply as $request) array_push($requests, $request);
                                }
                               break;
                            case REQ_TRACK:
                                if (is_array($request[2])) {
                                    $replies = array();
                                    $factions = str_match_all('#<table class="data"[^>]+>.+</table><br>#sU', $result);
                                    foreach ($factions as $faction) {
                                        $fac_id = (int)str_match('#D00000.+ID=(\d+)><#s', $faction);
                                        if (in_array($fac_id, $request[2]))
                                            $replies = array_merge($replies, check_tracking($faction, $fac_id));
                                    }
                                } else $replies = check_tracking($result, $request[2]);
                                if (!empty($replies)) $requests = array_merge($requests, $replies);
                                break;
                            case REQ_COUNTER: if (null !== $reply = chain_mode($result, $request[2])) array_push($requests, $reply); break;
                            case REQ_ATTACKER: check_attacker($result, $request[2]); break;
                            case REQ_URL:
                                $data = json_decode($result);
                                $c = $stmts['newURL']->execute(array($data->longUrl, substr($data->id, 14)));
                                if ($c === 0) {
                                    file_put_contents("url error.txt", var_export($request, true));
                                }
                                if ($request[3])$msg = preg_replace("/%URL%/i", $data->id, $request[3], 1);
                                else $msg = $data->id;

                                output($msg, $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel']);
                                break;
                            case REQ_CL_WARBS: cl_warbase($result); break;
                            case REQ_NEWTRGT: get_targets($result); break;
                            case REQ_CMD_INFO_A: case REQ_CMD_INFO_B:
                                if (null !== ($reply = cmd_info($result, $request[2], $request[0], $request[3], $request[4]))) array_push($requests, $reply);
                                break;
                            case REQ_UDSTATS: if (!empty($request)) array_push($requests, cmd_stats_updated($result)); break;
                            case REQ_CMD_STATS:
                                $data = json_decode($result);
                                if (empty($data->error)) {
                                    $stats = $data->stats;
                                    $msg = "\x0314\x02".$request[2]['name']." - ".$request[2]['id']." - $stats->diff";
                                    if ($stats->diff !== "Never updated") {
                                        $msg .= " - TOT:\x03 $stats->tot\n\x0314\x02STR:\x03 $stats->str\x0314 - SPD:\x03 $stats->spd ".
                                        "\x0314- DEF:\x03 $stats->def\x0314 - DEX:\x03 $stats->dex";
                                    }
                                } else $msg = "\x02\x0314$stats->error";
                                output($msg, $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel']);
                                break;
                            case REQ_CMD_WARBS: cmd_warbase($result, $request[2]); break;
                            case REQ_CMD_XAN:
                                    $data = json_decode($result);
                                    $c = count($data->data)-1;
                                    output("\x02\x0314".str_match('#[\w-]+#', $data->label)." took ".$data->data[$c][1]." xanax",
                                            $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel']);
                                break;
                            case REQ_CMD_TRTR:
                                array_push($trtr, array(get_profile($result), $request[3]));
                                if (count($trtr) === $request[2]) {
                                    foreach ($trtr as $player) {
                                        $data = $player[0];
                                        if (!stristr($data['status'], "Torn")) {
                                            $msg = "\x02\x0314$data[name] [\x03%URL%\x0314] -".
                                                (stristr($data['status'], "Is okay") ? "\x033 " : "\x035 ").$data['status']."\x0314";
                                            if ($player[1] !== 1 && stristr($data['status'], "Traveling")) {
                                                switch (substr($data['status'], 13)) {
                                                    case "Mexico.": $t = 1560; break;
                                                    case "Cayman Islands.": $t = 2100; break;
                                                    case "Canada.": $t = 2460; break;
                                                    case "Hawaii.": $t = 8040; break;
                                                    case "United Kingdom.": $t = 9540; break;
                                                    case "Argentina.": $t = 10020; break;
                                                    case "Switzerland.": $t = 10500; break;
                                                    case "Japan.": $t = 13500; break;
                                                    case "China.": $t = 14520; break;
                                                    case "Dubai.": $t = 16260; break;
                                                    case "South Africa.": $t = 17820; break;
                                                }
                                                if ($data['home'] === "Private Island") $t = $t*70/100;
                                                $time = $t-(time()-$player[1]);
                                                $msg .= " (in ".sec_to_time($time).")";
                                            }
                                            $msg .= " -";
                                            $active = time() - strtotime($data['action']);
                                            if ($active < 300) $msg .= "\x033 ";
                                            elseif ($active < 900) $msg .= "\x037 ";
                                            else $msg .= "\x035 ";
                                            $msg .= $active > 0 ? sec_to_time($active): "0min";
                                            $url= "http://gregos.it.cx/stats/#$data[id]";
                                            if (null !== ($reply = shortURL($url, $msg))) array_push($requests, $reply);
                                        }
                                    }
                                    $trtr = array();
                                }
                                break;
                                case REQ_CMD_PW:
                                    $exp = sec_to_time((int)substr($result, 10));
                                    $pw = substr($result, 0, 10);
                                    output("$pw \x02(expires in $exp)", $null, $cfg['ircconn'], $cfg['nick'], $request[2]);
                                    break;
                                case REQ_BAZAAR:
                                    $lists = str_match_all('#<table width=100[^>]+>(.+)</table></td>#sU', $result);
                                    foreach ($lists as $item) {
                                        $name = str_match("#F>(.+)<#U", $item);
                                        $id = str_match("#ID=(\d+)#", $item);
                                        $price = (int)str_replace(',', '', str_match("#\\$([\d,]+)#", $item));
                                        if ($price <= 2350000) {
                                            $msg = "$$price - http://www.torn.com/bazaar.php?userID=$id";
                                            cmd_shout(array (":$cfg[nick]!", null, null, null, $msg));
                                        } else {
                                            echo "$name - $price\n";
                                        }
                                    }
                                    break;
                                case REQ_CITY:
                                    echo rtrim(shell_exec("time /T"), "\r\n").": Searching the city\n";
                                    break;
                        }
                        unset($requests[$i]);
                    } else {
                        try {
                            $stmts['getSessid']->execute();
                            $sessid = $stmts['getSessid']->fetchColumn();
                        } catch (PDOException $e) {
                            echo $e->getMessage()."\n";
                        }
                        try {
                            $stmts['getSecret']->execute();
                            $secret = $stmts['getSecret']->fetchColumn();
                        } catch (PDOException $e) {
                            echo $e->getMessage()."\n";
                        }
                        echo "SESSID: $sessid\nSecret: $secret\n";
                        file_put_contents("main error.html", $result);
                        echo "download size ".curl_getinfo($done['handle'], CURLINFO_SIZE_DOWNLOAD)."\n";
                    }
                } else {
                    if ($request[0] < 5 || $request[0] === REQ_TRACK) {
                        unset($requests[$i]); $msg = '';
                        switch ($request[0]) {
                            case REQ_URL:
                                if ($request[3])
                                $msg = preg_replace("/%URL%/", "...", $request[3]);
                                else $msg .= "\x02\x0314Google URL shortener does not respond";
                                break;
                            case REQ_CMD_XAN:
                                switch ($http_code) {
                                    case 404: $msg .= "\x0314\x02ID doesnt not exist"; break;
                                    case 401:
                                        $msg .= "\x035\x02Access denied.";
                                        try {
                                            $stmts['getSecret']->execute();
                                            $secret = $stmts['getSecret']->fetchColumn();
                                            $msg .= " Try again";
                                        } catch (PDOException $e) {
                                            echo $e->getMessage()."\n";
                                            $msg .= " Infom the bot operator";
                                        }
                                        break;
                                    default: array_push($requests, $request); break;
                                }
                                break;
                        }
                        if (!empty($msg)) output($msg, $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel']);
                    }
                    if ($curl_error) {
                        echo "curl error: $curl_error $request[1]\n";
                        if (preg_match('#10049#', $curl_error)) $ifcfg->renew();
                    }
                    if ($http_code) echo "http error code: $http_code $request[1]\n";
                }

                curl_multi_remove_handle($mh, $done['handle']);
                curl_close($done['handle']);
            }

            while (1)
            {
                usleep(100000);
                if (!empty($cfg['timers'])) check_timers();
                if (!empty($cfg['w2h'])) check_w2h();

                if ($cfg['warbase'] && date('j') !== $day) {
                    array_push($requests, array(REQ_CMD_WARBS, "http://www.torn.com/factions.php?step=hitlist", -1));
                    $day = date('j');
                }


                $read = array($cfg['ircconn']);
                $status = socket_select($read, $null, $null, 0);
                if ($status > 0)
                {
                    $buff = '';
                    while ($get = socket_read($cfg['ircconn'], 1024)) $buff .= $get;
                    $lines = explode("\r\n", $buff);
                    foreach ($lines as $data)
                    {
                        $ex = explode(' ', trim(preg_replace('/\s+/', ' ', $data)));
                        if($ex[0] === "PING") {
                            socket_write($cfg['ircconn'], "PONG $ex[1]\r\n");
                        } elseif(isset($ex[1])) {
                            switch ($ex[1]) {
                                case "PRIVMSG": case "NOTICE":
                                    if ($ex[3] === ":STATUS")
                                    {
                                        if ($ex[5] === "3") {
                                            $stmts['newLink']->execute(array($link['id'], $link['nick']));
                                            $msg = "\x02\x0314\"$link[nick]\" \x033has been linked with the ID:\x0314 $link[id]";
                                        } else $msg = "\x035\x02The nick \x0314\"$link[nick]\"\x035 is not Registered with NickServ or went offline\x0314 - /msg NickServ HELP";
                                        output($msg, $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel']);
                                        break;
                                    }
                                case "NOTIFY":
                                    $msg = null; for ($i = 3; $i < count($ex); $i++) { $msg .= $ex[$i] . ' '; };
                                    $from = explode("!", $ex[0]); $from = $from[0];
                                    if ($ex[2] === $cfg['nick']) $from .= " (private)";
                                    output(trim($msg, " \r\n:"), $cfg['admin'], null, $from);
                                    break;
                                case "JOIN":
                                    if (isset($cfg['chain'][$cfg['faction']])) qnotify($cfg['faction']);
                                    else output("\x02\x033Chain\x03: Off", $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel']);
                                    $nick = str_match("/:(.+)!/", $ex[0]);
                                    if ($cfg['nick'] !== $nick)
                                        array_push($cfg['nicklist'], $nick);
                                    break;
                                case "352":
                                    if ($cfg['nick'] !== $ex[7])
                                        array_push($cfg['nicklist'], $ex[7]);
                                    break;
                                case "QUIT": case "PART": case "KICK":
                                    if ($ex[1] === "KICK") $del = $ex[3];
                                    else $del = str_match("/:(.+)!/", $ex[0]);

                                    foreach ($cfg['nicklist'] as $i => $nick) {
                                        if ($nick === $del) {
                                            array_splice($cfg['nicklist'], $i, 1);
                                            break;
                                        };
                                    };
                                    if (!empty($cfg['order'])) {
                                        foreach ($cfg['order'] as $i => $nick) {
                                            if (strcasecmp($del, $nick) === 0) {
                                                array_splice($cfg['order'], $i, 1);
                                                break;
                                            }
                                        }
                                    }
                                case "NICK":
                                    $old = str_match("/:(.+)!/", $ex[0]);
                                    $new = substr($ex[2], 1);
                                    foreach($cfg['nicklist'] as $i => $nick) {
                                        if ($nick === $old) {
                                            $cfg['nicklist'][$i] = $new;
                                            break;
                                        }
                                    }

                                    if (!empty($cfg['order'])) {
                                        foreach ($cfg['order'] as $i => $nick) {
                                            if (strcasecmp($old, $nick) === 0) {
                                               $cfg['order'][$i] = $new;
                                                break;
                                            }
                                        }
                                    }
                                    break;

                            };
                            if (isset($ex[3])) {
                                if ($ex[3] === ":") array_splice ($ex, 3, 1);
                                $cmd = ltrim(strtolower($ex[3])," :");

                                if (isset($cfg['chain'][$cfg['faction']]))
                                {
                                    switch ($cmd)
                                    {
                                        case "w2h": case "wth": if (!isset($ex[4])) cmd_wth($ex); break;
                                        case "911":
                                            $stmts['getLinkByNick']->execute(array(str_match("/:(.+)!/", $ex[0])));
                                            check_w2h((int)$stmts['getLinkByNick']->fetchColumn());
                                            break;
        //                                case "!current": case "!curr": case "!c": cmd_order_current(); break;
        //                                case "!next": case "!n": cmd_order_skip(); break;
        //                                case "!back": case "!b": cmd_order_prev(); break;
        //                                case "!order": case "!o": case "!rotation": case "!r":
        //                                    if (!isset($ex[4])) {
        //                                        cmd_order_current();
        //                                        break;
        //                                    }
        //                                    switch (strtolower($ex[4])) {
        //                                        case "add": cmd_order_add($ex); break;
        //                                        case "list": cmd_order_list(); break;
        //                                        case "clr": cmd_order_clr(); break;
        //                                        case "del": cmd_order_del($ex); break;
        //                                        case "set": cmd_order_set($ex); break;
        //                                        default: cmd_help($ex);
        //                                    };
                                        case "sub": cmd_sub($ex); break;
                                    }
                                }

                                switch ($cmd)
                                {
                                    case "!$cfg[nick]": case "!help": case "!commands": case "!cmds": cmd_help($ex); break;
                                    case "!chain": case "!on":
                                        $fac_id = null;
                                        if (isset($ex[4])) $fac_id = get_faction_id($ex);
                                            else $fac_id = $cfg['faction'];
                                        if (!empty($fac_id)) {
                                            if ($cfg['chain'][$fac_id]['next'] > 0) qnotify($fac_id);
                                            else $cfg['chain'][$fac_id] = array(0 => false, 'hits' => 0, 'time' => 0, 'next' => -1);
                                        }
                                        break;
                                    case "!chainoff": case "!off":
                                        $fac_id = null;
                                        if (!empty($cfg['chain'])) {
                                            if (isset($ex[4])) $fac_id = get_faction_id($ex);
                                                else $fac_id = $cfg['faction'];
                                            if (!empty($fac_id)) {
                                                unset($cfg['chain'][$fac_id]);
                                                $msg = "\x0314\x02".(key_exists($fac_id, $cfg['warbase']) ? $cfg['warbase'][$fac_id][0] : "Your");
                                                $msg .= " chain tracking is disabled";
                                            }
                                        } else $msg = "\x033\x0314Currently no active chains";
                                        output($msg, $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel']);
                                        break;
                                    case "!shout": case "!sht": case "!s": case "!!": cmd_shout($ex); break;
                                    case "!link":  case "!l": $link = cmd_link($ex); break;
                                    case "!info": case "!inf": case "!i": case "!profile": case "!prof": case "!stats": case "!st":
                                    case "!id": case "!hp": case "!hpp": case "!attack": case "!a": case "!xan":
                                        $player = array('id'=>null,'name'=>'');
                                        if (isset($ex[4]) && preg_match("#!hp#", $cmd) !== 1) {
                                            if (ctype_digit($ex[4]) && (!isset($ex[5]) || 1 !== preg_match('#^n$#', $ex[5])))
                                                $player['id'] = intval($ex[4]);
                                            else $nick = $ex[4];
                                        } else $nick = str_match("/:(.+)!/", $ex[0]);

                                        if (empty($player['id']) && !($player = get_user_id($nick))) {
                                            $req = REQ_CMD_INFO_B;
                                            $url = "http://www.torn.com/profiles.php?XID=$nick";
                                            $player['name'] = $nick;
                                        } else {
                                            switch ($cmd) {
                                                case "!id":
                                                    output ("\x0314\x02$player[name] - $player[id]",
                                                            $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel']);
                                                    break 2;
                                                case "!attack": case "!a":
                                                    output ("\x02\x033Attack\x0314 $player[name]\x03 https://www.torn.com/attack.php?PID=$player[id]",
                                                            $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel']);
                                                    break 2;
                                                case "!xan":
                                                    $to = date("Y-m-d"); $from = date("Y-m-d", strtotime("today - 10 days"));
                                                    $req = REQ_CMD_XAN;
                                                    $url = "http://www.torn.com/torncity/stats/data?userid=$player[id]&field=xantaken&from=$from&to=$to";
                                                    break;
                                                case "!stats": case "!st";
                                                    $req = REQ_CMD_STATS;
                                                    $url = "http://gregos.it.cx/stats/fetch-stats.php";
                                                    break;
                                                default:
                                                    $req = REQ_CMD_INFO_A;
                                                    $url = "http://www.torn.com/profiles.php?XID=$player[id]";
                                            }
                                        }
                                        array_push($requests, array($req, $url, $player, $cmd, $ex));
                                        break;
                                    case "!timer": case "!t": cmd_timer($ex); break;
                                    case "!track": case "!trackoff": case "!tr": case "!re"; case "!troff":
                                        if (isset($ex[4])) $replies = cmd_track_toggle($ex, $cmd);
                                        else $replies = cmd_track($cmd);
                                        if (null !== $replies) $requests = array_merge($requests, $replies);
                                        break;
                                    case "!trtr"; case "!trall";
                                        if (null !== ($replies = cmd_track($cmd))) $requests = array_merge($requests, $replies);
                                        break;
                                    case "Battle": if ($ex[4] !== "Stats") { break; }
                                    case "name:": case "strength:": case "speed:": case "dexterity:": case "defense:": case "defence:":
                                        sleep(2);
                                        $buff = $data;
                                        while ($data = socket_read($cfg['ircconn'], 1024)) $buff .= $data;
                                        if (null !== ($reply = cmd_update_stats($ex, $buff))) array_push($requests, $reply);
                                        $buff = $data = '';
                                        break;
                                    case "!warbase": case "!wb":
                                             $fac_id = isset($ex[4]) ? get_faction_id($ex) : 0;
                                       array_push($requests, array(REQ_CMD_WARBS,
                                           "http://www.torn.com/factions.php?step=hitlist&ID=$cfg[faction]", $fac_id));
                                        break;
                                    case "!pw":
                                        $nick = str_match("/:(.+)!/", $ex[0]);
                                        $stmts['getLinkByNick']->execute(array($nick));
                                        if (in_array($nick, $cfg['nicklist']) && ($id = $stmts['getLinkByNick']->fetchColumn())) {
//                                            $stmts['getMemberById']->execute(array($id));
//                                            if ($stmts['getMemberById']->fetchColumn()) {
                                                array_push($requests, array(REQ_CMD_PW, "http://gregos.it.cx/bot/pw.php", $nick));
                                                break;
//                                            }
                                        }
                                        output("\x035\x02$nick. you're not recognized infidel!\x0314 Requires linked id (!help link) and faction membership",
                                            $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $nick);
                                        break;
                                    case "You": if (isset($ex[4]) && isset($ex[5])) {
                                        if ($ex[4].$ex[5] === "lostto") {
                                            $nick = str_match("/:(.+)!/", $ex[0]);
                                            check_nrg($nick);
                                        }
                                    }
                                    case "!e":
                                        $msg = "";
                                        $nick = isset($ex[4]) ? $ex[4] : str_match("/:(.+)!/", $ex[0]);
                                        $stmts['getLinkByNick']->execute(array($nick));
                                        if (($id = $stmts['getLinkByNick']->fetchColumn())) {
                                            if (isset($cfg['clients'][$id])) {
                                                $send = "get-inf ".str_match("/:(.+)!/", $ex[0])."\r\n";
                                                socket_write($cfg['clients'][$id]['conn'], $send, strlen($send));
                                            } else $msg = "\x0314\x02$nick\x035 is not connected";
                                         } else $msg = "\x035\x02The IRC nick\x0314 $nick\x035 is not linked to a TC ID -\x0314 !help link";
                                        if (!empty($msg)) output($msg, $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel']);
                                        break;
                                    case "!eall":
                                        if (!empty($cfg['clients'])) {
                                            $send = "get-inf ".str_match("/:(.+)!/", $ex[0])."\r\n";
                                            foreach ($cfg['clients'] as $cl) {
                                                socket_write($cl['conn'], $send, strlen($send));
                                            }
                                        } else output("\x035\x02Nonody is connected",
                                                $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel']);
                                        break;
                                    case "!retal":
                                        if (!isset($ex[4])) {
                                            $retal = $retal ? false : true;
                                            $msg = $retal ? "\x0314\x02Global events on" : "\x0314\x02Global events off";
                                             output($msg, $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel']);
                                        }
                                        break;
                                    case "!calc": case "!c": cmd_calc($ex); break;
                                    case "!print": file_put_contents("print.txt", var_export($cfg, true)); break;
                                    case "!dumphl":
                                        $dump = $mem->query("SELECT * FROM hitlist")->fetchAll(PDO::FETCH_ASSOC);
                                        file_put_contents("hitlist.txt", var_export($dump, true));
                                        break;
                                    case "!dumpm":
                                        $dump = $mem->query("SELECT * FROM members")->fetchAll(PDO::FETCH_ASSOC);
                                        file_put_contents("members.txt", var_export($dump, true));
                                        break;
                                }
                            }
                        }
                    }
                }

                $read = array($cl_conn);
                if (!empty($cfg['clients'])) {
                    foreach($cfg['clients'] as $i => $client) {
                        array_push($read, $client['conn']);
                    }
                }

                if (0 < socket_select($read, $null, $null, 0))
                {
                    if (in_array($cl_conn, $read)) {
                        $new =  socket_accept($cl_conn);
                        $buff = '';
                        while ($get = socket_read($new, 1024)) $buff .= $get;
                        $lines = explode("\r\n", $buff);
                        foreach ($lines as $data)
                        {
                            $ex = explode(" ", trim($data));
                            if ($ex[0] === "chk-inf") {
                                $pl = json_decode($ex[1], true);
                                echo "$pl[name] has connected\n";
                                $cfg['clients'][$pl['id']] = array(
                                    'conn' => $new,
                                    'name' => $pl['name'],
                                    'ed' => $pl['timer']['ed'] === 0 ? 0 : 1,
                                    'drug' => $pl['timer']['drug'] === 0 ? 0 : 1,
                                    'boost' => $pl['timer']['boost'] === 0 ? 0 : 1,
                                );
                            }
                        }
                        unset($new);
                    }

                    foreach($cfg['clients'] as $i =>& $cl) {
                        if (in_array($cl['conn'], $read))
                        {
                            $buff = '';
                            while ($get = socket_read($cl['conn'], 1024)) $buff .= $get;

                            if (!$buff) {
                                echo "$cl[name] has disconnected\n";
                                socket_close($cl['conn']);
                                unset($cfg['clients'][$i]);
                            } else {
                                $ex = explode(" ", trim($buff), 2);
                                if ($ex[0] === "chk-inf") {
                                    $pl = json_decode($ex[1], true);
                                    $stmts['getLinkById']->execute(array($pl['id']));
                                    $nick = $stmts['getLinkById']->fetchColumn();
                                    $notify = '';
                                    $msg = "\x0314\x02".($nick ? $nick : $pl['name']);

                                    if ($pl['timer']['ed'] <= 0 && $cl['ed'] === 1)
                                        $notify = ",\x033 your education cause has finished";
                                    elseif ($pl['timer']['ed'] > 0 && $cl['ed'] === 0)
                                         $cl['ed'] = 1;

                                    if ($pl['timer']['drug'] <= 0 && $cl['drug'] === 1)
                                        $notify = ",\x033 your drug effect has ended";
                                    elseif ($pl['timer']['drug'] > 0 && $cl['drug'] === 0)
                                         $cl['drug'] = 1;

                                    if ($pl['timer']['boost'] <= 0 && $cl['boost'] === 1)
                                        $notify = ",\x033 your booster cooldown is over";
                                    elseif ($pl['timer']['boost'] > 0 && $cl['boost'] === 0)
                                         $cl['boost'] = 1;

                                    if ($pl['timer']['nrg'] <= 0)
                                        $notify = ",\x033 your energy is full";

                                    if ($pl['timer']['nrv'] <= 0)
                                        $notify =",\x033 your nerve is full";

                                    if (!empty($notify)) {
                                        output ($msg.$notify, $null, $cfg['ircconn'], $cfg['nick'], ($nick ? $nick : $cfg['channel']));
                                    }
                                } elseif ($ex[0] === "get-inf") {
                                    $pl = json_decode($ex[1], true);
                                    $stmts['getLinkById']->execute(array($pl['id']));
                                    $nick = $stmts['getLinkById']->fetchColumn();
                                    $msg = "\x0314\x02".($nick ? $nick : $pl['name'])." - \x033E:$pl[nrg] \x0314- \x035N:$pl[nrv]".
                                            " \x0314- \x037D:".($pl['timer']['drug'] > 0 ? sec_to_time($pl['timer']['drug']) : "No").
                                            " \x0314- \x0310B:".($pl['timer']['boost'] > 0 ? sec_to_time($pl['timer']['boost']) : "No");
                                    output ($msg, $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel']);
                                }
                            }
                        }
                        unset($cl);
                    }
                }

                $read = array($admin_conn);
                if (isset($cfg['admin'])) array_push($read, $cfg['admin']);
                if (0 < socket_select($read, $null, $null, 0))
                {
                    if (in_array($cfg['admin'], $read))
                    {
                        if (!($input = socket_read($cfg['admin'], 10240)))
                        {
                            echo "Client disconnected\n";
                            socket_close($cfg['admin']);
                            unset($cfg['admin']);
                        }
                        else
                        {
                            $ex = explode(" ", rtrim($input, "\r\n  "));
                            switch (intval($ex[0]))
                            {
                                case 1:
                                    $msg = null;
                                    for ($i=2;$i<count($ex);$i++) $msg .= "$ex[$i] ";
                                    output(rtrim($msg), $cfg['admin'], $cfg['ircconn'], $cfg['nick'], $cfg['channel'], $ex[1]);
                                    break;
                                case 2:
                                    $cfg['nologin'] = $cfg['nologin'] ? false : true;
                                    $msg = $cfg['nologin'] ? "Bot login disabled" : "Bot login enabled";
                                    output($msg, $cfg['admin']);
                                    break;
                                case 4: array_push($requests, array(REQ_CL_WARBS, "http://www.torn.com/factions.php?step=hitlist"));
                                    break;
                                case 6:
                                    if (isset($cfg['chain'][$cfg['faction']])) qnotify($cfg['faction']);
                                    else $cfg['chain'][$cfg['faction']] = array(0 => false, 'hits' => 0, 'time' => 0, 'next' => -1);
                                    break;
                                case 0: die();
                                default: output("Unknown command", $cfg['admin']);
                            }
                        }
                    }

                    if (in_array($admin_conn, $read)){
                        if (!isset($cfg['admin'])) {
                            $cfg['admin'] = socket_accept($admin_conn);
                            socket_read($cfg['admin'], 1024);
                            echo "Client connected\n";
                            output("Connection established", $cfg['admin']);
                        } else {
                           $refused = socket_accept($admin_conn);
                           echo "Client connecttion refused\n";
                           output("Another client already connected. Only one connection allowed", $refused);
                           socket_close($refused);
                           unset($refused);
                        }
                    }
                }
                $now = microtime(1);
                if ($running > 0 || min($next) <= $now || !empty($requests)) break;
            }
        } while ($running > 0);
    }
}