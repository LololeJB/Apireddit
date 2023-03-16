<?php
/// Librairies éventuelles (pour la connexion à la BDD, etc.)
include('jwt_utils.php');
$login='root';
$mdp='';
$server='localhost';
$dbname='authapi';
error_log("Declaration des variables");

try{
    $linkpdo = new PDO("mysql:host=$server;dbname=$dbname", $login, $mdp);
}
catch(Exception $e){
    die('Erreur : ' . $e->getMessage());
}
error_log("connexion a la bd");

 /// Paramétrage de l'entête HTTP (pour la réponse au Client)
header("Content-Type:application/json");

 /// Identification du type de méthode HTTP envoyée par le client
$http_method = $_SERVER['REQUEST_METHOD'];
switch ($http_method){
 /// Cas de la méthode POST
case "POST" :
  error_log("passage dans le post");
 /// Récupération des données envoyées par le Client
  $postedData = file_get_contents('php://input');
  echo $postedData;
  ///Lookup pour voir si le login est valide
  $request=$linkpdo->prepare("SELECT 'type' FROM 'authorized_logins' WHERE Username = :username AND Password = :password");
  $request->execute(array('username'=>($postedData)->username,'password'=>($postedData)->password));
  error_log("data selected");
  if(isset($request)){
    error_log("isset passé");
    $header=array('alg'=>'HS256','typ'=>'JWT');
    $payload=array('username'=>json_decode($postedData)->username,'account_type'=>$type,'exp'=>(time()+3600));
    $token=generate_jwt($header,$payload);
    /// Envoi de la réponse au Client
    deliver_response(201, "Votre message",$token);
  }
  else{
    deliver_response(418,"Identifiant ou mot de passe incorrect",null);
  }
 break;
 ///Dommage...
default :
  //Lever l'erreur HTTP 501
  deliver_response(501,"La methode demandee n'existe pas",null);
}
/// Envoi de la réponse au Client
function deliver_response($status, $status_message, $data){
 /// Paramétrage de l'entête HTTP, suite
header("HTTP/1.1 $status $status_message");
/// Paramétrage de la réponse retournée
$response['status'] = $status;
$response['status_message'] = $status_message;
$response['data'] = $data;
/// Mapping de la réponse au format JSON
$json_response = json_encode($response);
echo $json_response;
}
?>