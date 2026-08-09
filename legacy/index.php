<?php include 'header.php'; ?>

<!-- banner -->
<section id="banner">
    <div class="container">
        <div class="row">
            <div class="baner-info">
			    <h3> <span>B</span>ook <span>O</span>nline  <span>T</span>ickets </h3>
				<h4>In space no one can hear you scream.</h4>  
			</div>
        </div>
    </div>
</section>

<!-- upcomming Games -->

<section id="upcomming_games">
    <div class="container">
        <div class="row">
            <?php
                $sql = "select t1.tname as team1, t2.tname as team2, t.*, stadium.sName from matches t 
                    inner join teams t1 on t1.tid = t.hometeam 
                    inner join teams t2 on t2.tid = t.awayteam 
                    inner join stadium on t.venue = stadium.sid
                ";
                $result = mysqli_query($conn, $sql);
                if (mysqli_num_rows($result) > 0) {
                    while($row = mysqli_fetch_assoc($result)) {
                        ?>
                            <div class="col-sm-4">
                                <div class="card">
                                    <img src="assets/img/arsenalvsmancity.png" class="card-img-top" alt="...">
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo $row['title'] ?></h5>
                                        <div class="card-text">
                                            <div class="umang_up_match_venue">Venue:- <strong><?php echo $row['sName'] ?></strong></div>
                                            <div class="umang_up_match_time">Time:- <strong><?php echo $row['time'] ?></strong></div>
                                            <div class="umang_up_match_match_day">Match Day:- <strong><?php echo $row['title'] ?></strong></div>
                                            <div class="umang_up_match_ptw">Players To Watch:- <strong><?php echo $row['ptw'] ?></strong></div>
                                        </div>
                                        <a href="booking.php?match=<?php echo $row['mid'] ?>"><button class="btn btn-primary btn_up_details">Book Ticket</button></a>
                                    </div>
                                </div>
                            </div>
                        <?php
                    }
                }
            ?>
            

        </div>
    </div>
</section>

<?php include 'footer.php'; ?>