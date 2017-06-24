<?php
/**
 * Class for Attribute-Value & Utterance label to SQL Query Conversion
 * 
 * @author estepanov
 */
class Slu2DB {
	
    // Define a function to debug in peace
    function logger( $data, $name ){
        error_log(print_r(json_encode($name), TRUE)); 
        error_log(print_r(json_encode($data), TRUE));
    }


	/**
	 * Map SLU concepts & utterance classes to DB columns
	 * 
	 * EXTEND!
	 */
	private $mapping = array(
		'actor'          => 'actors',
		'actor.name'     => 'actors',
		'movie'          => 'title',
		'movie.name'     => 'title',
		'director'       => 'director',
		'director.name'  => 'director',
		'character'      => 'character',
		'character.name' => 'character',
		'producer.name'  => 'producer',
		'producer'       => 'producer',
		'country.name'   => 'country',
		'release_date'   => 'year',
		'movie.release_date'   => 'year',
		'genre'          => 'genre',
		'movie_other' 	 => 'title'
	);
	
	/**
	 * Returns db column w.r.t. $str
	 */
	public function db_mapping($str) {
		logger($str, 'calling db_mapping with');
		return $this->mapping[$str];
	}
	
	/**
	 * Meta function to
	 * - map slu concepts to DB
	 * - map utterance classifier class to db
	 * - construct sql query
	 */
	public function slu2sql($concepts, $class) {
		logger($class, 'slu2sql class');
		$db_class    = $this->db_mapping($class);
		
		$db_concepts = array();
		foreach ($concepts as $attr => $val) {
			$db_concepts[$this->db_mapping($attr)] = $val;
		}
		
				
		// construct SQL query
		$query  = 'SELECT ';
		$query .= $db_class;
		$query .= ' FROM movie WHERE ';
		
		$tmp = array();
		foreach ($db_concepts as $attr => $val) {
			//$tmp[] = $attr . ' LIKE "%' . $val . '%"';
			$tmp[] = $attr . ' LIKE \'' . $val . '%\'';
		}
		$query .= implode(' AND ', $tmp);
		$query .= ';';

		logger($query, 'QUERY');
		
		return $query;
	}
}
