<?php

//require_once('FirePHPCore/lib/FirePHPCore/FirePHP.class.php');
//ob_start(); // Starts FirePHP output buffering

require_once("utilities_statistics.php");


//$firephp = FirePHP::getInstance(true);

$use_session_cache = true; 
// This controls if the table_manager objects are stored in $_SESSION or not.
// It looks like doing it cuts down considerably on execution time.

if (!isset($_SESSION)) {
    session_start();
 }

//DBWrap::get_instance()->debug = true;

try{
    
    switch ($_REQUEST['oper']) {
	    case 'uf':
	        echo make_active_time_lines('uf');
	        exit;
	    case 'provider':
	        echo make_active_time_lines('provider');
	        exit;
	    case 'product':
	        echo make_active_time_lines('product');
	        exit;
	
	    case 'balances':
	        echo make_balances();
	        exit;

    default:
        throw new Exception("ctrlStatistics: operation {$_REQUEST['oper']} not supported");
        
    }
    
} 

catch(Exception $e) {
    header('HTTP/1.0 401 ' . $e->getMessage());
    die($e->getMessage());
}  

?>