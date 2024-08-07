<?php
    require_once "vendor/autoload.php";

    use Illuminate\Database\Capsule\Manager as Capsule;
    use Illuminate\Events\Dispatcher;
    use Illuminate\Container\Container;

    $Eloquent = new Capsule;
    $Eloquent->addConnection([
        'driver'    => 'mysql',
        'host'      => 'host',
        'port'      => 3306,
        'database'  => 'database',
        'username'  => 'username',
        'password'  => 'password',
        'charset'   => 'utf8',
        'collation' => 'utf8_unicode_ci',
        'prefix'    => '',
    ], 'tracker');
    $Eloquent->setEventDispatcher(new Dispatcher(new Container));
    $Eloquent->setAsGlobal();
    $Eloquent->bootEloquent();
