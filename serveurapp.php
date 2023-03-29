<?php
    /// Librairies éventuelles (pour la connexion à la BDD, etc.)
    //include('mylib.php');

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
    $table1='table avec tout les contenu';
    $table2='table avec les like et dislike avec id du message et id des users';
    $bearer_token=get_bearer_token();
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
            /// Récupération des critères de recherche envoyés par le Client            
            if($account_type == 'admin'){
                // Apparition d'un contenu pour les admin avec l'id du contenu
                if (!empty($_GET['id'])){
                    $req=$linkpdo->prepare("Select * from ".$table1." where id=".$_GET['id']." order by vote");
                    $req->execute();
                    $matchingData=$req->fetchAll(PDO::FETCH_ASSOC);
                    deliver_response(200, "cela a fonctionné", $matchingData);
                // Apparition de tout les contenus pour les admin avec toutes les informations
                } else {
                    $req=$linkpdo->prepare("Select * from ".$table1);
                    $req->execute();
                    $matchingData=$req->fetchAll(PDO::FETCH_ASSOC);
                    deliver_response(200, "cela a fonctionné", $matchingData);
                }
            } if($account_type == 'publisher'){
                //Affiche les articles avec des informations modéré
                $req=$linkpdo->prepare("Select * from ".$table1);
                $req->execute();
                $matchingData=$req->fetchAll();
                deliver_response(200, "cela a fonctionné", $matchingData);
            } if ($account_type=="anonyme") {
                //affiche des articles avec peu d'informations
                $req=$linkpdo->prepare("Select * from ".$table1);
                $req->execute();
                $matchingData=$req->fetchAll();
                deliver_response(200, "cela a fonctionné", $matchingData);
            }
            /// Envoi de la réponse au Client
            deliver_response(200, "Cela fonctionne correctement", $matchingData);
            break;

        /// Cas de la méthode POST
        case "POST" :
            if($account_type == 'publisher'){
                // vérifie si l'utilisateur a déja réagi au message
                if (!empty($_GET['id']) || !empty($_GET['like'])){
                    $req=$linkpdo->prepare("Select Reaction from ".$table2." where id=".$_GET['id']." and username=".$username);
                    $req->execute();
                    $matchingData=$req->fetchAll();
                    if($req == NULL){
                        $req=$linkpdo->prepare("update ".$table1." set like=like + 1 where id=".$_GET['id']." order by vote");
                        $req->execute();
                        $matchingData=$req->fetchAll();
                    } else if($_GET['like']=="-"){
                        $req=$linkpdo->prepare("update ".$table1." set dislike=dislike - 1 where id=".$_GET['id']." order by vote");
                        $req->execute();
                        $matchingData=$req->fetchAll();
                    }
                }else if (empty($_GET['id'])&& empty($_GET['like'])){
                    /// Récupération des données envoyées par le Client
                    $postedData = file_get_contents('php://input');
                    /// Traitement
                    $jsonData= json_decode($postedData);
                    $phrase=$jsonData->phrase;
                    $req=$linkpdo->prepare("Insert into ".$table1." (phrase, date_ajout) VALUES (:phrase, :date_ajout)");
                    $req->execute(array("phrase" => $phrase, "date_ajout" =>date("Y/m/d H:i:s", time())));
                    deliver_response(201, "Requete réussi", NULL);
                }else{
                    
                }
            }else{
                deliver_response(498, "Jeton invalide", NULL);
            }
        break;
        
        /// Cas de la méthode PUT
        case "PUT" :
            /// Récupération des données envoyées par le Client
            if($_GET['like']=="+"){
                $req=$linkpdo->prepare("update ".$table1." set like=like + 1 where id=".$_GET['id']);
                $req->execute();
                $matchingData=$req->fetchAll();
            } else if($_GET['like']=="-"){
                $req=$linkpdo->prepare("update ".$table1." set dislike=dislike - 1 where id=".$_GET['id']);
                $req->execute();
                $matchingData=$req->fetchAll();
            }
            $postedData = file_get_contents('php://input');
            /// Traitement
            $jsonData= json_decode($postedData);
            $phrase=$jsonData->phrase;
            $id=$jsonData->id;
            $req=$linkpdo->prepare("UPDATE ".$table1." set phrase =:phrase , date_modif=:date_modif where id=:id");
            $req->execute(array("phrase"=>$phrase, "id"=>$id, "date_modif" => date("Y/m/d H:i:s", time())));
            /// Envoi de la réponse au Client
            deliver_response(200, "3", NULL);
        break;

        /// Cas de la méthode DELETE
        case "DELETE" :
            /// Récupération de l'identifiant de la ressource envoyé par le Client
            if (!empty($_GET['id']) && $account_type == 'admin'){
                /// Traitement
                $req=$linkpdo->prepare("Delete from ".$table2." where id=".$_GET['id']);
                $req->execute();
                $req=$linkpdo->prepare("Delete from ".$table1." where id=".$_GET['id']);
                $req->execute();
            }
            if(!empty($_GET['id']) && $account_type == 'admin'){
                
            }
            /// Envoi de la réponse au Client
            deliver_response(200, "4", NULL);
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