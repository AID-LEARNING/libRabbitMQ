<?php

namespace SenseiTarzan\RabbitMQ;

use Exception;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Terminal;
use SenseiTarzan\RabbitMQ\Class\RabbitMQConfig;
use SenseiTarzan\RabbitMQ\Class\MongoError;
use Symfony\Component\Filesystem\Path;

class libRabbitMQ
{



	/** @var bool */
	private static bool $packaged;

	public static function isPackaged() : bool{
		return self::$packaged;
	}

	public static function detectPackaged() : void{
		self::$packaged = __CLASS__ !== 'SenseiTarzan\RabbitMQ\libRabbitMQ';

		if(!self::$packaged && defined("pocketmine\\VERSION")){
			echo Terminal::$COLOR_YELLOW . "Warning: Use of unshaded libRabbitMQ detected. Debug mode is enabled. This may lead to major performance drop. Please use a shaded package in production. See https://poggit.pmmp.io/virion for more information.\n";
		}
	}

    /**
     * @param PluginBase $plugin
     * @param RabbitMQConfig $configData
     * @return RabbitMQManager
     * @throws Exception
     */
	public static function create(PluginBase $plugin, RabbitMQConfig $configData) : RabbitMQManager{
		$vendors = Path::join($plugin->getServer()->getDataPath(), "libRabbitMQ");
		if(is_dir($vendors . "/vendor"))
			require_once $vendors . '/vendor/autoload.php';
		else {
			mkdir($vendors, 0777, true);
			throw new Exception("libRabbitMQ library not found");
		}
		libRabbitMQ::detectPackaged();

		$manager = new RabbitMQManager($plugin, $vendors, $configData);
		while(!$manager->connCreated()){
			usleep(1000);
		}
		if($manager->hasConnError()){
			throw new Exception( $manager->getConnError());
		}
		return $manager;
	}
}