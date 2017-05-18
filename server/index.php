<?php

$payload = json_decode(file_get_contents("php://input"), true);

$rootPath = $payload['root'];

if (substr($rootPath, -1) !== '/') {
  $rootPath .= '/';
}


$template = (isset($payload['template'])) ? $payload['template'] : file_get_contents($rootPath.$payload['filename']);

$data = (isset($payload['data'])) ? $payload['data'] : array();

require_once '../vendor/autoload.php';
require_once './atomatic.php';

$loader = new Twig_Loader_Chain([
    new Twig_Loader_Array([$payload['filename'] => $template]),
    new Twig_Loader_Atomatic($rootPath)
  ]
);

$twig = new Twig_Environment($loader);

print $twig->render($payload['filename'], $data);