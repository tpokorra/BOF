<?php
use \Firebase\JWT\JWT;

// the authentication
$app->add(new \Slim\Middleware\JwtAuthentication([
    "secure" => false, // we know we are using https behind a proxy
    "cookie" => "authtoken",
    "path" => [ "/admin", "/vote", "/nominate"],
    #"passthrough" => ["/home", "/login", "/authenticate"],
    "secret" => $settings['settings']['secrettoken'],
    "error" => function ($request, $response, $arguments) {
        $data["status"] = "error";
        $data["message"] = $arguments["message"];
        return $response
            ->withHeader("Content-Type", "application/json")
            ->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    }
]));


$app->add(function($request, $response, $next) {
    global $settings;
    global $container;

    $encodedcookie = $request->getCookieParams()['authtoken'];
    if($encodedcookie === NULL)
        return $next($request, $response);

    $cookie = (array)JWT::decode($encodedcookie, $settings['settings']['secrettoken'], array('HS256'));

    $container['view']['userid'] = $cookie['userid'];
    $container['view']['is_admin'] = $cookie['is_admin'];
    $request = $request->withAttribute('userid', $cookie['userid']);
    $request = $request->withAttribute('is_admin', $cookie['is_admin']);

    return $next($request, $response);
});

?>
