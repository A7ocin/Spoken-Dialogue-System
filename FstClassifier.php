<?php
/**
 * OpenFST library based Naive Bayes Classifier
 *
 * requires fstprintstrings
 *
 * [scroll down for example usage]
 */
require_once 'FstUtilities.php';

class FstClassifier extends FstUtilities {

	private $classifier;

	private $unk;
	private $ilex;
	private $olex;
	private $fsmout = 'str.fsm'; // output file name for text FST
	private $fstout = 'str.fst'; // output file name for compiled FST

	public function __construct($classifier, $ilex, $olex, $unk = '<unk>') {
		$this->classifier = $classifier;
		$this->ilex = $ilex;
		$this->olex = $olex;
		$this->unk  = $unk;

		// read lexicon for checking for unknown words
		$this->readLexicon($ilex);
	}

	/**
	 * Classify input FST using classifier FST
	 *
	 * @param  string $input
	 * @return array  $results
	 */
	private function FstClassify($input) {

		$this->text2fsm($input, $this->fsmout, $this->unk);
		$this->FstCompile($this->fsmout, $this->fstout, $this->ilex, $this->olex, FALSE);

		// compile pipeline
		$cmd  = "fstcompose $this->fstout $this->classifier";
		$cmd .= ' | ';
		$cmd .= $this->fstprintstrings($this->ilex, $this->olex);

		exec($cmd, $out);

		//parse output
		$results = array();
		foreach ($out as $line) {
			$la = explode("\t", trim($line));
			$lbl_arr = array_unique(preg_split('/\s/u', $la[1], -1, PREG_SPLIT_NO_EMPTY));
			$label  = $lbl_arr[0];
			$weight = $la[2];
			$results[$label] = $weight;
		}

		return $results;
	}

	/**
	 * predict label(s): main classification method
	 *
	 * @param  string  $str   string to classify
	 * @param  bool    $conf  whether to output confidences
	 * @param  mixed   $nbest whether to output nbest (number)
	 *
	 * @return mixed
	 */
	public function predict($str, $conf = FALSE, $nbest = FALSE) {

		$out   = $this->FstClassify($str);
		$confs = $this->computeConfidences($out);
		arsort($confs);

		// get nbest list
		if ($nbest) {
			$results = array_slice($confs, 0, $nbest);
		}
		else { // 1st best
			$results = array_slice($confs, 0, 1);
		}

		if (!$conf && $nbest) {
			$results = array_keys($results);
		}
		elseif (!$conf && !$nbest) {
			$results = array_keys($results);
			$results = $results[0];
		}
		else {
			$tmp = array();
			foreach ($results as $k => $v) {
				$tmp[] = array($k, $v);
			}
			$results = $tmp;
		}

		return $results;
	}
}

/***
 * Example Usage
 *
 * -c classifier FST
 * -s string for classification
 * -i input lexicon file
 * -o output lexicon file    [optional, uses input, if not set]
 * -u unknown symbol         [optional]
 */
/*
$args = getopt('c:s:i:o:u:');
$fst  = $args['c'];
$unk  = (isset($args['u'])) ? $args['u'] : '<unk>';
$ilex = $args['i'];
$olex = (isset($args['o'])) ? $args['o'] : $args['i'];

$str = trim($args['s']);

$UC = new FstClassifier($fst, $ilex, $olex, $unk);
echo $UC->predict($str) . "\n";
print_r($UC->predict($str, TRUE));
print_r($UC->predict($str, TRUE, 2));
print_r($UC->predict($str, FALSE, 2));

*/
