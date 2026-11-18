<?php

    use Illuminate\Foundation\Application;
    use Illuminate\Http\Request;

    define('LARAVEL_START', microtime(true));

    // Determine se a aplicação está em modo de manutenção...
    if (file_exists($maintenance = __DIR__.'/..
  /medvet_app/storage/framework/maintenance.php')) {
        require $maintenance;
    }

    // Registra o autoloader do Composer...
    require __DIR__.'/../medvet_app/vendor/autoload.php';

    // Inicializa o Laravel e manipula a requisição...
    /** @var Application $app */
    $app = require_once __DIR__.'/../medvet_app/bootstrap/app.php';

    $app->handleRequest(Request::capture());
