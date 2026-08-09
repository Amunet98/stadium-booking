<?php
function listMatches(){
    global $conn;
    $sql = "select t1.tname as team1, t2.tname as team2, t.*, stadium.sName from matches t 
        inner join teams t1 on t1.tid = t.hometeam 
        inner join teams t2 on t2.tid = t.awayteam 
        inner join stadium on t.venue = stadium.sid
    ";
    $result = mysqli_query($conn, $sql);
    if (mysqli_num_rows($result) > 0) {
        // output data of each row
            ?>
                <div class="btn_wrapper">
                    <a href="?page=match&action=add">
                        <button class="btn btn-primary">
                            Add New
                        </button>
                    </a>
                </div>
                
                <table class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th>TITLE</th>
                            <th>DESCRIPTION</th>
                            <th>VENUE</th>
                            <th>TIME</th>
                            <th>PLAYERS TO WATCH</th>
                            <th>HOME TEAM</th>
                            <th>AWAY TEAM</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                            while($row = mysqli_fetch_assoc($result)) {
                                ?>
                                    <tr>
                                        <td><?php echo $row['title'] ?></td>
                                        <td><?php echo $row['description'] ?></td>
                                        <td><?php echo $row['sName'] ?></td>
                                        <td><?php echo $row['time'] ?></td>
                                        <td><?php echo $row['ptw'] ?></td>
                                        <td><?php echo $row['team1'] ?></td>
                                        <td><?php echo $row['team2'] ?></td>
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
function addMatches(){
    global $conn;
    $sql = 'select * from stadium';
    $result = mysqli_query($conn, $sql);

    $teamsql = 'select * from teams';
    $teams = mysqli_query($conn, $teamsql);


    ?>
        <form action="inc/process.php" method="post">
            <div class="form-group">
                <label for="title">Name</label>
                <input type="text" id="title" name="title" class="form-control"/>
            </div>
            <div class="form-group">
                <label for="description">Description</label>
                <textarea name="sdescription" id="" cols="30" rows="10" class="form-control" id="description" name="description"></textarea>
            </div>
            <div class="form-group">
                <label for="stadium"></label>
                <select name="sid" id="stadium" class="form-control">
                    <option selected disabled>Select Stadium</option>
                    <?php
                        while($row = mysqli_fetch_assoc($result)) {
                            ?>
                                <option value="<?php echo $row['sid'] ?>"><?php echo $row['sName'] ?></option>
                            <?php
                        }
                    ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="time">TIME</label>
                <input type="text" id="time" name="time" class="form-control"/>
            </div>
            <div class="form-group">
                <label for="ptw">Players to watch</label>
                <input type="text" id="ptw" name="ptw" class="form-control"/>
            </div>
            <div class="form-group">
                <label for="stadium">Home Team</label>
                <select name="hometeam" id="stadium" class="form-control">
                    <option selected disabled>Select Team</option>
                    <?php
                        while($row = mysqli_fetch_assoc($teams)) {
                            ?>
                                <option value="<?php echo $row['tid'] ?>"><?php echo $row['tname'] ?></option>
                            <?php
                        }
                    ?>
                </select>
            </div>
            <div class="form-group">
                <label for="stadium">Away Team</label>
                <select name="awayteam" id="stadium" class="form-control">
                    <option selected disabled>Select Team</option>
                    <?php
                        $teamsql = 'select * from teams';
                        $teams = mysqli_query($conn, $teamsql);
                        while($row = mysqli_fetch_assoc($teams)) {
                            ?>
                                <option value="<?php echo $row['tid'] ?>"><?php echo $row['tname'] ?></option>
                            <?php
                        }
                    ?>
                </select>
            </div>
            
            <input type="submit" name="submitMatches" value="Add matches" class="form-control btn btn-primary">
        </form>
    <?php
}