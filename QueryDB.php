<?php
/*
 * Class to connect and query DB
 */

class QueryDB {
	
	public function query($sql) {
	
		// connect
		define("DB_HOST", "127.0.0.1");
		define("DB_USER", "lus");
		define("DB_PASS", "luspassword");
		define("DB_NAME", "moviedb");
		
		$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
		
		//print_r($mysqli);
		
		if ($mysqli->connect_errno) {
			printf("Connect failed: %s\n", $mysqli->connect_error);
			exit();
		}
		
		// query
		logger($sql , "Query SQL inside -->");
		$result = $mysqli->query($sql);
		logger($result , "Query result --> ");
		
		if (!$result) {
			echo "DB Error, could not query the database\n";
			echo 'MySQL Error: ' . mysql_error();
			exit;
		}


		$db_results = array();
		while ($row = $result->fetch_assoc()) {
			if(array_key_exists("title", $row)){
				$row["title"] = utf8_encode($row["title"]);
			}
			$db_results[] = $row;
			//echo $row[$class] . "\n";
			//echo "<br/>";
		}
		//print_r($db_results);

		logger($db_results , "Query db_result --> ");

		$result->free();
		$mysqli->close();
		
		return $db_results;
	}
	
}
