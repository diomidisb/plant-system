<?php

/** Read server-owned configuration, never request-supplied paths. */
function ps_private_config(string $name): array
{
    if (!in_array($name, ['database','auth','email'], true)) {
        throw new InvalidArgumentException('Unknown configuration.');
    }
    $directory = getenv('PS_WEB_CONFIG_DIR') ?: '/etc/plant-system/web-config';
    $config = require rtrim($directory, '/').'/'.$name.'.php';
    if (!is_array($config)) {
        throw new RuntimeException('Invalid server configuration.');
    }
    return $config;
}
