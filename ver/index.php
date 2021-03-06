<?php // Incluir librerías y header
require_once($_SERVER['DOCUMENT_ROOT']."/header.php");

$idVideo = $_GET["video"];

if (!isset($idVideo)){ ?>
  <script>window.location.replace("/");</script>
  <?php die();
}

// Crear conexión con la BD
require_once($_SERVER['DOCUMENT_ROOT']."/mysqlicon.php");
$con = dbCon();

$resu = $con->query("SELECT v.*, u.avatar, u.email FROM videos v JOIN usuarios u "
  ."ON (v.usuarios_nick = u.nick) WHERE idVideo = '"
  .$con->real_escape_string($idVideo)."'");

if(!$resu)
  videoNotAvailable("Error en la base de datos", true);

if($resu->num_rows == 0)
  videoNotAvailable("No se ha encontrado el vídeo", true);

$infoVideo = $resu->fetch_assoc();

// Si se ha pedido editar vídeo y el usuario logueado es el propietario
if(isset($_POST["editConfirm"])
&& $_SESSION["logedUser"]->getNick() == $infoVideo["usuarios_nick"]){
  if(!isset($_POST["newTitle"]) || trim($_POST["newTitle"]) == ""){
    videoNotAvailable("Debe indicar un título al editar el vídeo");
  }
  $newTitle = $_POST["newTitle"];
  $newDesc = (isset($_POST["newDesc"]) && $_POST["newDesc"] != ""
    ?$_POST["newDesc"]:NULL);
  $newPublic = isset($_POST["newPublic"])?"TRUE":"FALSE";

  $updateResu = $con->query("UPDATE `videos` SET "
    ."titulo = '".$con->real_escape_string($newTitle)."', "
    ."descripcion = '".$con->real_escape_string($newDesc)."', "
    ."public = ".$con->real_escape_string($newPublic)." "
    ."WHERE idVideo = '".$con->real_escape_string($infoVideo["idVideo"])."'");

  // Si la modificación ha salido bien, se recarga para evitar reenvío de formulario
  if($updateResu){
    header("Refresh:0");
  // Si no, se indica error en la base de datos
  } else {
    videoNotAvailable("Error en la base de datos", true);
  }
}

// Si se ha pedido borrar vídeo y el usuario logueado es el propietario
if(isset($_POST["eraseConfirm"])
&& $_SESSION["logedUser"]->getNick() == $infoVideo["usuarios_nick"]){
  // Se indica como borrado en la base de datos
  $eraseResu = $con->query("UPDATE `videos` SET estado = 'deleted' "
    ."WHERE idVideo = '".$infoVideo["idVideo"]."'");
  
  // Si la modificación de la BD ha salido bien, se elimina el vídeo
  if($eraseResu){
    $videosFolder = $_SERVER["DOCUMENT_ROOT"]."/videos";
    // Borrar vídeo 360p, si existe
    if(is_file($videosFolder."/360/".$infoVideo["idVideo"].".mp4"))
      unlink($videosFolder."/360/".$infoVideo["idVideo"].".mp4");
    // Borrar vídeo 720p, si existe
    if(is_file($videosFolder."/720/".$infoVideo["idVideo"].".mp4"))
      unlink($videosFolder."/720/".$infoVideo["idVideo"].".mp4");
    // Borrar archivo miniaturas, si existe
    if(is_file($videosFolder."/thumbs/".$infoVideo["idVideo"].".jpg"))
      unlink($videosFolder."/thumbs/".$infoVideo["idVideo"].".jpg");
    // Indicar actual como borrado para imprimir mensaje de vídeo borrado
    $infoVideo["estado"] = "deleted";
  // Si no, se indica error en BD
  } else {
    videoNotAvailable("Error en la base de datos", true);
  }
}

switch ($infoVideo["estado"]) {
  case "queued":
    videoNotAvailable("El vídeo solicitado está en cola de proceso");
    break;
  case "encoding":
    videoNotAvailable("El vídeo solicitado se está procesando");
    break;
  case "error":
    videoNotAvailable("El vídeo solicitado ha tenido un error en el proceso de conversión", true);
    break;
  case "deleted":
    videoNotAvailable("El vídeo solicitado se ha eliminado", true);
    break;
  case "ready":
  default:
    continue;
    break;
}

getHeader(htmlentities($infoVideo["titulo"]));

// Si se ha publicado un comentario
if(isset($_POST["newComment"]) && (trim($_POST["newComment"]) != "")
&& isset($_SESSION["isLoged"]) && $_SESSION["isLoged"]){
  $postCommentResu = $con->query("INSERT INTO comentarios (`idComentario`, "
  ."`texto`, `fechaComentario`, `usuarios_nick`, `videos_idVideo`) VALUES('"
  ."0', '"
  .$con->real_escape_string(trim($_POST["newComment"]))."', '"
  .$con->real_escape_string(date("Y-m-d H:i:s"))."', '"
  .$con->real_escape_string($_SESSION["logedUser"]->getNick())."', '"
  .$con->real_escape_string($infoVideo["idVideo"])."')");

  // Borrar variable y recargar la página para evitar reenvío de formulario
  unset($_POST["newComment"]); ?>
  <script>
    var url = window.location.href;
    window.location.replace(url);
  </script>
<?php exit();
} ?>

<?php // Avatar del usuario que ha subido el vídeo
if(is_null($infoVideo["avatar"])){
  $emailMD5 = md5($infoVideo["email"]);
  $avatar = "https://gravatar.com/avatar/$emailMD5?d=retro";
} else {
  $blob = $infoVideo["avatar"];
  $JPEG = "\xFF\xD8\xFF";
  $GIF  = "GIF";
  $PNG  = "\x89\x50\x4e\x47\x0d\x0a\x1a\x0a";
  $BMP  = "BM";
  if(strpos($blob, $JPEG) !== false)
    $dataImage = "data:image/jpeg;base64,";
  else if(strpos($blob, $GIF) !== false)
    $dataImage = "data:image/gif;base64,";
  else if(strpos($blob, $PNG) !== false)
    $dataImage = "data:image/png;base64,";
  else if(strpos($blob, $BMP) !== false)
    $dataImage = "data:image/bmp;base64,";

  if (isset($dataImage))
    $avatar = $dataImage.base64_encode($blob);
}

// Contador de likes/dislikes
$likesResu = $con->query("SELECT SUM(gusto = 1) AS likes, "
  ."SUM(gusto = 0) AS dislikes FROM likes WHERE videos_idVideo = '"
  .$con->real_escape_string($infoVideo["idVideo"])."'");

if($likesResu && ($likesRow = $likesResu->fetch_assoc())){
  $nLikes = (is_null($likesRow["likes"]))?"0":$likesRow["likes"];
  $nDislikes = (is_null($likesRow["dislikes"]))?"0":$likesRow["dislikes"];
}

// Cargar like/dislike del usuario logueado
if(isset($_SESSION["isLoged"]) && $_SESSION["isLoged"]){
  $ownLikeResu = $con->query("SELECT gusto FROM likes "
  ."WHERE videos_idVideo = '".$con->real_escape_string($infoVideo["idVideo"])
  ."' AND usuarios_nick = '"
  .$con->real_escape_string($_SESSION["logedUser"]->getNick())."'");

  if($ownLikeResu && $ownLikeResu->num_rows > 0){
    $didVote = true;
    $voted = ($ownLikeResu->fetch_assoc())["gusto"];
  } else {
    $didVote = false;
  }
}

// Entrada de likes/dislikes en la BD
if(isset($_POST["likeThis"]) || isset($_POST["dislikeThis"])){
  if(isset($_POST["likeThis"])){
    $newLike = "TRUE";
  } else if (isset($_POST["dislikeThis"])){
    $newLike = "FALSE";
  }

  // Si ya había votado, se actualiza su gusto
  if($didVote){
    $newLikeResu = $con->query("UPDATE likes SET gusto = $newLike "
    ."WHERE videos_idVideo = '"
    .$con->real_escape_string($infoVideo["idVideo"])."' "
    ."AND usuarios_nick = '"
    .$con->real_escape_string($_SESSION["logedUser"]->getNick())."'");
  // Si no, se inserta una nueva entrada    
  } else {
    $newLikeResu = $con->query("INSERT INTO likes (gusto, videos_idVideo, "
    ."usuarios_nick) VALUES ($newLike, '"
    .$con->real_escape_string($infoVideo["idVideo"])."', '"
    .$con->real_escape_string($_SESSION["logedUser"]->getNick())."')");
  }

  // Recargar la página para evitar reenvío de formulario ?>
  <script>
    var url = window.location.href;
    window.location.replace(url);
  </script>
<?php exit();
}

?>
<script src="/ver/playerCode.js"></script>

<div class="container video-container">
  <div class="row">
    <div class="col-lg-7 col-md-9 col-sm-12 col-xs-12 player-col">
      <div id="playerWrapper" class="normalVideo text-center">
        <video id="player" controls>
          <source label="360p"
            src=<?php echo "\"/videos/360/".$idVideo.".mp4\"" ?>
            type="video/mp4">
          Su navegador no soporta vídeo HTML5.
        </video>
      </div>
      <div class="container-fluid videoPlayerExtra">
        <div class="row">
          <div class="col-sm-6 col-xs-4">
            <?php if($infoVideo["isHD"]){ ?>
              <button id="qSelector" class="btn btn-danger">Cambiar a 720p</button>
            <?php } ?>
            <button id="wSelector" class="btn btn-danger">Modo cine</button>
          </div>
          <?php if(isset($_SESSION["isLoged"]) && $_SESSION["isLoged"]
          && $infoVideo["usuarios_nick"] == $_SESSION["logedUser"]->getNick()){ ?>
            <div class="col-sm-6 col-xs-8 text-right">
              <div class="buttonsRightVideo">
                <button id="editInfo" class="btn btn-warning"
                data-toggle="modal" data-target="#editModal">
                  Editar info del vídeo
                </button>
                <button id="eraseVideo" class="btn btn-danger"
                data-toggle="modal" data-target="#eraseModal">Borrar vídeo</button>
              </div>
            </div>
          <?php } ?>
        </div>
      </div>
    </div>
    <div class="col-lg-5 col-md-3 col-sm-12 col-xs-12 info-col info-narrow">
      <h3>
        <?php echo htmlentities($infoVideo["titulo"]) ?>
        <?php if($infoVideo["public"] == 0){ ?>
          <br>
          <small>Vídeo oculto</small>
        <?php } ?>
      </h3>
      <h5>
        <a class="userLink" href=<?php echo "'/u/".$infoVideo["usuarios_nick"]."'" ?>>
          <img class="userAvatar" src=<?php echo "'$avatar'" ?>>
        </a>
        <div style="display:inline-block; vertical-align:bottom">
          <a class="userLink" href=<?php echo "'/u/".$infoVideo["usuarios_nick"]."'" ?>>
            <?php echo htmlentities($infoVideo["usuarios_nick"]) ?>
          </a>
          <br><br>Subido el 
          <?php echo htmlentities(
            date('d/m/Y - H:i',strtotime($infoVideo["fechaSubida"]))
          )?>
        </div>
      </h5>
      <p class="collapsed text-justify">
        <?php echo htmlentities($infoVideo["descripcion"]) ?>
      </p>
      <button class="readMoreDesc readMoreNarrow btn btn-default btn-sm btn-info btn-block"
      style="display:none">Leer más</button>
    </div>
  </div>
</div>

<div class="video-container-2 container">
  <div class="row">
    <div class="col-xs-12 info-col info-wide" style="display:none">
      <h3>
        <?php echo htmlentities($infoVideo["titulo"]) ?>
        <?php if($infoVideo["public"] == 0){ ?>
          <br>
          <small>Vídeo oculto</small>
        <?php } ?>
      </h3>
      <h5>
        <a class="userLink" href=<?php echo "'/u/".$infoVideo["usuarios_nick"]."'" ?>>
          <img class="userAvatar" src=<?php echo "'$avatar'" ?>>
        </a>
        <div style="display:inline-block; vertical-align:bottom">
          <a class="userLink" href=<?php echo "'/u/".$infoVideo["usuarios_nick"]."'" ?>>
            <?php echo htmlentities($infoVideo["usuarios_nick"]) ?>
          </a>
          <br><br>Subido el 
          <?php echo date('d/m/Y - H:i',strtotime($infoVideo["fechaSubida"])) ?>
        </div>
      </h5>
      <p class="collapsed text-justify">
        <?php echo htmlentities($infoVideo["descripcion"]) ?>
      </p>
      <button class="readMoreDesc readMoreWide btn btn-default btn-sm btn-info btn-block"
      style="display:none">Leer más</button>
    </div>
  </div>
</div>

<hr>
<!-- Modulo de likes/dislikes -->
<div class="container comments-container">
  <div class="row">
    <div class="col-xs-12 text-center">
      <div class="likesModule">
        <span class="likesCount">
          <i class="fa fa-thumbs-up text-success" aria-hidden="true"></i>
          <?php echo $nLikes ?>
        </span>
        <span class="dislikesCount">
          <i class="fa fa-thumbs-down text-danger" aria-hidden="true"></i>
          <?php echo $nDislikes ?>
        </span>
        <?php if(isset($_SESSION["isLoged"]) && $_SESSION["isLoged"]){ ?>
          <div class="voteButtons">
            <form id="likeForm" method="POST"
            action=<?php echo "/ver?video=".$infoVideo["idVideo"] ?>>
              <?php if($didVote && $voted == 1){ ?>
                <button class="btn btn-default btn-success" disabled>Te gusta</button>
              <?php }else{ ?>
                <input type="submit" name="likeThis" id="likeThis"
                class="btn btn-default btn-success" value="Me gusta">
              <?php }
              if($didVote && $voted == 0){ ?>
                <button class="btn btn-default btn-danger" disabled>No te gusta</button>
              <?php }else{ ?>
                <input type="submit" name="dislikeThis" id="dislikeThis"
                class="btn btn-default btn-danger" value="No me gusta">
              <?php } ?>
            </form>
          </div>
        <?php } ?>
      </div>
    </div>
  </div>
</div>

<!-- Módulo de comentarios -->
<div class="container comments-container">
  <div class="row">
    <div class="col-md-10 col-md-push-1 col-xs-12">
      <h3>Comentarios en este vídeo:</h3>
    </div>
  </div>
  <?php if(isset($_SESSION["isLoged"]) && $_SESSION["isLoged"]){ ?>
    <div class="row">
      <div class="col-md-10 col-md-push-1 col-xs-12">
        <form id="commentForm" class="form" method="POST"
        action=<?php echo "/ver?video=".$infoVideo["idVideo"] ?>>
          <div class="form-group">
            <label for="newComment">Escribir comentario: </label>
            <textarea id="newComment" name="newComment" style="resize: vertical"
            class="form-control" required></textarea>
          </div>
          <input type="submit" class="btn btn-default btn-primary pull-right"
          id="postComment" name="postComment" value="Comentar">
        </form>
      </div>
    </div>
  <?php } else { ?>
    <div class="row">
      <div class="col-md-10 col-md-push-1 col-xs-12">
        <div class="text-center">
          Inicia sesión para poder comentar en este vídeo.
        </div>
      </div>
    </div>
  <?php }

  $commentsResu = $con->query(
    "SELECT c.texto, c.fechaComentario, c.usuarios_nick, u.avatar, u.email "
    ."FROM comentarios c JOIN usuarios u ON (c.usuarios_nick = u.nick) "
    ."WHERE videos_idVideo = '".$con->real_escape_string($infoVideo["idVideo"])."' "
    ."ORDER BY fechaComentario DESC"
  );

  if($commentsResu && $commentsResu->num_rows > 0){
    while($comment = $commentsResu->fetch_assoc()){ 
    $cNick = htmlentities($comment["usuarios_nick"]); 
    if(is_null($comment["avatar"])){
      $cEmailMD5 = md5($comment["email"]);
      $cAvatar = "https://gravatar.com/avatar/$cEmailMD5?d=retro&s=56";
    } else {
      $blob = $comment["avatar"];
      $JPEG = "\xFF\xD8\xFF";
      $GIF  = "GIF";
      $PNG  = "\x89\x50\x4e\x47\x0d\x0a\x1a\x0a";
      $BMP  = "BM";
      if(strpos($blob, $JPEG) !== false)
        $dataImage = "data:image/jpeg;base64,";
      else if(strpos($blob, $GIF) !== false)
        $dataImage = "data:image/gif;base64,";
      else if(strpos($blob, $PNG) !== false)
        $dataImage = "data:image/png;base64,";
      else if(strpos($blob, $BMP) !== false)
        $dataImage = "data:image/bmp;base64,";

      if (isset($dataImage))
        $cAvatar = $dataImage.base64_encode($blob);
    } ?>
      <hr>
      <div class="row">
        <div class="col-md-10 col-md-push-1 col-xs-12">
          <div class="container-fluid commentListItem">
            <div class="row">
              <div class="col-sm-4 col-xs-12">
                <h5>
                  <a class="userLink" href=<?php echo "'/u/$cNick'" ?>>
                    <img class="commentAvatar" src=<?php echo "'$cAvatar'" ?>>
                  </a>
                  <div style="display:inline-block; vertical-align:bottom">
                    <a class="userLink" href=<?php echo "'/u/$cNick'" ?>><?php echo $cNick ?></a>
                    <br>
                    Comentó el <br>
                    <?php echo htmlentities(
                      date('d/m/Y - H:i',strtotime($comment["fechaComentario"]))
                    ) ?>
                </h5>
              </div>
              <div class="col-sm-8 col-xs-12">
                <p><?php echo htmlentities($comment["texto"]) ?></p>
              </div>
            </div>
          </div>
        </div>
      </div>
    <?php }
  } else { ?>
    <hr>
      <div class="row">
        <div class="col-md-10 col-md-push-1 col-xs-12">
          <div class="container-fluid text-center">
            Todavía no se han escrito comentarios en este vídeo.
          </div>
        </div>
      </div>
    </div>
  <?php } ?>
</div>

<?php // Modal de edición de datos del vídeo
if(isset($_SESSION["isLoged"]) && $_SESSION["isLoged"]
&& $infoVideo["usuarios_nick"] == $_SESSION["logedUser"]->getNick()){ ?>
  <!-- CSS para switch de vídeo público -->
  <link href="/lib/toggle-switch.css" rel="stylesheet" type="text/css">

  <div id="editModal" class="modal fade" role="dialog">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal">&times;</button>
          <h4 class="modal-title">Editar información del vídeo</h4>
        </div>
        <form id="editVideo" method="POST" class="form">
          <div class="modal-body">
            <div class="form-group">
              <label for="newTitle">Título del vídeo: *</label>
              <input type="text" name="newTitle" id="newTitle" class="form-control"
              value=<?php echo "'".$infoVideo["titulo"]."'" ?> required>
            </div>
            <div class="form-group">
              <div class="container-fluid publicToggle">
                <div class="row">
                  <div class="col-lg-3 col-md-3 col-sm-3 col-xs-12">
                    <label for="newPublic">Publicar vídeo </label>
                  </div>
                  <div class="col-lg-9 col-md-9 col-sm-9 col-xs-12">
                    <label class="switch-light" onclick="">
                      <input type="checkbox" id="newPublic" name="newPublic"
                      <?php echo ($infoVideo["public"]?"checked":"") ?>>
                      <span class="progress active">
                        <span>Oculto</span>
                        <span>Público</span>
                        <a class="progress-bar progress-bar-info"></a>
                      </span>
                    </label>
                  </div>
                </div>
                <p>
                  Si se selecciona "Público", el vídeo subido aparecerá en la lista
                  de últimos vídeos subidos y en búsquedas.
                </p>
              </div>
            </div>
            <div class="form-group">
              <label for="newDesc">Descripción del vídeo: </label>
              <textarea class="form-control" rows="5"
              id="newDesc" name="newDesc" style="resize: vertical"
              placeholder="Descripción del vídeo"><?php echo $infoVideo["descripcion"] ?></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-default btn-danger pull-left"
            data-dismiss="modal">Cancelar</button>
            <input type="submit" class="btn btn-default btn-primary pull-right"
            id="editConfirm" name="editConfirm" value="Aceptar">
          </div>
        </form>
      </div>
    </div>
  </div>
<?php } ?>

<?php // Modal de confirmación de borrar vídeo
if(isset($_SESSION["isLoged"]) && $_SESSION["isLoged"]
&& $infoVideo["usuarios_nick"] == $_SESSION["logedUser"]->getNick()){ ?>
  <div id="eraseModal" class="modal fade" role="dialog">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal">&times;</button>
          <h4 class="modal-title">Borrar vídeo</h4>
        </div>
        <div class="modal-body">
          <div class="alert alert-danger lead">
            ATENCIÓN: Esta acción no se podrá deshacer
          </div>
          <p>¿Está seguro que quiere borrar el vídeo de la plataforma?</p>
        </div>
        <div class="modal-footer">
          <button class="btn btn-default btn-danger pull-left"
          data-dismiss="modal">Cancelar</button>
          <form id="eraseVideo" method="POST">
            <input type="submit" class="btn btn-default btn-primary pull-right"
            id="eraseConfirm" name="eraseConfirm" value="Aceptar">
          </form>
        </div>
      </div>
    </div>
  </div>
<?php } ?>

  </body>
</html>

<?php 
$con->close();

// Si no se puede mostrar el vídeo, se cierra la conexión y se para la ejecución
function videoNotAvailable($message, $severe = false){
  getHeader("Vídeo no disponible");
  $alertClasses = "alert alertVideo alert-".($severe?"danger":"warning");
  ?>
  <div class="text-center alertVideoWrapper">
    <div class=<?php echo "'$alertClasses'" ?> style="display: inline-block">
      <h3><?php echo $message ?></h3>
      <?php if(!$severe){ ?>
        <a href="">Recargar la página</a>
      <?php } ?>
    </div>
  </div>
  <?php
  echo "</body></html>";
  global $con;
  $con->close();
  exit();
}
?>