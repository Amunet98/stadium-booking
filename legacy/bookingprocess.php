<?php
session_start();
require_once 'inc\connect.php';
function sanitize($data){
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);

    $data = mysqli_real_escape_string($conn, $data);
    return $data;
}


//process code for bookings
if( isset( $_POST['ConfirmBooking'])){
    global $conn;
    $Name = sanitize( $_POST['name'] );
    $Seat= sanitize( $_POST['seat'] );
    $mid = (int) sanitize( $_POST['mid'] );
    
    $matchsql = "SELECT * FROM matches where mid = '$mid'";
    $matchResult = mysqli_query($conn, $matchsql);
    $matches = mysqli_fetch_assoc($matchResult);
   

    switch( $Seat ){
        case 'priceA':
            $paidAmount = $matches['priceA'];
        break;
        case 'priceB':
            $paidAmount = $matches['priceB'];
        break;
        case 'priceC':
            $paidAmount = $matches['priceC'];
        break;
    }

        $uid = $_SESSION["user"];

        //var_dump($uid);
        //die();

    $sql = "INSERT INTO bookings (`uid`,`mid`,`seattype`,`paidAmount`)
         VALUES ( '$uid', '$mid', '$Seat', '$paidAmount')";
         if (mysqli_query($conn, $sql)) {
            header('Location: http://localhost/booking/myticket.php');
        } else {
            echo "Error: " . $sql . "<br>" . mysqli_error($conn);
        }
          
        mysqli_close($conn);
    
    
}
    
?>


