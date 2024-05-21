<?php
use App\Router;

// Prefixes
Router::prefix('naos', 'admin');
Router::prefix('user', 'user');
Router::prefix('async', 'async');

// Redirections
Router::connect('', 'default/index');
?>