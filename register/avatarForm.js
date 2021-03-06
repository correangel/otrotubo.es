// JS para el tratamiento del avatar en el registros

// Declaración global para tiempo de espera de ocultación de errores
var alertHideTOut;
// Declaración global para petición asíncrona
var ajaxAvatar;
// Declaración globar para saber si se está usando Gravatar
var usingGravatar = false;

$(document).ready(function(){
  // Cada vez que se selecciona un archivo en el formulario
  $("#avatar").change(function(){
    // Se ocultan el alert y la imagen si se estaban mostrando
    $(".tmpAvatar").hide();
    clearTimeout(alertHideTOut);
    $("#alertIMG").slideUp();
    $("#alertIMG").removeClass("alert-danger");
    // Se muestra el nombre de la imagen o que no hay nada seleccionado
    $("#tmpFileName").val(($(this).val() !== "") ?
      $(this).val().replace(/\\/g, '/').replace(/.*\//, '')
      : "Ningún archivo seleccionado");
    var formData = new FormData();
    // Archivo del input "avatar"
    $(".tmpAvatar").hide();
    $(".tmpAvatar").attr("src", "");
    // Si se ha quitado el archivo subido (se ha vaciado el input),
    // se intenta usar Gravatar
    if(!$("#avatar").get(0).files.length)
      $("#email").change();
    // Si hay archivo seleccionado
    if($("#avatar").get(0).files.length){
      // Si es de más de 2 MB
      if ($("#avatar")[0].files[0].size > 2097152){
        formData.append("tooLarge", true);
        formData.append("imageSize", $("#avatar")[0].files[0].size);
      // Si no es una imagen
      } else if($("#avatar")[0].files[0].type.indexOf("image") == -1)
        formData.append("notImage", true);
      // Si es una imagen de 2MB o menos (es válida)
      else{
        formData.append("file0", $("#avatar")[0].files[0]);
        // Se coloca el botón de Gravatar si hay correo escrito 
        $("#email").change();
      }
      // Mostrar animación de carga
      $(".imgProcessing").slideDown();
      if(typeof(ajaxAvatar) != "undefined")
        ajaxAvatar.abort();
      ajaxAvatar = $.ajax({
        url: "/register/uploadAvatar.php",
        type: "post",
        dataType: "json",
        data: formData,
        cache: false,
        contentType: false,
        processData: false,
        success: function(json){
          // Si ha pasado los tests en el PHP, se muestra la imagen
          if(json.checkSuccess){
            $(".tmpAvatar").attr("src", json.tmpImgPath);
            $(".tmpAvatar").show();
            usingGravatar = false;
          // Sino, se muestra el mensaje de error como alert de bootstrap
          } else {
            $("#avatar").val("");
            $("#tmpFileName").val("Ningún archivo seleccionado");
            $("#alertIMG").html("<strong>ERROR: </strong>" + json.message);
            $("#alertIMG").removeClass("alert-info");
            $("#alertIMG").addClass("alert-danger");
            $("#alertIMG").slideDown();
            alertHideTOut = setTimeout(function(){
              $("#alertIMG").slideUp();
              $("#alertIMG").removeClass("alert-danger");
            }, 5000);
          }
        },
        // Si ha habido algún error en la petición, se indica error desconocido
        error: function(){
          $("#avatar").val("");
          usingGravatar = true;
          $("#tmpFileName").val("Ningún archivo seleccionado");
          $("#alertIMG").html("<strong>Error desconocido</strong>");
          $("#alertIMG").removeClass("alert-info");
          $("#alertIMG").addClass("alert-danger");
          $("#alertIMG").slideDown();
          alertHideTOut = setTimeout(function(){
            $("#alertIMG").slideUp();
            $("#alertIMG").removeClass("alert-danger");
          }, 5000);
        },
        complete: function(){
          $(".imgProcessing").slideUp();
        }
      });
    }
  })

  // Tratamiento del email para poder usar avatar de Gravatar
  // Cada vez que cambia el correo en el input
  $("#email").change(function(){
    var email = $("#email").val();
    var regex = /^([a-zA-Z0-9_.+-])+\@(([a-zA-Z0-9-])+\.)+([a-zA-Z0-9]{2,4})+$/;
    // Si se ha subido imagen, y el correo es válido,
    // se muestra el botón de usar gravatar
    if($("#avatar").val() && regex.test(email)){
      $("#useGravatar").slideDown();
    }
    // Si no se ha subido pero el correo es válido, se usa gravatar directamente
    else if (!($("#avatar").val()) && regex.test(email))
      useGravatar();
    // Si se ha subido pero el correo no es válido, se oculta el botón
    else if ($("#avatar").val() && (!regex.test(email)))
      $("#useGravatar").slideUp();
    // Si no se ha subido y el correo no es válido, se oculta todo
    else {
      $("#useGravatar").slideUp();
      $("#alertIMG").slideUp();
      $("#alertIMG").removeClass("alert-danger");
      $("#avatar").val("");
      $(".tmpAvatar").hide();
      clearTimeout(alertHideTOut);
      usingGravatar = false;
    }
  });

  // Cuando se pulsa en el botón, se usa Gravatar
  $("#useGravatar").click(function(e){
    e.preventDefault();
    $("#avatar").val("");
    $("#avatar").change();
    useGravatar();
  });

  // Al realizar el submit, si se está usando un avatar subido,
  // se pasa la codificación base64 a un input hidden.
  $("form#registerForm").submit(function(){
    if($("form#registerForm #usingGravatar").length > 0)
      $("form#registerForm #usingGravatar").val(usingGravatar);
    else{
      $(this).append(
        "<input type='hidden' name='usingGravatar'"
        + "id='usingGravatar' value='"+usingGravatar+"'>"
      );
    }
    if(!usingGravatar){
      var srcBase64 = $(".tmpAvatar").attr("src").slice(
        $(".tmpAvatar").attr("src").indexOf("base64,") + "base64,".length
      );
      if($("form#registerForm #avatarBase64").length > 0)
        $("form#registerForm #avatarBase64").val(srcBase64);
      else{
        $(this).append(
          "<input type='hidden' name='avatarBase64'"
          +"id='avatarBase64' value='"+srcBase64+"'>"
        );
      }
    } else if($("form#registerForm #avatarBase64").length > 0)
      $("form#registerForm #avatarBase64").remove();
  });

  // Función para usar Gravatar
  function useGravatar(){
    if(typeof(ajaxAvatar) != "undefined")
      ajaxAvatar.abort();
    $("#useGravatar").slideUp();
    var email = $("#email").val();
    var regex = /^([a-zA-Z0-9_.+-])+\@(([a-zA-Z0-9-])+\.)+([a-zA-Z0-9]{2,4})+$/;
    if(regex.test(email)){
      // Se ocultan el alert y la imagen si se estaban mostrando otros
        $(".tmpAvatar").hide();
        if($("#alertIMG").html().indexOf("Gravatar") === -1){
          clearTimeout(alertHideTOut);
          $("#alertIMG").slideUp();
          $("#alertIMG").removeClass("alert-danger");
        }
      // Se calcula el md5 del correo con el plugin jQuery MD5
      emailMD5 = $.md5(email.trim().toLowerCase());
      $(".tmpAvatar").attr(
        "src", "https://gravatar.com/avatar/"+emailMD5+"?d=retro&s=256"
      );
      $(".tmpAvatar").show();
      usingGravatar = true;
      // Se muestra alert como que la imagen está sacada de Gravatar
      $("#alertIMG").html("Powered by " +
          "<strong><a href='\/\/gravatar.com'>Gravatar</a></strong>");
      $("#alertIMG").removeClass("alert-danger");
      $("#alertIMG").addClass("alert-info");
      $("#alertIMG").slideDown();
    }
  };
});