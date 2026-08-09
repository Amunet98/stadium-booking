<?php
    if( isset($_POST['loginSubmit']) ){
        //login process
        include 'account/loginProcess.php';
    }
    else{
        include 'account/loginForm.php';
    }
?>
