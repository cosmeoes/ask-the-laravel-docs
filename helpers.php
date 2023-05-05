<?php

function config($key) {
    $config = require __DIR__ . '/config.php';
    $value = $config;
    foreach (explode('.', $key) as $segment) {
        if (isset($value[$segment])) {
            $value = $value[$segment];
        } else {
            return new \Exception("Key not found in config file: $segment.");
        }
    }

    return $value;
}

function encode(array $floatArray)
{
    $bytes = '';
    for ($i = 0; $i < count($floatArray); $i++) {
        $f32 = pack('f', $floatArray[$i]);
        $bytes .= $f32;
    }

    return $bytes;
}
