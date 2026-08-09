<?php
function listTeams(){
    global $conn;
     //get all teams
    $sql = 'select teams.*, stadium.sName from teams inner join stadium on teams.sid = stadium.sid';
    $result = mysqli_query($conn, $sql);
    if (mysqli_num_rows($result) > 0) {
        // output data of each row
            ?>
                <div class="btn_wrapper">
                    <a href="?page=team&action=add">
                        <button class="btn btn-primary">
                            Add New
                        </button>
                    </a>
                </div>
                
                <table class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th>TEAM_NAME</th>
                            <th>MANAGER</th>
                            <th>Stadium</th>
                            <th>DETAILS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                            while($row = mysqli_fetch_assoc($result)) {
                                ?>
                                    <tr>
                                        <td><?php echo $row['tname'] ?></td>
                                        <td><?php echo $row['manager'] ?></td>
                                        <td><?php echo $row['sName'] ?></td>
                                        <td><?php echo $row['details'] ?></td>
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
function addTeams(){
    //
    global $conn;
    $sql = 'select * from stadium';
    $result = mysqli_query($conn, $sql);
    //$rows = mysqli_fetch_assoc($result);
    ?>
        <form action="inc/process.php" method="post">
            <div class="form-group">
                <label for="tname">TEAM_NAME</label>
                <input type="text" id="tname" name="tname" class="form-control"/>
            </div>
            <div class="form-group">
                <label for="manager">MANAGER</label>
                <input type="text" id="manager" name="manager" class="form-control"/>
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
                <label for="details">DETAILS</label>
                <input type="text" id="details" name="details" class="form-control"/>
            </div>
            <input type="submit" name="teamSubmit" value="Add Teams" class="form-control btn btn-primary">
        </form>
    <?php
}