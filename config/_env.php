<?php
$dotenv = new josegonzalez\Dotenv\Loader(
    dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env'
);
$dotenv->parse();
$dotenv->toEnv();
