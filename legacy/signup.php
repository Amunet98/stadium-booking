<?php
    if( isset($_POST['signupSubmit']) ){
        //signup process
        include 'account/signupProcess.php';
    }
    else{
        include 'account/signupForm.php';
    }
?>