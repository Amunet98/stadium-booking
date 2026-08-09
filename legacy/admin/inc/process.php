<?php

require_once 'connect.php';

function sanitize($data){
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);

    $data = mysqli_real_escape_string($conn, $data);
    return $data;
}

//process code for stadium
if( isset( $_POST['stadiumSubmit'] ) ){

    global $conn;

    $sname = sanitize( $_POST['sname'] );
    $vipseat = sanitize( $_POST['vipseat'] );
    $goldseat = sanitize( $_POST['goldseat'] );
    $platinumseat = sanitize( $_POST['platinumseat'] );
    $sdescription = sanitize( $_POST['sdescription'] );

    $sql = "INSERT INTO stadium (`sName`, `a`, `b`, `c`, `description`)
        VALUES ( '$sname', '$vipseat', '$goldseat', '$platinumseat', '$sdescription' )";
    
    if (mysqli_query($conn, $sql)) {
        header('Location: http://localhost/booking/admin?page=stadium');
    } else {
        echo "Error: " . $sql . "<br>" . mysqli_error($conn);
    }
      
    mysqli_close($conn);
}

//process code for matches
if( isset( $_POST['submitMatches'])){
    global $conn;
    $title = sanitize( $_POST['title'] );
    $description = sanitize( $_POST['sdescription'] );
    $stadium = sanitize( $_POST['sid'] );
    $venue = sanitize( $_POST['venue'] );
    $time = sanitize( $_POST['time'] );
    $ptw = sanitize( $_POST['ptw'] );
    $hometeam = sanitize( $_POST['hometeam'] );
    $awayteam = sanitize( $_POST['awayteam'] );

    $sql = "INSERT INTO matches (`title`, `description`, `venue`, `time`, `ptw`,`hometeam`,`awayteam`)
        VALUES ( '$title', '$description','$stadium',  '$time', '$ptw','$hometeam','$awayteam' )";
    
    if (mysqli_query($conn, $sql)) {
        header('Location: http://localhost/booking/admin/?page=match');
    } else {
        echo "Error: " . $sql . "<br>" . mysqli_error($conn);
    }
      
    mysqli_close($conn);

}

// process code for teams
if( isset( $_POST['teamSubmit'])){
    global $conn;
    $tname = sanitize( $_POST['tname'] );
    $manager= sanitize( $_POST['manager'] );
    $sid= sanitize ($_POST['sid']);
    $details = sanitize( $_POST['details'] );

    $sql = "INSERT INTO teams (`tname`, `manager`,`sid`,`details`)
        VALUES ( '$tname', '$manager','$sid', '$details' )";
    
    if (mysqli_query($conn, $sql)) {
        header('Location: http://localhost/booking/admin/?page=team');
    } else {
        echo "Error: " . $sql . "<br>" . mysqli_error($conn);
    }
      
    mysqli_close($conn);

}

