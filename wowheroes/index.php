<?php
$zone = isset($_GET['z']) ? strtolower(trim($_GET['z'])) : null;
$realm = isset($_GET['r']) ? strtolower(trim($_GET['r'])) : null;
$guild = isset($_GET['g']) ? strtolower(trim($_GET['g'])) : null;
$callback = isset($_GET['c']) ? trim($_GET['c']) : null;

if(!in_array(strtolower($zone), array('eu', 'us')) || !$realm || !$guild || !$callback) {
	header("HTTP/1.0 404 Not Found");
	die("File not found");
}
require_once '../WowPhpTools/src/Wowheroes.php';

// check cache
$cachefile = 'cache/'.$zone.'/'.$realm.'/'.$guild;
if(!file_exists($cachefile) || filemtime($cachefile) < strtotime("-1 hour")) {
	$dir = dirname($cachefile);
	if(!is_dir($dir) && !mkdir($dir, 0777, true)) {
		header("HTTP/1.0 500 Internal Server Error");
		die("Cache couldn't be generated");
	}

	touch($cachefile); // prevents double generating cache
	$wowheroes = new WowPhpTools_Wowheroes(array('guildroster'	=>
		'http://xml.wow-heroes.com/xml-guild.php?z='.$zone.'&r=%s&g=%s'));
	try {
		$xml = $wowheroes->getGuild($realm, $guild);
	}
	catch(WowPhpTools_Exception $e) {
		header("HTTP/1.0 500 Internal Server Error");
		die("Internal Server Error");
	}

	if(!isset($xml->guild->character)) {
		header("HTTP/1.0 404 Not Found");
		die("File not found");
	}

	$characters = array();
	foreach($xml->guild->character as $character) {
		$attr = array();
		foreach($character->attributes() as $k => $v) {
			$attr[(string) $k] = (string) $v;
		}
		$characters[] = $attr;
	}
	$json = json_encode($characters);
	file_put_contents($cachefile, $json);
}
else {
	$json = file_get_contents($cachefile);
}

$expires = 3600; // 1 hour
header("Pragma: public");
header("Cache-Control: maxage=".$expires);
header('Expires: ' . gmdate('D, d M Y H:i:s', time()+$expires) . ' GMT');
echo $_GET['c']."(".$json.");";
