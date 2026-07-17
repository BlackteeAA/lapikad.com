<?php
echo "PHP version: " . phpversion() . "<br>";
$f = fn($x) => $x + 1;
echo "arrow fn result: " . $f(41);
