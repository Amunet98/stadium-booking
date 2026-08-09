<?php
function listStadium(){
    global $conn;
    //get all stadiums
    $sql = 'select * from stadium';
    $result = mysqli_query($conn, $sql);

    if (mysqli_num_rows($result) > 0) {
    // output data of each row
        ?>
            <div class="btn_wrapper">
                <a href="?page=stadium&action=add">
                    <button class="btn btn-primary">
                        Add New
                    </button>
                </a>
            </div>
            
            <table class="table table-striped table-bordered">
                <thead>
                    <tr>
                        <th>name</th>
                        <th>VIP</th>
                        <th>Platinum</th>
                        <th>Gold</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                        while($row = mysqli_fetch_assoc($result)) {
                            ?>
                                <tr>
                                    <td><?php echo $row['sName'] ?></td>
                                    <td><?php echo $row['a'] ?></td>
                                    <td><?php echo $row['b'] ?></td>
                                    <td><?php echo $row['c'] ?></td>
                                    <td><?php echo $row['description'] ?></td>
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
function addStadium(){
    ?>
        <form action="inc/process.php" method="post">
            <div class="form-group">
                <label for="sname">Name</label>
                <input type="text" id="sname" name="sname" class="form-control"/>
            </div>
            <div class="form-group">
                <label for="vipseat">VIP Seat Capacity</label>
                <input type="text" id="vipseat" name="vipseat" class="form-control"/>
            </div>
            <div class="form-group">
                <label for="goldseat">Gold Seat Capacity</label>
                <input type="text" id="goldseat" name="goldseat" class="form-control"/>
            </div>
            <div class="form-group">
                <label for="platinumseat">Platinum Seat Capacity</label>
                <input type="text" id="platinumseat" name="platinumseat" class="form-control"/>
            </div>
            <div class="form-group">
                <label for="description">Description</label>
                <textarea name="sdescription" id="" cols="30" rows="10" class="form-control" id="description" name="description"></textarea>
            </div>
            <input type="submit" name="stadiumSubmit" value="Add Stadium" class="form-control btn btn-primary">
        </form>
    <?php
}
