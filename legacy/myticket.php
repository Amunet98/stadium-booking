<?php

//$macthno = (int) $_GET['match'];

require_once 'inc/connect.php';
session_start();

$uid = $_SESSION["user"] ;

$sql= "select * from bookings where uid = $uid";
$result = mysqli_query($conn, $sql);
if(mysqli_num_rows($result) > 0 ){
    ?>
        <table class="table table-striped table-bordered">
            <thead>
                <tr>
                    <th>NAME</th>
                    <th>SEAT</th>
                    <th>PAIDAMOUNT</th>
                    <th>TIMESTAMP</th>
                </tr>
            </thead>
            <tbody>
                <?php
                    while($row = mysqli_fetch_assoc($result)) {
                        ?>
                            <tr>
                                <td><?php echo $row['mid'] ?></td>
                                <td><?php echo $row['seattype'] ?></td>
                                <td><?php echo $row['paidAmount'] ?></td>
                                <td><?php echo $row['timestamp'] ?></td>
                            </tr>
                        
                        <?php
                    }
                ?>
            </tbody>
        </table>
   <?php
}
else {
    echo "0 results";
}

mysqli_close($conn);

