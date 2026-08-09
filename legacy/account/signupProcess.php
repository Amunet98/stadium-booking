<?php
    
require_once './inc/connect.php';
session_start();

//echo $_SESSION['user'];
function sanitize($data){
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);

    $data = mysqli_real_escape_string($conn, $data);
    return $data;
}

//process code
if( isset( $_POST['signupSubmit'] ) ){

    global $conn;

    $email = sanitize( $_POST['email'] );
    $phone = sanitize( $_POST['phone'] );
    $country = sanitize( $_POST['country'] );
    $password = sanitize( $_POST['password'] );

    //hashing password
    $password = MD5($password);

    //if email exists
    $emailSql = "SELECT * FROM `users` WHERE email='$email'";
    $result = mysqli_query($conn, $emailSql);
    if ($result) {
        if (mysqli_num_rows($result) === 1) {
            $row = mysqli_fetch_assoc($result);
            $_SESSION["error"] = 'email already exists';
            echo "email already exists";
        }
        else{
            //insert data
            $sql = "INSERT INTO `users` (`email`, `password`, `phone`, `country`, `rid` ) 
            VALUES ( '$email', '$password', '$phone', '$country', 2)";
            $result = mysqli_query($conn, $sql);
            if ($result) {
                $_SESSION['notice'] = 'User Created Successfully';
                header('Location: http://localhost/booking/login.php');
            } else {
                echo "Error: " . $sql . "<br>" . mysqli_error($conn);
            }
        }
    } else {
        echo "Error: " . $sql . "<br>" . mysqli_error($conn);
    }
      
    mysqli_close($conn);
    
}