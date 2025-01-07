<?php

namespace SenseiTarzan\RabbitMQ\Events;

use PhpAmqpLib\Message\AMQPMessage;
use pocketmine\event\Event;

class MessageAMPQEvent extends Event
{

    public function __construct(
        private readonly string $routingKey,
        private readonly string $exchange,
        private readonly string $body,
        private readonly int $bodySize
    )
    {
    }

    /**
     * @return string
     */
    public function getRoutingKey(): string
    {
        return $this->routingKey;
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
    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * @return int
     */
    public function getBodySize(): int
    {
        return $this->bodySize;
    }

}