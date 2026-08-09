<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/bootstrap/css/bootstrap.min.css">

    <link rel="stylesheet" href="assets/css/style.css">
    <title>Stadium Booking System</title>
</head>
<body>
<?php session_start(); ?>
<?php
    require_once 'inc/connect.php';
?>
<header>
    <div class="umang_navbar_wrapper">
        <nav class="navbar navbar-expand-lg navbar-light bg-light">
            <div class="container-fluid">
                <a class="navbar-brand" href="#">Stadium</a>
                <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarTogglerDemo02" aria-controls="navbarTogglerDemo02" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarTogglerDemo02">
                    <ul class="navbar-nav mr-auto mt-2 mt-lg-0">
                        <li class="nav-item active">
                            <a class="nav-link" href="http://localhost/booking/">Home</a>
                        </li>
                    </ul>
                    <ul class="navbar-nav">
                        <?php
                            if( isset($_SESSION["loggedin"]) ){
                                ?>
                                    <li class="nav-item">
                                        <a class="nav-link" href="logout.php">Logout</a>
                                    </li>
                                    <li class="nav-item">
                                        <a href="http://localhost/booking/myticket.php" class="nav-link">
                                            My Tickets
                                        </a>
                                    </li>
                                <?php
                            }
                            else{
                                ?>
                                    <li class="nav-item">
                                        <a class="nav-link" href="login.php">Login</a>
                                    </li>
                                <?php
                            }
                        ?>
                        
                    </ul>
                </div>
            </div>
        </nav>
    </div>
</header>