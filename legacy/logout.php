<?php
session_start();
// unset($_SESSION["notice"]);
// unset($_SESSION["loggedin"]);
// unset($_SESSION["user"]);
session_unset();
session_destroy();

header('Location: http://localhost/booking/');