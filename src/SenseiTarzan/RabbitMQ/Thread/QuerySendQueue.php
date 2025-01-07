<?php

namespace SenseiTarzan\RabbitMQ\Thread;

use Closure;
use PhpAmqpLib\Message\AMQPMessage;
use pmmp\thread\ThreadSafe;
use pmmp\thread\ThreadSafeArray;
use SenseiTarzan\RabbitMQ\Class\ETypeRequest;
use SenseiTarzan\RabbitMQ\Class\Publish;
use SenseiTarzan\RabbitMQ\Exception\QueueShutdownException;

class QuerySendQueue extends ThreadSafe
{
	/** @var bool */
	private bool $invalidated = false;
	/** @var ThreadSafeArray */
	private ThreadSafeArray $queries;

	public function __construct(){
		$this->queries = new ThreadSafeArray();
	}

    /**
     * @param Publish $publish
     * @return void
     * @throws QueueShutdownException
     */
	public function scheduleQuery(Publish $publish): void {
		if($this->invalidated){
			throw new QueueShutdownException("You cannot schedule a query on an invalidated queue.");
		}
		$this->synchronized(function() use ($publish) : void{
            $this->queries[] = igbinary_serialize($publish);
			$this->notifyOne();
		});
	}

	public function fetchQuery() : string|false|null {
		return $this->synchronized(function(): string|false|null {
            if ($this->queries->count() === 0 && $this->isInvalidated())
                return false;
			return $this->queries->count() === 0  ? null : $this->queries->shift();
		});
	}

	public function invalidate() : void {
		$this->synchronized(function():void{
			$this->invalidated = true;
			$this->notify();
		});
	}

	/**
	 * @return bool
	 */
	public function isInvalidated(): bool {
		return $this->invalidated;
	}

	public function count() : int{
		return $this->queries->count();
	}
}