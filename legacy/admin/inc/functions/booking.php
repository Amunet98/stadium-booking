<?php
function listBookings(){
    global $conn;
    $sql = 'select * from bookings';
    $result = mysqli_query($conn, $sql);

    if (mysqli_num_rows($result) > 0) {
    // output data of each row
        ?>
            <div class="btn_wrapper">
                <a href="?page=bookings&action=add">
                    <button class="btn btn-primary">
                        ADD BOOKING
                    </button>
                </a>
            </div>
            
            <table class="table table-striped table-bordered">
                <thead>
                    <tr>
                        <th>MATCHID</th>
                        <th>SEATTYPE</th>
                        <th>TOTALAMOUNT</th>
                        <th>TIME</th>
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
}

function addBookings(){
    
}
