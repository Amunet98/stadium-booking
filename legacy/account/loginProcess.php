<?php
    
require_once './inc/connect.php';
session_start();

echo $_SESSION['user'];

function sanitize($data){
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);

    $data = mysqli_real_escape_string($conn, $data);
    return $data;
}

//process code
if( isset( $_POST['loginSubmit'] ) ){

    global $conn;

    $username = sanitize( $_POST['username'] );
    $password = sanitize( $_POST['password'] );

    //hashing password
    $password = MD5($password);

    $sql = "SELECT * FROM `users` WHERE email='$username' AND `password` = '$password'";

    $result = mysqli_query($conn, $sql);
    if ($result) {
        // $row = mysqli_fetch_assoc($result);
        //     var_dump($row);
        if (mysqli_num_rows($result) === 1) {
            $row = mysqli_fetch_assoc($result);
            // var_dump($row);
            $_SESSION["loggedin"] = 1;
            $_SESSION["user"] = $row['uid'];
            if( $row['rid'] == 1 ){
                header('Location: http://localhost/booking/admin');
            }
            else{
                header('Location: http://localhost/booking/');
            }
            
        }
        else{
            echo 'no user found';
        }
    } else {
        echo "Error: " . $sql . "<br>" . mysqli_error($conn);
    }
      
    mysqli_close($conn);
}