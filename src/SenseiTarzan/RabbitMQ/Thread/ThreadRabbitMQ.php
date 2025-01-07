<?php

namespace SenseiTarzan\RabbitMQ\Thread;

use Composer\Autoload\ClassLoader;
use Exception;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exception\AMQPConnectionClosedException;
use PhpAmqpLib\Exception\AMQPNoDataException;
use PhpAmqpLib\Exception\AMQPTimeoutException;
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
	{
        if(!libRabbitMQ::isPackaged()){
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
        $connection->reconnect();
        $channel = $connection->channel();
        $this->config->apply($this->bufferRecv, $channel);
        while(true) {
            $rows = $this->bufferSend->fetchQuery();
            if ($rows === false) {
                break ;
            }
            /**
             * @var ?Publish $publisher
             */
            $publisher = $rows === null ? null : igbinary_unserialize($rows);
            $this->tick($connection, $channel, $publisher);
        }
        $channel->close();
        $connection->close();
	}

    public function tick(AMQPStreamConnection $connection, AMQPChannel $channel, ?Publish $publisher = null): void
    {
        $start = microtime(true);
        try {
            $channel->wait(null, true, 10);
            if ($publisher){
                $channel->basic_publish($publisher->getMessage(), $publisher->getExchange(), $publisher->getRoutingKey());
                $time = microtime(true) - $start;
                if ($time < self::RABBIT_MQ_PER_TICK) {
                    @time_sleep_until(microtime(true) + self::RABBIT_MQ_PER_TICK - $time);
                }
            }
        } catch (AMQPTimeoutException) {
            // something might be wrong, try to send heartbeat which involves select+write
            $channel->getConnection()->checkHeartBeat();
        } catch (AMQPNoDataException) {

        } catch (AMQPConnectionClosedException)
        {
            $connection->reconnect();
        }
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