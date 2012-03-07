<?php
/**
 * Copyright (C) 2012 Engauge
 * 
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

// base directory
$base = "public_html/index.php";
// log file
$log = "/var/log/ee.related.log";
// set the request to a page that uses the related module so EE will handle all the loading for us
// there's probably a better way to do this.
$_SERVER['PATH_INFO'] = "/path/to/template";
$_SERVER['REQUEST_URI'] = $_SERVER['PATH_INFO'];

ob_start();
chdir(dirname($base));
require($base);           // Main CI index.php file
$output = ob_get_contents();
ob_end_clean();
$ee =& get_instance();
$related = new Related();
$related->field_id = 34;
$related->_checkCache();
$query = $ee->db->query("SELECT GET_LOCK('cache',0) as locked");
$cache = $query->row();
$result = "";
if($cache->locked == 1) {
	if(!empty($related->deleted) || !empty($related->added) || !empty($related->expired)) {
		try {
			$success = $related->_generateCache();
			if($success) {
				$result = date('[D m d H:i:s]',time()) . " DELETED ". count($related->deleted) . " entries\n";
				$result .= date('[D m d H:i:s]',time()) . " UPDATED ". count($related->expired) . " entries\n";
				$result .= date('[D m d H:i:s]',time()) . " ADDED ". count($related->added) . " entries\n";
			} else {
				throw new Exception('Cache failed!');
			}
		} catch (Exception $e) {
	   		$result = date('[D m d H:i:s]',time()) . ' ERROR: '.  $e->getMessage(). "\n";
		}
	} else {
		echo "Cache up to date\n";
	}
	$query = $ee->db->query("SELECT RELEASE_LOCK('cache')");
} else {
	$result = date('[D m d H:i:s]',time()) . ' ERROR: Cache is already processing'. "\n";
}
$fp = fopen($log, 'a');
fwrite($fp, $result);
fclose($fp);
?>