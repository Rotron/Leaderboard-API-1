<?php
// TODO: Get Requests

// Allow third parties to use
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header("Access-Control-Allow-Headers: X-Requested-With");
    
$connection = connect_to_database();

if($_SERVER['REQUEST_METHOD'] === "POST" ){
    
    if( true || validate_key($_POST['key'], $connection) ){  // Posts require API Key Validation
        post_request($connection);
    }else{
        handle_unauthorized_user();
    }
    
}else if($_SERVER['REQUEST_METHOD'] === "GET" ){
    get_request($connection); // Gets should be free to access, no validation neccessary
}else{
    echo("Invalid HTTP Request type");
}

function api_error($msg, $response){
    $error = new stdClass();
    $error->error = $msg;
    $error->http_response = $response;
    
    return $error;
}

function handle_unauthorized_user(){
    
    $error = api_error("A key is required to post scores to the leaderboard", 401);
    echo(json_encode($error));
    http_response_code($error->http_response); // Unauthorized key
    
}


function validate_key($k, $mysqli){
    
    if(!$k){return 0;}
    
    $stmt = $mysqli->prepare("SELECT game FROM api where api_key=?");
    $stmt->bind_param("s", $k);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return ($result->num_rows === 1);
}


function post_request($db_conn){
    $player = $_POST['player'];
    $level = strtoupper( $_POST['level'] ); // Keep everything capital to avoid confusion
    $score = $_POST['score'];
    
    if($player && $level && $score){
        
        $stmt = $db_conn->prepare("INSERT INTO scores (
    	        PLAYER,
    	        LEVEL,
    	        SCORE,
    	        DATE
            ) VALUES (
	            ?,
	            ?,
	            ?,
	            NOW()
            );");
            
        $stmt->bind_param("ssd", $player, $level, $score);
        $stmt->execute();
        
        echo("{'success': 'success'}");
        
    }else{
        $error = api_error("Missing some data in the Post request", 400);
        echo(json_encode($error));
        http_response_code($error->http_response); // Bad request
    }
}

function get_request($db_conn){
    $level = $_GET['level'];
    $sort = strtoupper($_GET['sort']) == "ASC" ?  "ASC" : "DESC" ; // Default is DESC unless specified otherwise
    $number = $_GET['number'] > 0 ? $_GET['number'] : 5; // Default is 5 unless specified otherwise
    
    if($level){
        
        $stmt = $db_conn->prepare("SELECT * FROM scores WHERE LEVEL = ? ORDER BY SCORE {$sort} LIMIT ?;");
        $stmt->bind_param("si", $level, $number);  
        $stmt->execute();
        
        $result = $stmt->get_result();
        
        $json_results = array();
        
        while($row = $result->fetch_assoc()){
            array_push($json_results, $row);
        }
        
        $json_results = json_encode($json_results);
        echo($json_results);
         
    }else{
        $error = api_error("Missing some data in the Get request", 400);
        echo(json_encode($error));
        http_response_code($error->http_response); // Bad request
    }
    
}


function connect_to_database(){
    $servername = getenv('IP');
    $username = "username"; // Highly recommended to change these for production
    $password = "password"; // Also remember to DROP THE USER
    $database = "c9";
    $dbport = 3306;

    // Create connection
    $db = new mysqli($servername, $username, $password, $database, $dbport);

    // Check connection
    if ($db->connect_error) {
        die("Connection failed. ");
    }
    
    return $db;
}


?>