<?php

namespace SenseiTarzan\RabbitMQ\Class;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Wire\AMQPTable;
use pmmp\thread\ThreadSafe;
use pmmp\thread\ThreadSafeArray;

class Exchange extends ThreadSafe
{

    public function __construct(
        private string $exchange,
        private string $type,
        private bool $passive,
        private bool $durable,
        private bool $auto_delete,
        private bool $internal,
        private bool $nowait,
        private readonly ?ThreadSafeArray  $arguments,
        private readonly ?int              $ticket
    )
    {
    }

    /**
     * @param string $exchange
     * @param string $type
     * @param bool $passive
     * @param bool $durable
     * @param bool $auto_delete
     * @param bool $internal
     * @param bool $nowait
     * @param ThreadSafeArray|null $arguments
     * @param int|null $ticket
     * @return Exchange
     */
    public static function create(
        string $exchange,
        string $type,
        bool $passive = false,
        bool $durable = false,
        bool $auto_delete = true,
        bool $internal = false,
        bool $nowait = false,
        ?ThreadSafeArray  $arguments = null,
        ?int              $ticket  = null
    ): Exchange
    {
        return new self($exchange, $type, $passive, $durable, $auto_delete, $internal, $nowait, $arguments, $ticket);
    }

    public function getExchange(): string{
        return $this->exchange;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return bool
     */
    public function isPassive(): bool
    {
        return $this->passive;
    }

    /**
     * @return bool
     */
    public function isDurable(): bool
    {
        return $this->durable;
    }

    /**
     * @return bool
     */
    public function isAutoDelete(): bool
    {
        return $this->auto_delete;
    }

    public function isInternal(): bool{
        return $this->internal;
    }

    /**
     * @return bool
     */
    public function isNowait(): bool
    {
        return $this->nowait;
    }


    public function apply(AMQPChannel $channel): void
    {
        $arguments = array_map(function (string $argument) {
            return unserialize($argument, ['allowed_classes' => true]);
        }, (array) ($this->arguments ?? []));
        $channel->exchange_declare($this->exchange, $this->type, $this->isPassive(), $this->isDurable(),$this->isAutoDelete(), $this->isInternal(), $this->isNowait(), $arguments, $this->ticket);
    }

}