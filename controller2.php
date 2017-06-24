<?php

    ini_set('memory_limit', '1024M');

    // Define a function to debug in peace
    function logger( $data, $name ){
        error_log(print_r(json_encode($name), TRUE)); 
        error_log(print_r(json_encode($data), TRUE));
    }

    function write_conf( $confidence, $type){
        $file_path = "positives.txt";
        if (!$type){
            $file_path = "negatives.txt";
        }
        $content = $confidence;
        $myfile = file_put_contents($file_path, $content.PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    // for SLU processing
    require 'FstClassifier.php';
    require 'FstSlu.php';
    require 'SluResults.php';

    // for DB
    require 'Slu2DB.php';
    require 'QueryDB.php';

    // configure paths
    $classifier = 'models/MAP.fst';
    $cilex      = 'models/classifier.lex';
    $colex      = 'models/classifier.lex';
//    $lm         = 'models/slu.lm';
//    $wfst       = 'models/wfst.fst';
    $lm         = 'models/myslu.lm';
    $wfst       = 'models/mywfst.fst';
//    $sluilex    = 'models/slu.lex';
//    $sluolex    = 'models/slu.lex';
    $sluilex    = 'models/mylexicon.lex';
    $sluolex    = 'models/mylexicon.lex';
    $unk        = '<unk>';

    $UC  = new FstClassifier($classifier, $cilex, $colex, $unk);
    $SLU = new FstSlu($wfst, $lm, $sluilex, $sluolex, $unk);
    $SR  = new SluResults();
    $QC  = new Slu2DB();
    $DB  = new QueryDB();

    // Get the utterance from the requests params
    $asr_output = $_POST['SLUObject'];
    $myArray = explode('\n', $asr_output);
    foreach ($myArray as $value) {
        $asr_output = $value;
        logger($asr_output, "input_sentence");
        $ASR_confidence = 1;
        $utterance = $asr_output;
        $utterance = trim(strtolower($utterance));
        
    }

    // Run SLU
    $SLU_out = $SLU->runSlu($utterance, TRUE, 2);

    // Run Utterance classifier
    $UC_out  = $UC->predict($utterance, TRUE, 2);

    logger($SLU_out, "slu_out");

    // Get tags
    $i = 0;
    $SLU_tags;
    $SLU_conf;
    while ($i < count($SLU_out)) {
        # code...
        $SLU_tags = $SLU_out[$i][0];
        $SLU_conf = $SLU_out[$i][1] * $ASR_confidence;
        $results = $SR->getConcepts($utterance, $SLU_tags);
        if (!empty($results)){
            break;
        } else {
            $i = $i + 1;
        }
    }

    // Extract concepts from SLU output
    $results = $SR->getConcepts($utterance, $SLU_tags);

    // Get response from classification
    $UC_class = $UC_out[0][0];

    // Get confidence from the response
    $UC_conf  = $UC_out[0][1];

    logger($results, "SLU Concepts and Values");
    logger($SLU_conf, "SLU Confidence");

    // "who directed avatar"
    // uc_class is the object of the query. What the user want to know
    // for example: director
    // $results contains something like that {"movie.name":"avatar"}

    //----------------------------------------------------------------------
    // Dialog Management & Natural Language Generation
    //----------------------------------------------------------------------
    // DEVELOP THIS PART!
    // Example
    $th_accept = 0.9;
    $th_reject = 0.4;
    $error = false;
    $error_topic;
    $error_message;

    if ($SLU_conf >= $th_accept && $UC_conf >= $th_accept) {
        //------------------------------------------------------------------
        // Convert SLU results to SQL Query
        //------------------------------------------------------------------
        $query = $QC->slu2sql($results, $UC_class);
        logger($query , "Query SQL");
        
        //------------------------------------------------------------------
        // Query DB
        //------------------------------------------------------------------
        $db_results = $DB->query($query);

        //logger($db_results, "DB Results");
        
        $response = $db_results[0][$UC_class];
    }
    elseif ($SLU_conf < $th_reject || $UC_conf < $th_reject) {
        $response = 'Sorry, I did not understand!';
        $error = true;
        if ($SLU_conf < $th_reject){
            $error_topic = "slu";
            $error_message = $results;
        } else {
            $error_topic = "uc";
            $error_message = $UC_class;
        }
    }
    else {
        $response = 'Not implemented yet!';
    }

    $tts_output = $response;
    $tts = array('results' => $tts_output);
    $json = json_encode($tts);
    $callback = $_GET['callback'];
    echo $callback.'('. $json . ')';
?>
