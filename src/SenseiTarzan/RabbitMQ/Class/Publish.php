<?php

namespace SenseiTarzan\RabbitMQ\Class;

use MongoDB\Collection;
use MongoDB\Database;
use PhpAmqpLib\Message\AMQPMessage;
use pmmp\thread\ThreadSafe;
use SenseiTarzan\RabbitMQ\Client\MongoClient;
use Throwable;

readonly class Publish
{
    public function __construct(
        private AMQPMessage $message,
        private string    $exchange,
        private string $routing_key
    )
    {
    }

    static public function create(
        AMQPMessage $message,
        string $exchange = '',
        string $routing_key = '',
    ): Publish
    {
        return new self($message, $exchange, $routing_key);
    }

    /**
     * @return AMQPMessage
     */
    public function getMessage(): AMQPMessage
    {
        return $this->message;
    }

    /**
     * @return string
     */
    public function getExchange(): string
    {
        return $this->exchange;
    }

    /**
     * @return string
     */
    public function getRoutingKey(): string
    {
        return $this->routing_key;
    }
}