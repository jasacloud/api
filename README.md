# api
Configuration for nginx web server.

Add the your project

example :

    <?PHP
    // /location/index.php:
    $api = new Api();
    $api->processApi();
    ?>

Modify your nginx config :

    location = /api {
        rewrite ^(.*)$ "/location/index.php";
    }
    
    location ~ /api/ {
        rewrite /api/([\._0-9a-zA-Z]+)/?([\._0-9a-zA-Z]+)/?[.*]?/?[.*]? /location/index.php?kind=$2%23$1;
    }
