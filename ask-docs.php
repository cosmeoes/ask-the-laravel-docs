<?php
use CosmeDev\AskDocs\AskDocs;
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/helpers.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

 $askDocs = new AskDocs();
 $askDocs->start();
