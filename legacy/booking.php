<?php
$macthno = (int) $_GET['match'];

require_once 'inc/connect.php';

//$sql = "select * from matches where mid = '$macthno'";
$sql = "select t1.tname as team1, t2.tname as team2, t.*, stadium.* from matches t 
inner join teams t1 on t1.tid = t.hometeam 
inner join teams t2 on t2.tid = t.awayteam 
inner join stadium on t.venue = stadium.sid
where mid = '$macthno'";;
$result = mysqli_query($conn, $sql);


function countSeats($sql)
{
    global $conn;
    $result = mysqli_query($conn, $sql);
    $row = mysqli_num_rows($result);
    return $row;
}

?>

<?php include 'header.php'; ?>

<section id="umang_booking_form">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <?php
                while ($row = mysqli_fetch_assoc($result)) {

                    //Remaining Seat Calculations
                    $totalA = $row['a'];
                    $totalB = $row['b'];
                    $totalC = $row['c'];

                    $bookingsqlA = "select bid from `bookings` where `seattype` = 'a'";
                    $bookedSeatsA = countSeats($bookingsqlA);

                    $bookingsqlB = "select bid from `bookings` where `seattype` = 'b'";
                    $bookedSeatsB = countSeats($bookingsqlB);

                    $bookingsqlC = "select bid from `bookings` where `seattype` = 'c'";
                    $bookedSeatsC = countSeats($bookingsqlC);


                ?>
                    <div class="card">
                        <div class="card-header">
                            <h1>Book Seat for match: <strong><?php echo $row['title'] ?></strong></h1>
                        </div>
                        <div class="card-body">
                            <form action="bookingprocess.php" method="post">
                                <div class="form-group">
                                    <label for="name">Name</label>
                                    <input type="text" id="name" name="name" class="form-control">
                                </div>

                                <div class="form-group">
                                    <label for="seat">Select Seat</label>
                                    <select class="form-control" name="seat" id="seat">
                                        <option value="priceA"> VIP Seat | $.<?php echo $row['priceA'] ?></option>
                                        <option value="priceB"> Platinum Seat | $.<?php echo $row['priceB'] ?></option>
                                        <option value="priceC"> Gold Seat | $.<?php echo $row['priceC'] ?></option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <input type="hidden" name="mid" value="<?php echo $macthno; ?>">
                                    <input type="submit" value='Book' name="ConfirmBooking" class="btn btn-primary">
                                </div>
                                <div class="card-text">
                                    <div class="umang_up_match_time">Time:- <strong><?php echo $row['time'] ?></strong></div>
                                    <div class="umang_up_match_match_day">Match Day:- <strong><?php echo $row['title'] ?></strong></div>
                                    <div class="umang_up_match_ptw">Players To Watch:- <strong><?php echo $row['ptw'] ?></strong></div>
                                    <div class="umang_up_match_venue">Venue:- <strong> <?php echo $row['sName'];  ?> </strong></div>
                                    <div class="umang_up_match_priceA">VIP seat Remaining:- <strong><?php echo ($totalA - $bookedSeatsA) ?></strong></div>
                                    <div class="umang_up_match_priceB">Platinuim seat Remaining:- <strong><?php echo ($totalB - $bookedSeatsB) ?></strong></div>
                                    <div class="umang_up_match_priceC">Gold seat Remaining:- <strong><?php echo ($totalC - $bookedSeatsC) ?></strong></div>
                                    <div class="umang_up_match_hometeam">Home Team:- <strong><?php echo $row['team1'] ?></strong></div>
                                    <div class="umang_up_match_awayteam">Away Team:- <strong><?php echo $row['team2'] ?></strong></div>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php
                }
                ?>

            </div>
        </div>
    </div>
</section>

<?php include 'footer.php'; ?>

<?php


//KISS
//Keep It Simple Stupid

?>