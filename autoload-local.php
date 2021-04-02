<?php
require_once "../plibv4-autoload/src/Autoload.php";
$loader = new Autoload("../");
$loader->noException(true);
$loader->addIgnoreDir("vendor");
$loader->register();