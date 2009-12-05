<?php
/*
 * This file is part of WowPhpToolsWebService  <http://github.com/christiaan/WowPhpToolsWebService >
 *
 * WowPhpToolsWebService
 * Copyright (C) 2009  Christiaan Baartse <christiaan@baartse.nl>
 *
 * WowPhpToolsWebService is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * WowPhpToolsWebService is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
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
			'flashvars' => array(
				'emblemstyle' => getStr($emblem, 'emblemIconStyle'),
				'emblemcolor' => getStr($emblem, 'emblemIconColor'),
				'embborderstyle' => getStr($emblem, 'emblemBorderStyle'),
				'embbordercolor' => getStr($emblem, 'emblemBorderColor'),
				'bgcolor' => getStr($emblem, 'emblemBackground'),
				'faction' => getStr($guildHeader, 'faction'),
				'imgdir' => 'http://eu.wowarmory.com/_images/tabard_images/'
			),
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

$expires = 3600; // 1 hour
header("Pragma: public");
header("Cache-Control: maxage=".$expires);
header('Expires: ' . gmdate('D, d M Y H:i:s', time()+$expires) . ' GMT');
echo $_GET['c']."(".$json.");";
