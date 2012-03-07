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

/**
 * RelatedSources class.
 * Calculates the correlation between a collection of documents
 */
class RelatedSources {

	public $distributions = array();
	
	
	/**
	 * Get our corpus ready. First we strip out all common words specified in our training data,
	 * then loop through each document and generate a frequency table.
	 * 
	 * @access public
	 * @param mixed $source 
	 * @param mixed $training
	 * @return void
	 */
	public function __construct($source,$training) {
		$this->training = $training;
		if($training === true) {
			$this->distributions = $source;
		} else {
			foreach($training as $key => $word) {
				$training[$key] = " $word ";
			}
			foreach($source as $text) {
				if($text) {
					$this->distributions[] = new Distribution(str_ireplace($training,' ',$text));
				}
			}
		}
	}
	
	
	/**
	 * Calculate the mutual information between the given text and all the documents in the corpus.
	 * 
	 * @access public
	 * @param mixed $text Text of the documents you want the correlations for
	 * @return array list of documents in the corpus and their respective correlation
	 */
	public function related($text) {
		$related = array();
		$dist = new Distribution(str_ireplace($this->training,' ',$text));
		foreach($this->distributions as $compare) {
			$related[] = $this->mutualInformation($compare,$dist);
		}
		return $related;
	}
	

	/**
	 * Modified mutual information. Calculates the mutual information between two random variables
	 * 
	 * @access public
	 * @param Distribution $distA 
	 * @param Distribution $distB 
	 * @return float The mutual information in bits
	 */
	public function mutualInformation($distA,$distB) {
		$mutualInformation = 0;
		foreach($distB as $word => $frequency) {
			$joint = 0;
			$probX = $distA($word);
			foreach($distA as $key => $var) {
				if($var > 0) {
					$conditional = ($distB($key) > 0) ? $var * $distB($key) : $var;
				} else {
					$conditional = $distB($key);
				}
				$joint += ($probX > 0 && $conditional > 0) ? $probX*log($conditional,2) : 0;
			}
			$mutualInformation += $frequency*$joint;
		}
		return (float)$mutualInformation * -1;
	}

}


/**
 * Distribution class. Cleans and generates a frequency table of a document.
 * 
 * @implements Iterator
 */
class Distribution implements Iterator {

	private $position = 0;
	private $limit = 50;
	
	
	/**
	 * Clean the text, and then generate the frequency table.
	 * 
	 * @access public
	 * @param mixed $text The text of the Document we are getting the frequencies for
	 * @return void
	 */
	public function __construct($text) {
		$text = str_replace(array("\n","\r","\t"),'',$text);
		$text = preg_replace("/[^a-zA-Z0-9\s\p{P}]/", "", $text);
		$text = trim(preg_replace('/\s\s+/', ' ', $text));
		$this->text = $text;
		$this->frequency = $this->_frequency($text);
		$this->words = array_keys($this->frequency);
		$this->size = (count(explode(' ',$text)) < $this->limit) ? count(explode(' ',$text)) : $this->limit;
	}
	
	
	/**
	 * We overide __invoke here to make the frequency easily callable.
	 * 
	 * @access public
	 * @param string $word The word you want the frequency of
	 * @return float
	 */
	public function __invoke($word) {
		$prob = @$this->frequency[$word];
		return $prob ? $prob : 0;
	}
	

	/**
	 * Count and rank the frequency of words
	 * 
	 * @access private
	 * @param mixed $text
	 * @return array
	 */
	private function _frequency($text) {
		$count = array();
		$words = explode(' ',$text);
		$num = count($words);
		foreach($words as $word) {
			if(isset($count[$word])) {
				$count[$word]++;
			} else {
				$count[$word] = 1;
			}
		}
		foreach($count as $key => $val) {
			$count[$key] = $val/$num;
		}
		arsort($count);
		return array_slice($count,0,$this->limit);
	}

    public function rewind() {
        $this->position = 0;
    }

    public function current() {
        return $this->frequency[$this->words[$this->position]];
    }

    public function key() {
        return $this->words[$this->position];
    }

    public function next() {
        ++$this->position;
    }

    public function valid() {
        return isset($this->words[$this->position]);
    }
	
}


?>