<?php
/***
 * FST Utilities
 */
class FstUtilities {

	protected $lex;

	/**
	 * Read lexicon into array
	 *
	 * <eps>	0
	 * <unk>	1
	 *
	 * @param file $file
	 */
	protected function readLexicon($file) {
		$lines = array_map('trim', file($file));
		foreach ($lines as $line) {
			if ($line != '') {
				$la = preg_split('/\s/u', $line, -1, PREG_SPLIT_NO_EMPTY);
				$this->lex[$la[1]] = $la[0];
			}
		}
	}

	/**
	 * Convert string to FSM & write to file
	 *
	 * @param string $str
	 * @param string $fout output file name
	 */
	protected function text2fsm($str, $fout, $unk) {
		$fh  = fopen($fout, 'w');
		$arr = preg_split('/\s/u', $str, -1, PREG_SPLIT_NO_EMPTY);
		foreach ($arr as $k => $tok) {
			$tmp = array();
			$tmp[] = $k;     // begin state
			$tmp[] = $k + 1; // end state
			// token
			$tmp[] = (in_array($tok, $this->lex)) ? $tok : $unk;
			fwrite($fh, implode("\t", $tmp) . "\n");
		}
		fwrite($fh, $k + 1 . "\n");
		fclose($fh);
		//chmod($fout, 0777);
	}

	/**
	 * Compile fst
	 *
	 * @param file   $fsm  input  file name (fsm in text format)
	 * @param string $fout output file name
	 * @param file   $ilex input  lexicon
	 * @param file   $olex output lexicon
	 * @param bool   $fst  transducer or acceptor
	 */
	protected function FstCompile($fsm, $fout, $ilex, $olex = NULL, $fst = TRUE) {
		$cmd  = 'fstcompile';
		$cmd .= ' --isymbols=' . $ilex;
		if ($fst) {
			$cmd .= ' --osymbols=' . $olex;
		}
		else {
			$cmd .= ' --acceptor';
		}
		$cmd .= ' ' . $fsm . ' > ' . $fout;
		exec($cmd);
		//chmod($fout, 0777);
	}

	/**
	 * Compute confidences from costs as normalized probability
	 *
	 * @param  array $arr
	 * @return array $confs
	 */
	protected function computeConfidences($arr) {
		$confs = array();
		$probs = array_map(array($this, 'cost2prob'), $arr);
		$sum   = array_sum($probs);
		foreach ($probs as $label => $p) {
			$confs[$label] = $p / $sum;
		}
		return $confs;
	}

	/**
	 * Converts negative log-probability to plain probability
	 *
	 * @param  float $num
	 * @return float
	 */
	protected function cost2prob($num) {
		return exp(-$num);
	}

	/**
	 * fstprintstrings command
	 *
	 * @param  file   $ilex input  lexicon
	 * @param  file   $olex output lexicon
	 * @return string $cmd
	 */
	protected function fstprintstrings($ilex, $olex) {

		$cmd  = 'fstprintstrings --use_separator --print_weight';
		$cmd .= ' --isymbols=';
		$cmd .= $ilex;
		$cmd .= ' --osymbols=';
		$cmd .= $olex;

		return $cmd;
	}

}
