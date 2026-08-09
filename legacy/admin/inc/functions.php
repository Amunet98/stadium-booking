<?php

session_start();

require_once 'connect.php';

/* 
require functional pages
*/
require_once 'functions/matches.php';
require_once 'functions/stadium.php';
require_once 'functions/teams.php';
require_once 'functions/booking.php';



// admin match page
function matchpage(){

    if(isset($_GET['action'])){
        $action = $_GET['action'] ? $_GET['action'] : 'list';
    }
    else{
        $action = 'list';
    }
    

    switch($action){
        case 'list':
            listMatches();
        break;

        case 'add':
            addMatches();
        break;
    }
}

// admin stadium page
function stadiumpage(){

    if(isset($_GET['action'])){
        $action = $_GET['action'] ? $_GET['action'] : 'list';
    }
    else{
        $action = 'list';
    }
    

    switch($action){
        case 'list':
            listStadium();
        break;

        case 'add':
            addStadium();
        break;
    }
    
}

// admin teams page
function teamspage(){

    if(isset($_GET['action'])){
        $action = $_GET['action'] ? $_GET['action'] : 'list';
    }
    else{
        $action = 'list';
    }
    

    switch($action){
        case 'list':
            listTeams();
        break;

        case 'add':
            addTeams();
        break;
    }
    
}

//admin logout page

function logout(){
    session_unset();
    session_destroy();

    header('Location: http://localhost/booking/');
}

//admin booking page
 function matchbooking(){
   
    if(isset($_GET['action'])){
        $action = $_GET['action'] ? $_GET['action'] : 'list';
    }
    else{
        $action = 'list';
    }
    

    switch($action){
        case 'list':
            listBookings();
        break;

        case 'add':
            addBookings();
        break;
    }


 }