<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;
//php server.php warning "error_test" > logs_from_rabbit.log

$connection = new AMQPStreamConnection('10.10.1.40', 5672, 'kitmap', ',x58-T0c]8qB', 'test');
$channel = $connection->channel();

var_dump($channel->exchange_declare('direct_logs', 'direct', false, false, false));
$queue_name = "test_queue";
$channel->queue_declare("test_queue", false, false, true, false);
var_dump($queue_name);
$severities = array_slice($argv, 1);
if (empty($severities)) {
    file_put_contents('php://stderr', "Usage: $argv[0] [info] [warning] [error]\n");
    exit(1);
}

foreach ($severities as $severity) {
    $channel->queue_bind($queue_name, 'direct_logs', $severity);
}

echo " [*] Waiting for logs. To exit press CTRL+C\n";


$channel->basic_consume($queue_name, '', false, true, false, false, function ($msg) {
    echo ' [x] ', $msg->getRoutingKey(), ':', $msg->getBody(), "\n";
});

try {
    $channel->consume();
} catch (\Throwable $exception) {
    echo $exception->getMessage();
}

$channel->close();
$connection->close();