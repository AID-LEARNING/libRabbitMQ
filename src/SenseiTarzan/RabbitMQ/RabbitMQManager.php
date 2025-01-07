<?php

namespace SenseiTarzan\RabbitMQ;

use AttachableLogger;
use Closure;
use Error;
use Exception;
use Generator;
use PhpAmqpLib\Message\AMQPMessage;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use pocketmine\snooze\SleeperHandlerEntry;
use pocketmine\utils\Terminal;
use ReflectionClass;
use SenseiTarzan\RabbitMQ\Class\ETypeRequest;
use SenseiTarzan\RabbitMQ\Class\RabbitMQConfig;
use SenseiTarzan\RabbitMQ\Class\MongoError;
use SenseiTarzan\RabbitMQ\Class\Publish;
use SenseiTarzan\RabbitMQ\Class\Response;
use SenseiTarzan\RabbitMQ\Events\MessageAMPQEvent;
use SenseiTarzan\RabbitMQ\Exception\QueueShutdownException;
use SenseiTarzan\RabbitMQ\Thread\QueryRecvQueue;
use SenseiTarzan\RabbitMQ\Thread\QuerySendQueue;
use SenseiTarzan\RabbitMQ\Thread\ThreadRabbitMQ;
use SOFe\AwaitGenerator\Await;
use SplFixedArray;

class RabbitMQManager
{
	private ThreadRabbitMQ $thread;
	private QuerySendQueue $bufferSend;
	private QueryRecvQueue $bufferRecv;

	private readonly AttachableLogger $logger;

	public function __construct(
		PluginBase                   $plugin,
		private string               $vendors,
		private readonly RabbitMQConfig $config
	)
	{
		$this->logger = $plugin->getLogger();
		$this->bufferSend = new QuerySendQueue();
		$this->bufferRecv = new QueryRecvQueue();
        $this->thread = new ThreadRabbitMQ($this->vendors, $this->bufferSend, $this->bufferRecv, $this->config);
		$plugin->getScheduler()->scheduleRepeatingTask(new ClosureTask(function (): void {
            $this->readResults();
        }), 20);
	}

	public function stopRunning(): void
	{
        $this->thread->stopRunning();
	}

	public function close(): void
	{
		$this->stopRunning();
	}


    /**
     * @param Publish $publish
     * @return void
     * @throws QueueShutdownException
     */
	public function addQuery(Publish $publish) : void{
		$this->bufferSend->scheduleQuery($publish);
	}
	public function readResults(): void {
        $resultsLists = $this->bufferRecv->fetchAllResults();
        if (MessageAMPQEvent::hasHandlers()) {
            /** @var MessageAMPQEvent $event */
            foreach ($resultsLists as $event) {
                $event->call();
            }
        }
	}

	public function connCreated() : bool{
		return $this->thread->connCreated();
	}

	public function hasConnError() : bool{
		return $this->thread->hasConnError();
	}

	public function getConnError() : ?string{
		return $this->thread->getConnError();
	}

	public function getLoad() : int{
		return $this->bufferSend->count();
	}
}