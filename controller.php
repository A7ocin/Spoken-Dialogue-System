<?php

    ini_set('memory_limit', '2048M');
    session_start();

    //SLU processing
    require 'FstClassifier.php';
    require 'FstSlu.php';
    require 'SluResults.php';

    //DB
    require 'Slu2DB.php';
    require 'QueryDB.php';

    //Paths
    $classifier = 'models/MAP.fst';             //Classifier
    $cilex      = 'models/classifier.lex';      //Lexicon input symbols (classifier)
    $colex      = 'models/classifier.lex';      //Lexicon output symbols (classifier)
    $lm         = 'models/languageModel.lm';    //Language model
    $wfst       = 'models/wordToConcept.fst';   //Machine (word to concept)
    $sluilex    = 'models/lexicon';             //Lexicon input symbols (fst)
    $sluolex    = 'models/lexicon';             //Lexicon output symbols (fst)
    $unk        = '<unk>';                      //Unk symbol

    //SLU and DB variables
    $UC  = new FstClassifier($classifier, $cilex, $colex, $unk);
    $SLU = new FstSlu($wfst, $lm, $sluilex, $sluolex, $unk);
    $SR  = new SluResults();
    $QC  = new Slu2DB();
    $DB  = new QueryDB();

    //Thresholds
    $UC_th  = 0.93;
    $SLU_th = 0.87;


    //Check if the SLU confidence is above the threshold
    function checkSLUTh($SLU_result, $SLU_confidence){
        global $SLU_th;

        logger($SLU_result, "SLU Result:");
        logger($SLU_confidence, "SLU Confidence:");

        if ($SLU_confidence >= $SLU_th){
            return $SLU_result;
        } 
        else {
            foreach($SLU_result as $tag=>$text){
                $content = array('text' => $text, 'tag' => $tag);
                send($content, 'askForSLUConfirmation');
                break; //Stop when an element is found
            }
        }
    }

    //Check if the UC confidence is above the threshold
    function checkUCTh($UC_result, $UC_confidence){
        global $UC_th;

        logger($UC_result, "UC Result:");
        logger($UC_confidence, "UC Confidence:");

        if ($UC_confidence >= $UC_th) {
            return $UC_result;
        }
        else {
            $all_UCs = array();
            foreach ($UC_result as $res) {
                $all_UCs[] = $res;
            }
            send($all_UCs, 'askForUCConfirmation');
        } 
    }

    function send($array, $message) {
        $response = array('results'=>$array, 'message'=>$message);
        $json = json_encode($response);
        $callback = $_GET['callback'];
        echo $callback.'('. $json . ')';
        exit();
    }

    //Construct query and search results on the DB
    function searchDB($SLU_result, $UC_result){
        global $QC;
        global $DB;
        
        //Query construction
        $query = $QC->slu2sql($SLU_result, $UC_result);
        logger($query , "Query SQL");

        $object ="empty";
        foreach($SLU_result as $tag=>$text){
            $object = $text;
            break;
        }
        
        $old_UC = $UC_result;
        
        //Querying the DB
        $db_results = $DB->query($query);           //Run
        $UC_result = $QC->db_mapping($UC_result);   //Solve wrong mappings
       
        logger($UC_result, "TOPIC"); 
        logger($db_results, "DB response");
        
        //TODO: better response format
        //TODO: if no response, search on the web

        if(array_key_exists(0, $db_results)){
            $responseTemp = $db_results[0][$UC_result];

            for ($i=1; $i < sizeof($db_results); $i++) { 
                if (stristr($responseTemp, $db_results[$i][$UC_result]) == false) {
                    $responseTemp .= "," . $db_results[$i][$UC_result];
                }
            }

            if(strpos($responseTemp,",") != false
                || strpos($responseTemp,"|") != false){
                $response = "The " . $old_UC . "s of " . $object . " are: " . $responseTemp;
            }
            else{
                $response = "The " . $old_UC . " of " . $object . " is: " . $responseTemp;
            }
            
            $response = str_replace("_", " ", $response);
            $response = str_replace(" ,", ", ", $response);
            
            return $response;
        }
        else{
            return "=" . $old_UC . "+" . $object;
        }
        
    }

    function startAsking($question, $ASR_confidence){
        global $UC;
        global $SLU;
        global $SR;
        global $QC;
        global $DB;

        global $SLU_th;
        global $UC_th;

        logger($question, "YOU ASKED:");

        $utterance = $question;
        $utterance = trim(strtolower($utterance));

        //Run SLU
        $SLU_out = $SLU->runSlu($utterance, TRUE, 3);

        //Run UC
        $UC_out  = $UC->predict($utterance, TRUE, 3);

        //Get the first valid tag
        $SLU_tags;
        $SLU_conf;
        for ($i=0; $i < count($SLU_out); $i++) { 
            $SLU_tags = $SLU_out[$i][0];
            $SLU_conf = $SLU_out[$i][1] * $ASR_confidence;
            $results = $SR->getConcepts($utterance, $SLU_tags);
            if (!empty($results)){
                break;
            }
        }

        //If I didn't find a valid tag
        if (empty($results)){
            //TODO: search on the web
            $content = array('error' => "no_tagging");
            send($content, 'final');
        }

        //Get intent
        $UC_class = $UC_out[0][0];

        //Get intent confidence
        $UC_conf  = $UC_out[0][1];

        logger($results, "SLU Concepts and Values:");
        logger($SLU_conf, "SLU first Confidence:");

        //Set session variables
        $_SESSION["SLU_result"] = $results;
        $_SESSION["SLU_confidence"] = $SLU_conf;

        //Check if the UC confidence is above the threshold
        $UC_result = checkUCTh($UC_out, $UC_conf);
        $UC_result = $UC_result[0][0];
        $_SESSION["UC_result"] = $UC_result;

        //Check if the SLU confidence is above the threshold
        $object = checkSLUTh($results, $SLU_conf);

        //Get results from the DB
        $db_response = searchDB($object, $UC_result);

        //Send back the message
        $content = array('response' => $db_response);
        send($content, 'final');
    }

    //Logging function
    function logger( $data, $name ){
        error_log(print_r(json_encode($name), TRUE)); 
        error_log(print_r(json_encode($data), TRUE));
    }


    //Start from here <------------
    $status = $_GET['status'];

    if($status == "askForUCConfirmation" ||
        $status == "askForSLUConfirmation"){

        $content = $_GET["content"];
        $SLU_result = $_SESSION["SLU_result"];
        $SLU_confidence =  $_SESSION["SLU_confidence"];
        logger($SLU_confidence, 'ORIGINAL SLU CONFIDENCE:');
    }

    switch ($status) {
        case 'askForUCConfirmation':
            //Overwrite intent results
            $result_intent = $content["UC_result"];
            $high_UC_confidence = $content['confident'];
            if ($high_UC_confidence == 'false'){
                //Classify again the intent
                $UC_out  = $UC->predict($result_intent, TRUE, 3);
                $result_intent = $UC_out[0][0];
                logger($result_intent, 'Prediction result:');
            }

            //Save intent
            $_SESSION["UC_result"] = $result_intent;
            
            //Check if the SLU confidence is above the threshold
            $object = checkSLUTh($SLU_result, $SLU_confidence);

            //Get results from the DB
            $db_response = searchDB($object, $result_intent);

            //Send back the message
            $content = array('response' => $db_response);
            send($content, 'final');
            break;
        //case 'ask_target':
        case 'askForSLUConfirmation':
            $result_intent = $_SESSION["UC_result"];
            $result_tag = $content['tag'];
            $result_target = $content['target'];
            $is_tag_sure = $content['confident'];
            logger($is_tag_sure, "confident?");

            if ($is_tag_sure == 'false'){
                //Classify again the intent
                $UC_out  = $UC->predict($result_tag, TRUE, 3);
                $result_tag = $UC_out[0][0];
            }

            $object = array($result_tag => $result_target);

            //Get results from the DB
            $db_response = searchDB($object, $result_intent);

            //Send back the message
            $content = array('response' => $db_response);
            send($content, 'final');
            break;
        default:
            //Start from here
            $request = $_GET["content"];
            $question = $request['asrResult'];
            $ASR_confidence = $request['asrConfidence'];
            startAsking($question, $ASR_confidence);
            break;
    }

?>
