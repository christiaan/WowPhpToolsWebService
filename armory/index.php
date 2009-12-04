<?php
$zone = isset($_GET['z']) ? strtolower(trim($_GET['z'])) : null;
$realm = isset($_GET['r']) ? strtolower(trim($_GET['r'])) : null;
$guild = isset($_GET['g']) ? strtolower(trim($_GET['g'])) : null;
$callback = isset($_GET['c']) ? trim($_GET['c']) : null;

if(!in_array(strtolower($zone), array('eu', 'us')) || !$realm || !$guild || !$callback) {
	header("HTTP/1.0 404 Not Found");
	die("File not found");
}
require_once '../WowPhpTools/src/Armory.php';

// check cache
$cachefile = 'cache/guild/'.$zone.'/'.$realm.'/'.$guild;
if(!file_exists($cachefile) || filemtime($cachefile) < strtotime("-1 hour")) {
	$dir = dirname($cachefile);
	if(!is_dir($dir) && !mkdir($dir, 0777, true)) {
		header("HTTP/1.0 500 Internal Server Error");
		die("Cache couldn't be generated");
	}

	touch($cachefile); // prevents double generating cache

	$armory = new WowPhpTools_Armory(require('urls_'.$zone.'.php'));
	try {
		$xml = $armory->getGuildRoster($realm, $guild);
	}
	catch(WowPhpTools_Exception $e) {
		header("HTTP/1.0 500 Internal Server Error");
		die("Internal Server Error");
	}

	if(!isset($xml->guildInfo->guild)) {
		header("HTTP/1.0 404 Not Found");
		die("File not found");
	}

	function getStr($xml, $key) {
		return isset($xml[$key]) ? (string) $xml[$key] : null;
	}

	$guildHeader = $xml->guildInfo->guildHeader;
	$emblem = $guildHeader->emblem;

	$members = array();
	foreach($xml->guildInfo->guild->members->character as $char) {
		$members[] = array(
			'achPoints' => getStr($char, 'achPoints'),
			'classId' => getStr($char,'classId'),
			'genderId' => getStr($char,'genderId'),
			'level' => getStr($char,'level'),
			'name' => getStr($char,'name'),
			'raceId' => getStr($char,'raceId'),
			'rank' => getStr($char,'rank'),
			'url' => $armory->getCharacterUrl($realm, getStr($char,'name')),
		);
	}

	$data = array(
		'url' => $armory->getGuildRosterUrl($realm, $guild),
		'name' => getStr($guildHeader, 'name'),
		'battleGroup' => getStr($guildHeader, 'battleGroup'),
		'faction' => getStr($guildHeader, 'faction'),
		'realm' => getStr($guildHeader, 'realm'),
		'emblem' => array(
			'emblemBackground' => getStr($emblem, 'emblemBackground'),
			'emblemBorderColor' => getStr($emblem, 'emblemBorderColor'),
			'emblemBorderStyle' => getStr($emblem, 'emblemBorderStyle'),
			'emblemIconColor' => getStr($emblem, 'emblemIconColor'),
			'emblemIconStyle' => getStr($emblem, 'emblemIconStyle'),
			'swfFlashvars' => 'emblemstyle=37&emblemcolor=14&embborderstyle=4&embbordercolor=14&bgcolor=45&faction=1&imgdir=/_images/tabard_images/',
			'swf' => 'http://eu.wowarmory.com/_images/emblem_ex.swf',
		),
		'members' => $members
	);


	$json = json_encode($data);
	file_put_contents($cachefile, $json);
}
else {
	$json = file_get_contents($cachefile);
}

echo $_GET['c']."(".$json.");";
