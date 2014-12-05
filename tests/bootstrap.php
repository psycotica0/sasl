<?php

$autoloaderFile = __DIR__ . '/../vendor/autoload.php';

if (!file_exists($autoloaderFile)) {
    fwrite(
        STDERR,
        'You need to set up the project dependencies using the following commands:' . PHP_EOL .
        'wget http://getcomposer.org/composer.phar' . PHP_EOL .
        'php composer.phar install' . PHP_EOL
    );
    exit(1);
}

require $autoloaderFile;
