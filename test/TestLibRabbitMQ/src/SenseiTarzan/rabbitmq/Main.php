<?php

namespace SenseiTarzan\rabbitmq;

use PhpAmqpLib\Message\AMQPMessage;
use pmmp\thread\ThreadSafeArray;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\server\NetworkInterfaceRegisterEvent;
use pocketmine\network\mcpe\raklib\RakLibInterface;
use pocketmine\plugin\PluginBase;
use SenseiTarzan\RabbitMQ\Class\Binding;
use SenseiTarzan\RabbitMQ\Class\Consummer;
use SenseiTarzan\RabbitMQ\Class\Exchange;
use SenseiTarzan\RabbitMQ\Class\Publish;
use SenseiTarzan\RabbitMQ\Class\Queue;
use SenseiTarzan\RabbitMQ\Class\RabbitMQConfig;
use SenseiTarzan\RabbitMQ\Events\MessageAMPQEvent;
use SenseiTarzan\RabbitMQ\Exception\QueueShutdownException;
use SenseiTarzan\RabbitMQ\libRabbitMQ;
use SenseiTarzan\RabbitMQ\RabbitMQManager;
use SenseiTarzan\RabbitMQ\Thread\QueryRecvQueue;
use SenseiTarzan\RabbitMQ\Thread\ThreadRabbitMQ;

class Main extends PluginBase implements Listener
{

	public static  Main $instance;
	private RabbitMQManager|null $manager;

	protected function onLoad(): void
	{
		self::$instance = $this;
	}


	/**
	 */
	public function onEnable(): void {
        $test = new ThreadSafeArray();
        $test['direct_logs'] = ThreadSafeArray::fromArray(["warning", "error"]);
        $this->manager = libRabbitMQ::create($this, new RabbitMQConfig(
            '127.0.0.1',
            5672,
            'kitmap',
            'fsdfsdfds',
            'test',
            Queue::create("kitmap", auto_delete: false),
            ThreadSafeArray::fromArray([
                Consummer::create(
                    "kitmap",
                    noAck: true,
                    callback: static function (QueryRecvQueue $recvQueue, AMQPMessage $message) {
                        $recvQueue->publishResult($message);
                    },
                    cosummerTag: "test_cosummer"
                )
            ]),
            ThreadSafeArray::fromArray(
                [
                    Exchange::create(
                        'direct_logs',
                        "direct",
                        auto_delete: false
                    )
                ]
            ),
            ThreadSafeArray::fromArray(
                [
                    Binding::create(
                        "kitmap",
                        "direct_logs",
                        ThreadSafeArray::fromArray(["warning", "error"])
                    )
                ]
            )
        ));
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}


    public function onMessageAMPQ(MessageAMPQEvent $event): void
    {
        var_dump($event->getBody());
    }

    /**
     * @param PlayerChatEvent $event
     * @return void
     * @handleCancelled
     * @throws QueueShutdownException
     */
    public function onChat(PlayerChatEvent $event): void
    {
        $this->manager->addQuery(Publish::create(
            new AMQPMessage($event->getMessage()),
            "direct_logs",
            "error_test"
        ));
    }

    public function NetworkInterfaceRegister(NetworkInterfaceRegisterEvent $event) : void{
        $raknetInterface = $event->getInterface();
        if(!$raknetInterface instanceof RakLibInterface){
            return;
        }
        $raknetInterface->setPacketLimit(PHP_INT_MAX);
    }


	protected function onDisable(): void
	{
		$this->manager->close();
	}
}