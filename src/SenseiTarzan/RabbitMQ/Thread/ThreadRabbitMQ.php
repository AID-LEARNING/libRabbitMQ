<?php

namespace SenseiTarzan\RabbitMQ\Thread;

use Composer\Autoload\ClassLoader;
use Exception;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use pmmp\thread\Thread as NativeThread;
use pmmp\thread\ThreadSafeArray;
use pocketmine\snooze\SleeperHandlerEntry;
use pocketmine\thread\Thread;
use SenseiTarzan\RabbitMQ\Class\ETypeRequest;
use SenseiTarzan\RabbitMQ\Class\RabbitMQConfig;
use SenseiTarzan\RabbitMQ\Class\MongoError;
use SenseiTarzan\RabbitMQ\Class\Publish;
use SenseiTarzan\RabbitMQ\Client\MongoClient;
use pocketmine\Server;
use SenseiTarzan\RabbitMQ\libRabbitMQ;
use Throwable;

class ThreadRabbitMQ extends Thread
{
	private const RABBIT_MQ_TPS = 20;
	private const RABBIT_MQ_PER_TICK = 1 / self::RABBIT_MQ_TPS;

	private bool $busy = false;
	protected bool $connCreated = false;
	protected ?string $connError = null;

	public function __construct(
		private readonly string              $vendors,
		private readonly QuerySendQueue      $bufferSend,
		private readonly QueryRecvQueue      $bufferRecv,
		private readonly RabbitMQConfig $config
	)
	{        if(!libRabbitMQ::isPackaged()){
        /** @noinspection PhpUndefinedMethodInspection */
        /** @noinspection NullPointerExceptionInspection */
        /** @var ClassLoader $cl */
        $cl = Server::getInstance()->getPluginManager()->getPlugin("DEVirion")->getVirionClassLoader();
        $this->setClassLoaders([Server::getInstance()->getLoader(), $cl]);
    }
        $this->start(NativeThread::INHERIT_INI);
	}

    /**
     * @throws Exception
     */
    protected function onRun(): void
	{
        gc_enable();
		require_once $this->vendors . '/vendor/autoload.php';
		try {
            $connection = new AMQPStreamConnection($this->config->getHost(), $this->config->getPort(), $this->config->getUser(), $this->config->getPassword(), $this->config->getVhost());
			$this->connCreated = true;
		} catch (Throwable $exception){
			$this->connError = $exception;
			$this->connCreated = true;
			return;
		}
        $channel = $connection->channel();
        $this->config->apply($this->bufferRecv, $channel);
        while(true) {
            $start = microtime(true);
            $rows = $this->bufferSend->fetchQuery();
            if ($rows === false) {
                break ;
            } elseif ($rows === null) {
                $channel->wait(null, true, 10);
            }else {
                $channel->wait(null, true, 10);
                /**
                 * @var Publish $publisher
                 */
                $publisher = igbinary_unserialize($rows);
                $channel->basic_publish($publisher->getMessage(), $publisher->getExchange(), $publisher->getRoutingKey());
                $time = microtime(true) - $start;
                if ($time < self::RABBIT_MQ_PER_TICK) {
                    @time_sleep_until(microtime(true) + self::RABBIT_MQ_PER_TICK - $time);
                }
            }
        }
        $channel->close();
        $connection->close();
	}

    /**
     * @return QueryRecvQueue
     */
    public function getBufferRecv(): QueryRecvQueue
    {
        return $this->bufferRecv;
    }
	public function stopRunning(): void {
		$this->bufferSend->invalidate();
		parent::quit();
	}

	public function connCreated() : bool{
		return $this->connCreated;
	}

	public function hasConnError() : bool{
		return $this->connError !== null;
	}

	public function getConnError() : ?string{
		return $this->connError;
	}

	/**
	 * @return bool
	 */
	public function isBusy() : bool{
		return $this->busy;
	}

	public function quit() : void{
		$this->stopRunning();
		parent::quit();
	}
}