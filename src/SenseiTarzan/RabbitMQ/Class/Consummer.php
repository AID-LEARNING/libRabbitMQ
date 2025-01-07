<?php

namespace SenseiTarzan\RabbitMQ\Class;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use pmmp\thread\ThreadSafe;
use pmmp\thread\ThreadSafeArray;
use SenseiTarzan\RabbitMQ\Events\MessageAMPQEvent;
use SenseiTarzan\RabbitMQ\Thread\QueryRecvQueue;
use SenseiTarzan\RabbitMQ\Thread\ThreadRabbitMQ;

class Consummer extends ThreadSafe
{

    /**
     * @param string $queue
     * @param bool $noLocal
     * @param bool $noAck
     * @param bool $exclusive
     * @param bool $noWait
     * @param \Closure|null $callback
     * @param string $cosummerTag
     * @param bool $ticket
     * @param ThreadSafeArray|null $arguments
     */
    public function __construct(
        private readonly string           $queue,
        private readonly bool             $noLocal,
        private readonly bool             $noAck,
        private readonly bool             $exclusive,
        private readonly bool             $noWait,
        private readonly ?\Closure        $callback,
        private readonly string           $cosummerTag,
        private readonly ?int             $ticket,
        private readonly ?ThreadSafeArray $arguments
    )
    {
    }

    public static function create(
       string           $queue,
       bool             $noLocal = false,
       bool             $noAck = false,
       bool             $exclusive = false,
       bool             $noWait = false,
       ?\Closure        $callback = null,
       ?int             $ticket = null,
       string           $cosummerTag = '',
       ?ThreadSafeArray $arguments = null
    ): Consummer
    {
        return new self($queue, $noLocal, $noAck, $exclusive, $noWait, $callback,  $cosummerTag, $ticket, $arguments);
    }


    public function apply(QueryRecvQueue $recvQueue, AMQPChannel $channel): void
    {
        $arguments = array_map(function (string $argument) {
            return unserialize($argument, ['allowed_classes' => true]);
        }, (array) ($this->arguments ?? []));
        $channel->basic_consume($this->queue, $this->cosummerTag, $this->noLocal, $this->noAck, $this->exclusive, $this->noWait, function (AMQPMessage $message) use ($recvQueue) {
            if ($this->callback) {
                ($this->callback)($recvQueue, $message);
            }
        }, $this->ticket, $arguments);
    }

}