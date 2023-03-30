<?php
    /// Librairies éventuelles (pour la connexion à la BDD, etc.)
    include('jwt_utils.php');

    try{
            //les acces a la base de données
            $dbname='redditapiv2';
            $user='root';
            $host='localhost';
                $linkpdo = new PDO('mysql:host='.$host.';dbname='.$dbname.';charset=utf8',$user);
            }
            catch(Exception $e){
                die('Erreur : ' . $e->getMessage());
            }
            
    /// Paramétrage de l'entête HTTP (pour la réponse au Client)
    header("Content-Type:application/json");

    /// Identification du type de méthode HTTP envoyée par le client
    $http_method = $_SERVER['REQUEST_METHOD'];
    $table1='post';
    $table2='reaction';
    $bearer_token=get_bearer_token();
    error_log($bearer_token);
    if($bearer_token==null){
        $account_type="anonyme";
    }
    else if (is_jwt_valid($bearer_token)){
        $valeur_token =explode('.',$bearer_token);
        $payload_token = base64_decode($valeur_token[1]);
        $account_type = json_decode($payload_token)->account_type;
        $username = json_decode($payload_token)->username;
    }
    else{
        deliver_response(498, "Jeton invalide", NULL);
    }
    switch ($http_method){

        /// Cas de la méthode GET
        case "GET" :
            if (!isset($_GET['like'])) {
                /// Récupération des critères de recherche envoyés par le Client            
                if($account_type == 'admin'){
                    // Apparition d'un contenu pour les admin avec l'id du contenu
                    if (!empty($_GET['id'])){
                        $req=$linkpdo->prepare("Select * from ".$table1." where id=".$_GET['id']." order by vote");
                        $req->execute();
                        $matchingData=$req->fetchAll(PDO::FETCH_ASSOC);
                    // Apparition de tout les contenus pour les admin avec toutes les informations
                    } else {
                        error_log("execute admin");
                        $req=$linkpdo->prepare("Select * from ".$table1);
                        $req->execute();
                        $matchingData=$req->fetchAll(PDO::FETCH_ASSOC);
                    }
                } if($account_type == 'publisher'){
                    //Affiche les articles avec des informations modéré
                    $req=$linkpdo->prepare("Select * from ".$table1);
                    $req->execute();
                    $matchingData=$req->fetchAll();
                } if($account_type == 'anonyme'){
                    //affiche des articles avec peu d'informations
                    $req=$linkpdo->prepare("Select contenu from ".$table1);
                    $req->execute();
                    $matchingData=$req->fetchAll();
                }
            } else if ($_GET['like']=='+') {
                if($account_type == 'admin' || $account_type == 'publisher'){
                    // Apparition d'un contenu pour les admin avec l'id du contenu
                    if (!empty($_GET['id'])){
                        $req=$linkpdo->prepare("Select count(*) from ".$table2." where id=".$_GET['id']." and type=1");
                        $req->execute();
                        $matchingData=$req->fetch(PDO::FETCH_ASSOC);
                    // Apparition de tout les contenus pour les admin avec toutes les informations
                    } else {
                        deliver_response(501,"votre demande est indisponible", NULL);
                    }
                } else {
                    deliver_response(403, "Ce compte n'a pas accès à cette commande", NULL);
                }
            } else if ($_GET['like']=='-') {
                if($account_type == 'admin' || $account_type == 'publisher'){
                    // Apparition d'un contenu pour les admin avec l'id du contenu
                    if (!empty($_GET['id'])){
                        $req=$linkpdo->prepare("Select count(*) from ".$table2." where id=".$_GET['id']." and type=0");
                        $req->execute();
                        $matchingData=$req->fetch(PDO::FETCH_ASSOC);
                    // Apparition de tout les contenus pour les admin avec toutes les informations
                    } else {
                        deliver_response(501,"votre demande est indisponible", NULL);
                    }
                } else {
                    deliver_response(403, "Ce compte n'a pas accès à cette commande", NULL);
                }
            }
            deliver_response(200, "Cela fonctionne correctement", $matchingData);
            break;

        /// Cas de la méthode POST
        case "POST" :
            if($account_type == 'publisher'){
                if (!empty($_GET['id']) || !empty($_GET['like'])){
                    $like ='dislike';
                    if ($_GET['like'] == '+') {
                        $like = 1;
                    }
                    $req=$linkpdo->prepare("Select Reaction from ".$table2." where idMessage=".$_GET['id']." and idUser=".$username);
                    $req->execute();
                    $matchingData=$req->fetch();
                    if($matchingData == NULL){
                        $req=$linkpdo->prepare("insert into ".$table2." (reaction, idMessage, idUser) VALUES (:reaction, :idMessage, :idUser)");
                        $req->execute(array("reaction" => $like, "idMessage" => $_GET['id'], "idUser" => $idUser));
                        $matchingData=$req->fetchAll();
                    } else if($_GET['like']=="-"){
                        $req=$linkpdo->prepare("update ".$table1." set dislike=dislike - 1 where id=".$_GET['id']." order by vote");
                        $req->execute();
                        $matchingData=$req->fetchAll();
                    }
                }else if (empty($_GET['id'])&& empty($_GET['like'])){
                    $postedData = file_get_contents('php://input');
                    /// Traitement
                    $jsonData= json_decode($postedData);
                    $phrase=$jsonData->phrase;
                    $req=$linkpdo->prepare("Insert into ".$table1." (phrase, date_ajout) VALUES (:phrase, :date_ajout)");
                    $req->execute(array("phrase" => $phrase, "date_ajout" =>date("Y/m/d H:i:s", time())));
                    deliver_response(201, "Requete réussi", NULL);
                }else{
                    deliver_response(403, "Ce compte n'a pas accès à cette commande", NULL);
                }
            } else{
                deliver_response(403, "Ce compte n'a pas accès à cette commande", NULL);
            }
            break;
        
        /// Cas de la méthode PUT
        case "PUT" :
            /// Récupération des données envoyées par le Client
            if ($account_type == 'publisher') {
                if($_GET['like']=="+"){
                    $req=$linkpdo->prepare("select count(type) from ".$table2." where user='".$username."' and postid=".$_GET['id'])
                    $req->execute();
                    $result=$req->fetch();
                    if ($result==1) {
                        $req=$linkpdo->prepare("select type from ".$table2." where user='".$username."' and postid=".$_GET['id'])
                        $req->execute();
                        $result=$req->fetch();
                        if ($result == 0) {
                            $req=$linkpdo->prepare("update ".$table2." set type=1 where user='".$username."' and postid=".$_GET['id']);
                            $req->execute();
                        }
                    }
                } else if($_GET['like']=="-"){
                    $req=$linkpdo->prepare("select count(type) from ".$table2." where user='".$username."' and postid=".$_GET['id'])
                    $req->execute();
                    $result=$req->fetch();
                    if ($result==1) {
                        $req=$linkpdo->prepare("select type from ".$table2." where user='".$username."' and postid=".$_GET['id'])
                        $req->execute();
                        $result=$req->fetch();
                        if ($result == 1) {
                            $req=$linkpdo->prepare("update ".$table2." set type=0 where user='".$username."' and postid=".$_GET['id']);
                            $req->execute();
                        }
                    }
                }
                if (!isset($_GET['like'])) {
                    $postedData = file_get_contents('php://input');
                    /// Traitement
                    $jsonData= json_decode($postedData);
                    $phrase=$jsonData->phrase;
                    $id=$jsonData->id;
                    $req=$linkpdo->prepare("SELECT count(phrase) from ".$table1." where id=".$id." and idUser='".$username."'");
                    $req->execute();
                    $usermessage=$req->fetch();
                    if ($usermessage==1) {
                        $req=$linkpdo->prepare("UPDATE ".$table1." set phrase =:phrase , date_modif=:date_modif where id=:id");
                        $req->execute(array("phrase"=>$phrase, "id"=>$id, "date_modif" => date("Y/m/d H:i:s", time())));
                    }
                    
                }
                /// Envoi de la réponse au Client
                deliver_response(200, "3", NULL);
            } else {
                deliver_response(403, "Ce compte n'a pas accès à cette commande", NULL);
            }
        break;

        /// Cas de la méthode DELETE
        case "DELETE" :
            $response_code = 403;
            $response_string = "You are not allowed to perform this action";
            //Cas du modo
            if($account_type=="admin"){
                $id=$_GET['id'];
                $req=$linkpdo->prepare("Delete from ".$table2." where postid=?");
                $req->execute(array($id));
                $req=$linkpdo->prepare("Delete from ".$table1." where postid=?");
                $req->execute(array($id));
                $response_code=200;
                $response_string="delete successful";
            } ($account_type=='anonyme'){
                $id=$_GET['id'];
                $req=$linkpdo->prepare("select count(*) from ".$table1." where postid=? and author_name=".$username);
                $req->execute(array($id));
                $result=$req->fetch();
                if ($result == 1) {
                    $req=$linkpdo->prepare("Delete from ".$table2." where postid=?");
                    $req->execute(array($id));
                    $req=$linkpdo->prepare("Delete from ".$table1." where postid=?");
                    $req->execute(array($id));
                    $response_code=200;
                    $response_string="delete successful";
                }
            }
            //cas du publisher
            /// Envoi de la réponse au Client
            deliver_response($response_code, $response_string, NULL);
        break;

        default :
            deliver_response(501,"votre demande est indisponible", NULL);
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