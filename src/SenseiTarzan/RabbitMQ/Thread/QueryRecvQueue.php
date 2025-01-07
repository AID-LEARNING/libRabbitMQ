<?php

namespace SenseiTarzan\RabbitMQ\Thread;

use PhpAmqpLib\Message\AMQPMessage;
use pmmp\thread\ThreadSafe;
use pmmp\thread\ThreadSafeArray;
use SenseiTarzan\RabbitMQ\Class\MongoError;
use SenseiTarzan\RabbitMQ\Class\Response;
use SenseiTarzan\RabbitMQ\Events\MessageAMPQEvent;

class QueryRecvQueue extends ThreadSafe
{

	private ThreadSafeArray $queue;

	public function __construct(){
		$this->queue = new ThreadSafeArray();
	}

	/**
	 * @param int $queryId
	 * @param Response $result
	 */
	public function publishResult(AMQPMessage $result) : void{
		$this->synchronized(function() use ($result) : void{
			$this->queue[] = igbinary_serialize([
                $result->getExchange(),
                $result->getRoutingKey(),
                $result->getBody(),
                $result->getBodySize()
            ]);
			$this->notify();
		});
	}

	public function fetchResults(&$routingKey,&$exchange,&$body,&$size) : bool{
		$row = $this->queue->shift();
		if(is_string($row)){
            [$routingKey,$exchange,$body,$size] = igbinary_unserialize($row);
			return true;
		}
		return false;
	}

	/**
	 * @return array
	 */
	public function fetchAllResults(): array{
		return $this->synchronized(function(): array{
			$ret = [];
			while($this->fetchResults($routingKey,$exchange,$body,$size)){
				$ret[] = new MessageAMPQEvent($routingKey,$exchange,$body,$size);
			}
			return $ret;
		});
	}
}