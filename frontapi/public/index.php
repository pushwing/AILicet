<?php

declare(strict_types=1);

use App\App;
use Nyholm\Psr7Server\ServerRequestCreator;

require __DIR__ . '/../vendor/autoload.php';

// .env 로드(있으면)
if (is_file(__DIR__ . '/../.env')) {
    Dotenv\Dotenv::createImmutable(__DIR__ . '/..')->safeLoad();
}

$creator = new ServerRequestCreator(
    new Nyholm\Psr7\Factory\Psr17Factory(),
    new Nyholm\Psr7\Factory\Psr17Factory(),
    new Nyholm\Psr7\Factory\Psr17Factory(),
    new Nyholm\Psr7\Factory\Psr17Factory(),
);

$request  = $creator->fromGlobals();
$response = App::create($_ENV + $_SERVER)->handle($request);

// 응답 송출
http_response_code($response->getStatusCode());
foreach ($response->getHeaders() as $name => $values) {
    foreach ($values as $value) {
        header(sprintf('%s: %s', $name, $value), false);
    }
}
echo $response->getBody();
