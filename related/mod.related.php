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
 
if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Related {


	/**
	 * Constructor, grabs a reference to the EE object and prepares the training text.
	 * 
	 * @access public
	 * @return void
	 */
	function Related() {
		// Make a local reference to the ExpressionEngine super object
		if(!class_exists('RelatedSources')) {
			include('libraries/related_core.php');
		}
		$this->training = explode("\n",file_get_contents(dirname(__FILE__).'/libraries/training.txt'));
		foreach($this->training as $key => $val) {
			$this->training[$key] = " $val ";
		}
		$this->EE =& get_instance();
	}


	/**
	 * The main action for the module. Grabs the specified number of related entries.
	 * Tries to grab cached values first, if the cache is processing we use _fastRelated.
	 * 
	 * @access public
	 * @return string The parsed template data.
	 */
	function entries() {
		if (($entry_id = $this->EE->TMPL->fetch_param('entry_id')) === FALSE) return;
		// check if the field name is set and exists
		if ($this->EE->TMPL->fetch_param('field')) {
			$field = $this->EE->TMPL->fetch_param('field');
			$query = $this->EE->db->query("SELECT field_id from exp_channel_fields WHERE field_name = ?",array($field));
			$field_id = $query->row_array();
			$field_id = $field_id['field_id'];
			$this->field_id = $field_id;
			// bogus field
			if(!$field_id) {
				return;
			}
		} else {
			return;
		}
		$cache = $this->EE->TMPL->fetch_param('cache');
		$this->cache = $cache == 'false' ? false : true;
		$paramLimit = $this->EE->TMPL->fetch_param('limit');
		$limit	= ( !$paramLimit OR !is_numeric($paramLimit)) ? 5 : $paramLimit;
		// check if caching is turned on
		if($this->cache) {
			if($this->_checkCache()) {
				// caching is now handled in cache.php via cron
				// $this->_generateCache();
				$output = $this->_getFastRelated($entry_id,$limit);
			} else {
				$output = $this->_getCached($entry_id,$limit);
			}
		} else {
			$output = $this->_getRelated($entry_id,$limit);
		}
		$output = $this->EE->TMPL->parse_variables($this->EE->TMPL->tagdata, $output); 
		return $output;
	}
	
	
	/**
	 * Calculates the related entries on the fly, no cacheing here. 
	 * This is only used when the cache is turned off.
	 * 
	 * @access private
	 * @param mixed $entry_id The entry to find relations for
	 * @param mixed $limit The numbers of entries to grab
	 * @return array Sorted array of related entries and their correlations.
	 */
	private function _getRelated($entry_id,$limit) {
		$query = $this->EE->db->query("SELECT exp_channel_titles.entry_id as entry_id, title, author_id, field_id_{$this->field_id}  as post FROM exp_channel_titles LEFT JOIN exp_channel_data ON exp_channel_data.entry_id = exp_channel_titles.entry_id WHERE field_id_{$this->field_id} != ''");
		foreach($query->result_array() as $row) {
			if($row['entry_id'] == $entry_id) {
				$search = $row['post'];
			} else {
				$posts[] = $row;
				$sources[] = $row['post'];
			}
		}
		$related = new RelatedSources($sources,$this->training);
		$similar = $related->related($search);
		arsort($similar);
		$i = 0;
		foreach($similar as $key => $correlation) {
			if($i < $limit) {
				$recc = $posts[$key];
				$recc['correlation'] = $correlation;
				$recommendations[] = $recc;
			} else {
				break;
			}
			$i++;
		}
		//var_dump($recommendations);
		return $recommendations;
	}
	

	/**
	 * For now this just grabs random entries (pretty fast!).
	 * We need a quick algorithm here that will calculate related entries.
	 * 
	 * @access private
	 * @param mixed $entry
	 * @param mixed $limit
	 * @return void
	 */
	private function _getFastRelated($entry,$limit) {
		$query = $this->EE->db->query("SELECT exp_channel_titles.entry_id as entry_id, title, author_id, field_id_{$this->field_id}  as post FROM exp_channel_titles LEFT JOIN exp_channel_data ON exp_channel_data.entry_id = exp_channel_titles.entry_id WHERE field_id_{$this->field_id} != '' AND exp_channel_data.entry_id != ? ORDER BY rand() LIMIT {$limit}",array($entry));
		foreach($query->result_array() as $row) {
			$row['correlation'] = 0;
			$posts[] = $row;
		}
		return $posts;
	}
	

	/**
	 * Check if there are pending entries to be cached. If there are any added,
	 * deleted, or updated entries set our public varibales for later processing and return true.
	 *
	 * @access public
	 * @return bool
	 */
	public function _checkCache() {
		$cachedResults = $this->EE->db->query("SELECT entry_id, last_cache FROM exp_related_posts WHERE field_id = ?",array($this->field_id));
		$cachedEntries = array();
		$cache = array();
		foreach($cachedResults->result() as $row) {
			$cache[$row->entry_id] = $row->last_cache;
			$cachedEntries[] = $row->entry_id;
		}
		$cached = $cachedResults->num_rows();
		
		$postResults = $this->EE->db->query("SELECT exp_channel_data.entry_id, timestamp(edit_date) as last_update FROM exp_channel_data LEFT JOIN exp_channel_titles ON exp_channel_data.entry_id = exp_channel_titles.entry_id WHERE field_id_{$this->field_id} != ''",array($this->field_id));
		$posts = $postResults->num_rows();
		$postedEntries = array();
		// now we loop through and see if anything has has been updated
		$this->expired = array();
		foreach($postResults->result() as $post) {
			$postedEntries[] = $post->entry_id;
			if(!isset($cache[$post->entry_id]) || $post->last_update > $cache[$post->entry_id]) {
				$this->expired[] = $post->entry_id;
			}
		}
		$this->deleted = array_diff($cachedEntries,$postedEntries);
		$this->added = array_diff($postedEntries,$cachedEntries);
		$this->expired = array_diff($this->expired,$this->added);
		if(!empty($this->deleted) || !empty($this->added) || !empty($this->expired)) {
			return true;
		}
		return false;
	}
	
	
	/**
	 * Grab the encoded related array from the db, loop through and add the entry data.
	 * 
	 * @access private
	 * @param mixed $entry_id The entry to find relations for
	 * @param mixed $limit The numbers of entries to grab
	 * @return array Sorted array of related entries and their correlations.
	 */
	private function _getCached($entry,$limit) {
		$query = $this->EE->db->query("SELECT related FROM exp_related_posts WHERE entry_id = ?",array($entry));
		$row = $query->row();
		if($query->num_rows() > 0) {
			$related = json_decode($row->related);
			//var_dump($related);
			if($related) {
				$related = get_object_vars($related);
				arsort($related);
				//var_dump($related);
				$i=0;
				$related_id = "";
				foreach($related as $id => $val) {
					if($i >= $limit) {
						break;
					}
					//var_dump($id);
					$related_id .= $id . (($i<$limit-1) ? ',' : '');
					$i++;
				}
				//var_dump($related_id);
				$query = $this->EE->db->query("SELECT exp_channel_titles.entry_id as entry_id, title, author_id, field_id_{$this->field_id} as post FROM exp_channel_titles LEFT JOIN exp_channel_data ON exp_channel_data.entry_id = exp_channel_titles.entry_id WHERE exp_channel_data.entry_id IN ($related_id)");
				foreach($query->result_array() as $row) {
					$query = $this->EE->db->query("SELECT url_title FROM exp_channel_data
													LEFT JOIN exp_channel_titles ON exp_channel_titles.entry_id = exp_channel_data.entry_id
													WHERE field_id_39 = ?",array($row['author_id']));

					$info = $query->row();
					$recc = $row;
					$recc['correlation'] = $related[$row['entry_id']];
					$recc['related_url'] = $info->url_title;
					$recommendations[] = $recc;
				}
				//var_dump($recommendations);
				return $recommendations;
			}
		}
	}
	

	/**
	 * Generate the new frequency tables for our added and expired entries, and then
	 * kick off _generateRelated. Will return false if cacheing fails, otherwise returns true.
	 * You may need to adjust the time limit depending on how much data you are processing.
	 *
	 * @access public
	 * @return bool
	 */
	public function _generateCache() {
		set_time_limit(100000);
		// first delete the old posts
		if($this->deleted) {
			$deleted = implode(",",$this->deleted);
			$query = $this->EE->db->query("DELETE FROM exp_related_posts WHERE entry_id IN ($deleted);");
			echo "Deleting " . count($this->deleted) . " cached entries\n";
		}
		// now let's add our new frequency distributions
		$sources = array_fill_keys(array_merge($this->added,$this->expired),null);
		foreach($sources as $key => $val) {
			$sources[$key] = $this->_getSource($key);
		}
		echo "Processing frequency tables (". count($sources) . ")";
		$distributions = array_map(array($this,'_generateDistribution'),$sources);
		foreach($distributions as $entry => $distribution) {
			$this->_setDistribution($entry,$distribution);
		}
		echo "\nAdded " . count($sources) . " frequency tables\nGenerating related\n";
		// and finally we generate our new relations
		return $this->_generateRelated();
	}
	
	
	/**
	 * The meat of the cacheing happens here. First we grab all the related arrays from the database,
	 * scrub the deleted entries, and then process the rest in memory to reduce DB calls.
	 *
	 * @access private
	 * @return bool
	 */
	private function _generateRelated() {
		// grab our corpus
		$query = $this->EE->db->query("SELECT entry_id, frequency, related FROM exp_related_posts WHERE field_id = ?",array($this->field_id));
		foreach($query->result() as $distribution) {
			$entries[] = $distribution->entry_id;
			$sources[] = unserialize($distribution->frequency);
			$relatedObjs[$distribution->entry_id] = (array)json_decode($distribution->related);
		}
		$related = new RelatedSources($sources,true);
		// process deleted
		foreach($this->deleted as $entry) {
			unset($relatedObjs[$entry]);
			foreach($relatedObjs as $similar) {
				unset($similar[$entry]);
			}
		}
		// process expired and added entries
		$updated = implode(',',array_merge($this->expired,$this->added));
		$query = $this->EE->db->query("SELECT entry_id, field_id_{$this->field_id} as post FROM exp_channel_data WHERE entry_id IN ($updated);");
		foreach($query->result() as $row) {
			$start = $this->_microtime_float();
			$similar = $related->related($row->post);
			arsort($similar);
			$recommended = array();
			$i = 0;
			foreach($similar as $key => $val) {
				if($i == 0) {
					// first element is itself, we need the key so we can remove it later
					$sourcesKey = $key;
				}
				$recommended[$entries[$key]] = $val;
				$relatedObjs[$entries[$key]][$row->entry_id] = $val; 
				$i++; 
			}
			// remove the current source from the sources list
			// for n sources, on the first iteration we have to check against n other sources, on the second iteration n - 1
			// so for all n sources we must compute the mutual information n*(n-1)/2 times 
			unset($related->distributions[$sourcesKey]);
			echo "Sources: " . count($related->distributions) . "\n";
			// highest rated recommendation is itself, so lets remove it
			$recommended = array_slice($recommended,1,count($recommended),true);
			$relatedObjs[$row->entry_id] = $recommended;
			$time = $this->_microtime_float() - $start;
			echo "entry {$row->entry_id} took $time seconds\n";
		}
		// we're done! write all of our objects back to the db
		foreach($relatedObjs as $entry => $obj) {
			$similar = json_encode($obj);
			$query = $this->EE->db->query("UPDATE exp_related_posts SET related = ? WHERE entry_id = ?",array($similar,$entry));
		}
		return true;
	}
	
	private function _generateDistribution($source) {
		echo ".";
		return new Distribution(str_ireplace($this->training,' ',$source));
	}
	

	/**
	 * Updates an entry's frequency table to the provided distribution.
	 * 
	 * @access private
	 * @param int $entry The id of the entry we are updating.
	 * @param Distribution $dist The new distribution object.
	 * @return void
	 */
	private function _setDistribution($entry,$dist) {
		$countQuery = $this->EE->db->query("SELECT * FROM exp_related_posts WHERE entry_id = ?",array($entry));
		if($countQuery->num_rows() > 0) {
			$query = $this->EE->db->query("UPDATE exp_related_posts SET frequency = ? WHERE entry_id = ?",array(serialize($dist),$entry));
		} else {
			$query = $this->EE->db->query("INSERT INTO `exp_related_posts` (`entry_id`,`field_id`,`frequency`,`related`,`last_cache`) VALUES (?,?,?,null,CURRENT_TIMESTAMP)",array($entry,$this->field_id,serialize($dist)));
		} 
	}
	
	
	/**
	 * Grab the text for an entry.
	 * 
	 * @access private
	 * @param int $entry
	 * @return string
	 */
	private function _getSource($entry) {
		$query = $this->EE->db->query("SELECT field_id_{$this->field_id} AS source FROM exp_channel_data WHERE entry_id = ?",array($entry));
		$row = $query->row();
		return $row->source;
	}
	

	/**
	 * Utility function for getting the time in microseconds. Used for timing.
	 * 
	 * @access private
	 * @return float Time in microseconds
	 */
	private function _microtime_float() {
    	list($usec, $sec) = explode(" ", microtime());
    	return ((float)$usec + (float)$sec);
	}
	
}