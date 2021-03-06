<?php

namespace App\Controllers;

use Psr\Log\LoggerInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Flash\Messages;

use App\Controllers\CommentaireController;

use App\Models\Item;
use App\Models\Liste;
use App\Models\Commentaire_item;


final class ItemController extends BaseController
{

    public function getItemsFromToken(Request $request, Response $response, $args)
    {
      $liste = Liste::where('token', '=', $request->getAttribute('route')->getArgument('token'))->first();
      $items_query = Item::where('liste_id', '=', $liste->id)->get();

      $items = $items_query->toArray();
      $commentaires = []; // liste de listes de commentaires
      $nbCommentaires = []; // liste de nombre de commnetaires (dans le meme ordre que les listes)
      $items_query->map(function ($item) use (&$commentaires, &$nbCommentaires) {
        $commentaireDeLItem = CommentaireController::getCommentaireItem($item->id);
        $commentaires[] = $commentaireDeLItem;
        $nbCommentaires[] = count($commentaireDeLItem);
      });
      return $this->container->view->render($response, "addItem.twig", ['items' => $items, 'commentaires' => $commentaires, 'nbCommentaires' => $nbCommentaires, 'token' => $args['token'],'liste'=>$liste] );
    }


    public function addItem(Request $request, Response $response, $args)
    {
        // $liste = Liste::where('token', '=', $args['token'])->first();
        // $items_query = Item::where('liste_id', '=', $liste->id)->get();
        //
        // $items = $items_query->toArray();
        //
        // $nbCommentaires = []; // liste de nombre de commnetaires (dans le meme ordre que les listes)
        // $items_query->map(function ($item) use (&$nbCommentaires) {
        //   $nbCommentaires[] = CommentaireController::nbCommentaireListe($item->id);
        // });
        // return $this->container->view->render($response, "testItem.twig", ['items' => $items, 'nbCommentaires' => $nbCommentaires, 'token' => $args['token'] ] );
    }

    private function valid($s, $max_len) {
  		$len = strlen($s);
  		return $len > 0 && $len <= $max_len;
  	}

    private function validateReservationItem($p) {
  		if (!$this->valid($p['nom'], 25)) {
  			return "le nom doit être rempli et faire moins de 25 caractères";
  		}
  		$item = Item::where('id', '=', $p['idItem'])->first();
  		if (! $item) {
  			return "item non existant";
  		}
      if ($item['reservedBy']) {
        return "item déjà reservé";
      }
  		$liste = Liste::where('token', '=', $p['token'])->first();
  		if (! $liste) {
  			return "token invalide";
  		}
  		$list_of_item = Liste::where('id', '=', $item->liste_id)->first();
  		if ($list_of_item->id != $liste->id) {
  			// nice try, hackerman
  			return "token invalide pour cet item";
  		}

  		return "ok";
  	}


    public function reservationItem(Request $request, Response $response, $args)
    {
      $post = $request->getParsedBody();
      $valid = $this->validateReservationItem($post);
      if ($valid === "ok") {
        $item = Item::where('id', '=', $post['idItem'])->first();
        $item['reservedBy'] = $post['nom'];
        $item->save();
        $this->container->flash->addMessage("Success", "Votre reservation a été enregistrée");
        return $response->withRedirect("/item/" . $post['token']);
      }
      else {
        $this->container->flash->addMessage("Error", $valid);
        return $response->withRedirect("/item/" . $post['token']);
      }
    }

    public function postItem(Request $request, Response $response, $args) {
      $postDonne=$request->getParsedBody();
      $item=new Item();
      $lists=Liste::where('id','=', $postDonne['liste_id'])->first();
      $errorAddItem=[];
      if(!array_key_exists('name',$_POST)|| $_POST['name']=='' ||  !preg_match("/[a-zA-Z0-9]+$/", $_POST['name'])){
          $errorAddItem['name']="Vous avez pas rentré un nom d'item ou nom incorrect";
      }

      if(!array_key_exists('tarif',$_POST)|| $_POST['tarif']=='' ||  !preg_match("/[0-9]+$/", $_POST['tarif'])){
          $errorAddItem['tarif']="Vous n'avez pas rentré un tarif ou format de tarif incorrect";
      }

      if(!array_key_exists('description',$_POST)|| $_POST['description']==''){
          $errorAddItem['description']="Vous n'avez pas rentré une description de l'item";
      }

      $_SESSION['errorItem']=$errorAddItem;

      if(isset ($postDonne) && $postDonne['buttonAjoutItem']=="ajoutItem"){
          if(!empty($_SESSION['errorItem'])){
              $this->container->flash->addMessage("ErrorItem", "Votre item n'a pas été enregistré :");
              return $response->withRedirect("/item/".$lists->token);
          }
          $valid=$this->validate($postDonne);
          if($valid=="ok"){
              $item->liste_id=$postDonne['liste_id'];//ToVerify
              $item->tarif=$postDonne["tarif"];
              $item->nom=$postDonne["name"];
              $item->description=$postDonne["description"];
              $url = $postDonne["url"];
              if( !empty($url)){
                $item->lien_url=$url;
              }

            $uploads_dir =$this->container->uploads.DIRECTORY_SEPARATOR ;
            $error = $_FILES["image"]["error"] ;
            if ($error == UPLOAD_ERR_OK) {
                $tmp_name = $_FILES['image']['tmp_name'];
                $name = uniqid('img-'.date('Ymd').'-');
                move_uploaded_file($tmp_name, $uploads_dir.''.$name);
                $item->lien_image = $name;
            }
            $item->save();
            $this->container->flash->addMessage("successAddItem","L'item a été ajoutée avec succès");
            return $response->withRedirect("/item/".$lists->token);
           }else{
              $this->container->flash->addMessage("ErrorPostItem",$valid);
              return $response->withRedirect("/item/".$lists->token);
          }



      }


    }

    public function deleteitem(Request $request, Response $response, $args){
      $post = $request->getParsedBody();

        $option_id = $post['delete_item_option'];
        $items = Item::find($option_id);
        $nom = $items['nom'];

          $commentaire_item = Commentaire_item::where('item_id', '=', $option_id )->get()->toArray();

          $j = 0 ;//TODO pas bien :(
          foreach ($commentaire_item as $value) {//chaque commentaire de chaque item de la liste à supprimer
            $coI = $commentaire_item[$j];
            $comItem_id = $coI['id'];

            Commentaire_item::destroy($comItem_id);

            $j++;
          }

        Item::destroy($option_id);
        $test = $items['liste_id'];
        $list = Liste::where('id', '=', $test )->first();
        $token = $list->token;


        $this->container->flash->addMessage("Success", $nom." a été supprimé");
        return $response->withRedirect("/item/".$token);


    }

    private function validate($p) {
      if (!$this->valid($p['name'], 25)) {
        return "le nom de l'item doit être rempli et faire moins de 25 caractères";
      }
      if (!$this->valid($p['description'], 250)) {
        return "la description doit être remplie et faire moins de 250 caractères";
      }
      return "ok";
    }

 }
unset($_SESSION['errorItem']);
