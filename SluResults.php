<?php
/***
 * Extract concepts from CRF & FST outputx
 */
class SluResults {

	/**
	 * Convert token-level array to span-level array: segment array
	 *    from $arr[$tokID]  = array($label, $tag)
	 *    to   $arr[$spanID] = array($label, array($tokIDs));
	 *
	 * @param  array   $arr      token array
	 * @return array   $out      span array
	 */
	public function token2span($arr) {
		$out   = array();
		$segID = 0;
		foreach ($arr as $tokID => $tok) {
			$a = explode('-', $tok);
			$tag = $a[0];
			$lbl = (isset($a[1])) ? $a[1] : NULL;
			//if $lbl == 'movie'

			switch ($tag) {
				case 'B': // new
					$segID = (!empty($out)) ? $segID + 1 : $segID;
					$out[$segID] = array($lbl, array($tokID));
				break;

				case 'I': // add
				case 'E':
					if ($out[$segID][0] != $lbl) { // new chunk
						$segID = (!empty($out)) ? $segID + 1 : $segID;
						$out[$segID] = array($lbl, array($tokID));
					}
					else {
						$out[$segID][1][] = $tokID;
					}

				break;

				default: // 'O'
					//$segID = (!empty($out)) ? $segID + 1 : $segID;
					//$out[$segID] = array($lbl, array($tokID));
				break;
			}
		}

		return array_values($out);
	}

	/**
	 * Extract concepts from SLU output
	 */
	public function getConcepts($utt_str, $slu_str) {
		$out   = array();
		$utt_arr = preg_split('/\s/u', $utt_str, -1, PREG_SPLIT_NO_EMPTY);
		$slu_arr = preg_split('/\s/u', $slu_str, -1, PREG_SPLIT_NO_EMPTY);

		$spans = $this->token2span($slu_arr);

		foreach ($spans as $span) {
			$label = $span[0];
			$toks  = array();
			foreach ($span[1] as $tokID) {
				$toks[] = $utt_arr[$tokID];
			}
			$out[$label] = implode(' ', $toks);
		}

		return $out;
	}
}
