<?php
$filename = 'hider.json';
if (!file_exists($filename)){
    die("No one hiding.");
}
echo(file_get_contents($filename));
?>
