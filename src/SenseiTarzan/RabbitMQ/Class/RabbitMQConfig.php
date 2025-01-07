<?php

namespace SenseiTarzan\RabbitMQ\Class;

use PhpAmqpLib\Channel\AMQPChannel;
use pmmp\thread\ThreadSafe;
use pmmp\thread\ThreadSafeArray;
use SenseiTarzan\RabbitMQ\Thread\QueryRecvQueue;
use SenseiTarzan\RabbitMQ\Thread\ThreadRabbitMQ;

class RabbitMQConfig extends ThreadSafe
{
    /**
     * @param string $host
     * @param int $port
     * @param string $user
     * @param string $password
     * @param string $vhost
     * @param Queue $queue
     * @param ThreadSafeArray<Consummer>|null $listConsumer
     * @param ThreadSafeArray<Exchange>|null $listExchanges
     * @param ThreadSafeArray<Binding>|null $listBindings
     */
	public function __construct(
        private readonly string           $host,
        private readonly int              $port,
        private readonly string           $user,
        private readonly string           $password,
        private readonly string           $vhost,
        private readonly Queue            $queue,
        private readonly ?ThreadSafeArray $listConsumer = null,
        private readonly ?ThreadSafeArray $listExchanges = null,
        private readonly ?ThreadSafeArray $listBindings = null
	)
	{
	}

    /**
     * @return string
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * @return int
     */
    public function getPort(): int
    {
        return $this->port;
    }

    /**
     * @return string
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    /**
     * @return string
     */
    public function getUser(): string
    {
        return $this->user;
    }

    /**
     * @return string
     */
    public function getVhost(): string
    {
        return $this->vhost;
    }

    public function apply(QueryRecvQueue $recvQueue, AMQPChannel $channel): void
    {
        $this->queue->apply($channel);
        if ($this->listConsumer)
        {
            foreach ($this->listConsumer as $consumer)
            {
                $consumer->apply($recvQueue, $channel);
            }
        }
        if ($this->listExchanges)
        {
            foreach ($this->listExchanges as $exchange) {
                $exchange->apply($channel);
            }
        }

        if ($this->listBindings) {
            foreach ($this->listBindings as $binding) {
                $binding->apply($channel);
            }
        }
    }

}