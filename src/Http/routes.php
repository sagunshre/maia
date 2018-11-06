<?php

$router->group([
        'middleware' => 'auth',
        'namespace' => 'Views',
    ], function ($router) {
        $router->get('volumes/{id}/maia', [
            'as' => 'volumes-maia',
            'uses' => 'MaiaController@index',
        ]);
});

$router->group([
        'middleware' => 'auth:web,api',
        'namespace' => 'Api',
        'prefix' => 'api/v1',
    ], function ($router) {
        $router->resource('volumes/{id}/maia', 'MaiaController', [
            'only' => ['store'],
            'parameters' => ['volumes' => 'id'],
        ]);
});
