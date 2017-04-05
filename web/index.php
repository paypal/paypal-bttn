<?php
    require_once("../vendor/autoload.php");
    require_once(dirname(__FILE__) . "/lib/braintree.php");
    require_once(dirname(__FILE__) . "/lib/db.php");
    require_once(dirname(__FILE__) . "/lib/config.php");
    include_once(dirname(__FILE__) . "/lib/cart.php");
    include_once(dirname(__FILE__) . "/lib/bttn.php");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <meta name="author" content="">

    <title>truc - Buy Stuff Online</title>

    <!-- Bootstrap Core CSS -->
    <link rel="stylesheet" href="css/bootstrap.min.css" type="text/css">


    <!-- Plugin CSS -->
    <link rel="stylesheet" href="css/animate.min.css" type="text/css">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/creative.css" type="text/css">

    <!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
        <script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
        <script src="https://oss.maxcdn.com/libs/respond.js/1.4.2/respond.min.js"></script>
    <![endif]-->
    <style type="text/css">
    #payment-form{
        align-content: center;
        text-align: center;
    }

    .white_form
    {
        background: white;
        margin-bottom:10px;
    }

    .error_msg{
        margin-top:10px;
        margin-bottom:10px;
    }

    .bg-admin {
      color: #fff;
      background-color: #646667;
    }

    .vcenter {
    display: inline-block;
    vertical-align: middle;
    float: none;
    }
    </style>


</head>

<body id="page-top">


    <?php
    if( isset($_GET["merchant"]) )
    {
      $loader = new Twig_Loader_Filesystem( dirname(__FILE__).'/templates');
      $twig = new Twig_Environment($loader);

      $template_merchant = $twig->loadTemplate('index_merchant.html');

      echo $template_merchant->render(array());
    }
    else {
      $loader = new Twig_Loader_Filesystem( dirname(__FILE__).'/templates');
      $twig = new Twig_Environment($loader);

      $template_consumer = $twig->loadTemplate('index_consumer.html');


      $cart = getRandomCart();
      $total = getCartTotal($cart);

      echo $template_consumer->render(array(
        "total" => $total,
        "cart" => $cart
      ));
    }
    ?>

     <section id="contact">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 col-lg-offset-2 text-center">
                    <h2 class="section-heading">Let's Get In Touch!</h2>
                    <hr class="primary"/>
                    <p>Interested in using a PayPal bttn for your company?</p>
                    <p><a href="https://docs.google.com/forms/d/e/1FAIpQLSduzTRXBxVta8gstoG37mIWVPyL7sXdbDso3jxPpJZrYOFUJQ/viewform" target="_blank"><button type="button" class="btn btn-primary btn-lg" onclick="ga('send', 'event', 'google_form', 'link', 'click');">Please fill out this form</button></a></p>
                </div>

            </div>
        </div>
    </section>

    <!-- jQuery -->
    <script src="js/jquery.js"></script>

    <!-- Bootstrap Core JavaScript -->
    <script src="js/bootstrap.min.js"></script>

    <!-- Plugin JavaScript -->
    <script src="js/jquery.easing.min.js"></script>
    <script src="js/jquery.fittext.js"></script>
    <script src="js/wow.min.js"></script>
    <script src="js/braintree-2.23.0.min.js"></script>

    <!-- Custom Theme JavaScript -->
    <script src="js/creative.js"></script>



    <?php
    if( !isset($_GET["merchant"]) )
    {
      $template = $twig->loadTemplate('consumer_footer.html');
      echo $template->render(array(
        "clientToken" => getClientToken()
      ));
    }
    ?>

     <script type="text/javascript">

        function populateTestData(type)
        {
          if( type == "cc1")
          {
            $("input[name=cc_num]").val("378282246310005");
            $("input[name=cc_exp]").val("10/17");
            $("input[name=cc_cvv]").val("1234");
            $("input[name=cc_postal]").val("85339");
          }
        }

        function handleBraintreeTokenization () {
          var cc = $("input[name=cc_num]").val();
          var exp = $("input[name=cc_exp]").val();
          var cvv = $("input[name=cc_cvv]").val();
          var postalCode = $("input[name=cc_postal]").val();

          // If a CC and EXP is entered, we'll ignore the PayPal nonce and overwrite it with this CC nonce
          if( cc.length > 0 && exp.length > 0 )
          {
            client.tokenizeCard({
              number: cc,
              expirationDate: exp,
              cvv: cvv,
              billingAddress: {
                postalCode: postalCode
              }
            },function (err, nonce) {
                $("#payment-method-nonce").val(nonce);
                $("#charge-type").val("credit");
            });
          }
        }

        function releaseButton(assocationId)
        {
            $.post('./functions.php', {function: "RELEASE_BY_ASSOCID", associd: assocationId}, function(response) {
              console.log(response);
              location.reload();
            });
        }

        function showBttnData()
        {
            $("#bttn_data_wait").show();
            $.post('./functions.php', {function: "SHOW_BTTN_DATA"}, function(response) {
              $("#bttn_data").html(response);
              $("#bttn_data_wait").hide();
            });
        }

        function showBraintreeData()
        {
            $("#braintree_data_wait").show();
            $.post('./functions.php', {function: "SHOW_BRAINTREE_DATA"}, function(response) {
              $("#braintree_data").html(response);
              $("#braintree_data_wait").hide();
            });
        }

        function handleFormSubmission(formName)
        {
            var form = $(formName);
            var serializedData = form.serialize();

            $(formName + "_wait").show();

            $.post('./functions.php', serializedData, function(response) {
                var button = $("button", form);

                if( response == "success" )
                {
                    $(formName + "_error").text("");

                    $(button).removeClass("btn-default");
                    $(button).addClass("btn-success");

                    setTimeout(function(){
                        $(button).removeClass("btn-success");
                        $(button).addClass("btn-default");
                        $(formName + "_error").text("");
                    },2000);
                }
                else
                {
                    console.log(response);
                    $(formName + "_error").text(response);

                    $(button).removeClass("btn-default");
                    $(button).addClass("btn-danger");

                    setTimeout(function(){
                        $(button).removeClass("btn-danger");
                        $(button).addClass("btn-default");
                        $(formName + "_error").text("");
                    },2000);

                }
                $(formName + "_wait").hide();

            });
        }

        $("#release_button").click(function(event){    handleFormSubmission("#release_form") });
        $("#registration_button").click(function(event){    handleFormSubmission("#registration_form") });
        $("#request_button").click(function(event){         handleFormSubmission("#request_form") });
        $("button[name=cc1_test]").click(function(event){
          populateTestData("cc1");
          handleBraintreeTokenization();
        });

        $("#pay_button").click(function(event){
          handleFormSubmission("#checkout");
        });


    </script>

</body>

</html>
