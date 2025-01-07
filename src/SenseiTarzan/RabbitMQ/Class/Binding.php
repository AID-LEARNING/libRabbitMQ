<?php

namespace SenseiTarzan\RabbitMQ\Class;

use PhpAmqpLib\Channel\AMQPChannel;
use pmmp\thread\ThreadSafe;
use pmmp\thread\ThreadSafeArray;

class Binding extends ThreadSafe
{

    /**
     * @param string $queue
     * @param string $exchange
     * @param ThreadSafeArray|null $listRoutingKey
     * @param bool $nowait
     * @param ThreadSafeArray|null $arguments
     * @param int|null $ticket
     */
    public function __construct(
        private readonly string           $queue,
        private readonly string           $exchange,
        private readonly ?ThreadSafeArray $listRoutingKey,
        private readonly bool             $nowait,
        private readonly ?ThreadSafeArray  $arguments,
        private readonly ?int              $ticket
    )
    {
    }

    public static function create(
         string             $queue,
         string             $exchange,
         ?ThreadSafeArray   $listRoutingKey = null,
         bool               $nowait = false,
         ?ThreadSafeArray   $arguments = null,
         ?int               $ticket  = null
    ): Binding
    {
        return new self($queue, $exchange, $listRoutingKey, $nowait, $arguments, $ticket);
    }


    public function apply(AMQPChannel $channel): void
    {
        $arguments = array_map(function (string $argument) {
            return unserialize($argument, ['allowed_classes' => true]);
        }, (array) ($this->arguments ?? []));
        if ($this->listRoutingKey) {
            foreach ($this->listRoutingKey as $routingKey) {
                $channel->queue_bind($this->queue, $this->exchange, $routingKey, $this->nowait, $arguments, $this->ticket);
            }
        } else{
            $channel->queue_bind($this->queue, $this->exchange, '', $this->nowait, $arguments, $this->ticket);
        }
    }

}