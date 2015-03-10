<?php
try {
    $dotenv = new josegonzalez\Dotenv\Loader(
        dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env'
    );
    $dotenv->parse();
    $dotenv->expect(array(
        'AURA_CONFIG_MODE',
        'AURA_BIN_GITHUB_USER',
        'AURA_BIN_GITHUB_TOKEN',
    ));
    $dotenv->toEnv();
} catch (Exception $e) {
    echo $e->getMessage() . PHP_EOL;
    exit(1);
}
