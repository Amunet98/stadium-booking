<?php

    if( isset($_GET['page']) ){
        $page = $_GET['page'] ? $_GET['page'] : 'match';
    }
    else{
        $page = 'match';
    }

    require_once 'inc/functions.php';

?>
<div class="dashboard_wrapper">
    <?php
        switch($page){
            case 'match':
                matchpage();
                break;

            case 'team':
                teamspage();
                break;
                
            case 'stadium':
                stadiumpage();
                break;
            
            case 'bookings':
                matchbooking();
                break;

            case 'logout':
                logout();
                break;
                
            default:
                echo 'No Page';
                break;
        }
    ?>
</div>
