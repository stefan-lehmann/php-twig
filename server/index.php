<?php

$payload = json_decode(file_get_contents("php://input"), true);

$rootPath = $payload['root'];

if (substr($rootPath, -1) !== '/') {
  $rootPath .= '/';
}

$template = (isset($payload['template'])) ? $payload['template'] : file_get_contents($rootPath.$payload['filename']);
$data = (isset($payload['data']) && is_array($payload['data'])) ? $payload['data'] : array();
$paths = (isset($payload['paths']) && is_array($payload['paths'])) ? $payload['paths'] : array();
$separator = (isset($payload['separator']) && is_string($payload['separator'])) ? $payload['separator'] : '-';

require_once '../vendor/autoload.php';
require_once '../AtomaticTwigLoader.php';

$loader = new Twig_Loader_Chain([
    new Twig_Loader_Array([$payload['filename'] => $template]),
    new AtomaticTwigLoader($paths, $rootPath, $separator)
  ]
);

$twig = new Twig_Environment($loader);

print $twig->render($payload['filename'], $data);