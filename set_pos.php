<?php
session_start();
$filename = "hider.json";
if (isset($_POST["x"]) && isset($_POST["y"]) && $_SESSION["is_hiding"]){
    $pos = array('x' => (float) $_POST["x"], "y" => (float) $_POST["y"]);
    file_put_contents($filename, json_encode($pos));
}
else{
    die("Unauthorized.");
}
?>
