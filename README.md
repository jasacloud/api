# api
Configuration for nginx web server :

    location = /api {
        rewrite ^(.*)$ "/classes/index.php";
    }
    
    location ~ /api/ {
        rewrite /api/([\._0-9a-zA-Z]+)/?([\._0-9a-zA-Z]+)/?[.*]?/?[.*]? /classes/index.php?kind=$2%23$1;
    }
