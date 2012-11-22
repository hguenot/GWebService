<?php
/**
 * DocCommand class file.
 *
 * @author HG Development
 * @copyright Copyright &copy; 2012 HG Development
 * @license GNU LESSER GPL 3
 * @version $Id: $
 */

use Ulrichsg\Getopt;

/**
 * APP_PATH refers to the application base path
 */
defined("APP_PATH") or define("APP_PATH", dirname(dirname(__FILE__)));
/**
 * EXTENSIONS_PATH refers to the extensions base path
*/
defined("EXTENSIONS_PATH") or define("EXTENSIONS_PATH", dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR.'extensions');

include_once(dirname(__FILE__).DIRECTORY_SEPARATOR."wsdl".DIRECTORY_SEPARATOR."assets".DIRECTORY_SEPARATOR."nusoap.php");
include_once(dirname(__FILE__).DIRECTORY_SEPARATOR."wsdl".DIRECTORY_SEPARATOR."assets".DIRECTORY_SEPARATOR."Getopt.php");


class WsdlCommandLogRoute extends CLogRoute {
	private static $levelsString = array(
			CLogger::LEVEL_TRACE => 'DBG',
			CLogger::LEVEL_PROFILE => 'DBG',
			CLogger::LEVEL_INFO => 'INF',
			CLogger::LEVEL_WARNING => 'WAR',
			CLogger::LEVEL_ERROR => 'ERR',
	);

	public $category;
	public function __construct($category = 'WsdlCommand') {
		$this->category = $category;
	}

	/*
	 *  array(
	 		*   [0] => message (string)
	 		*   [1] => level (string)
	 		*   [2] => category (string)
	 		*   [3] => timestamp (float, obtained by microtime(true));
	 		*/
	public function processLogs($logs) {
		list($message, $level, $cat, $d) = $logs;
		$msg = sprintf("%s [%s] %s: %s\n", date("H:i:s",$d), self::$levelsString[$level], $cat, $message);
		if ($level == CLogger::LEVEL_ERROR || $level == CLogger::LEVEL_WARNING) {
			fwrite(STDERR, $msg);
		} else {
			fwrite(STDOUT, $msg);
		}
	}

	private function log($message, $level, $subcategory = null) {
		$cat = $this->category;
		if ($subcategory !== null) $cat .= ".$subcategory";
		Yii::log($message, $level, $cat);
	}

	public function info($message, $subcategory = null) {
		$this->log($message, CLogger::LEVEL_INFO, $subcategory);
	}

	public function error($message, $subcategory = null) {
		$this->log($message, CLogger::LEVEL_ERROR, $subcategory);
	}

	public function debug($message, $subcategory = null) {
		$this->log($message, CLogger::LEVEL_TRACE, $subcategory);
	}
}


class WsdlCommand extends CConsoleCommand
{
	private $_logger = null;

	public function getLogger() {
		if ($this->_logger == null) {
			$this->_logger = new WsdlCommandLogRoute();
			Yii::getLogger()->autoFlush = 1;
			Yii::getLogger()->onFlush = array($this, 'processLogs');
		}

		return $this->_logger;
	}

	public function processLogs($event) {
		foreach ($event->sender->logs as $logs) {
			$this->logger->processLogs($logs);
		}
	}

	public function getHelp()
	{
		$app_path = APP_PATH;
		$cpn_path = $app_path.DIRECTORY_SEPARATOR."components";

		return <<<EOD
USAGE
  yiic wsdl [-u <service-url>] [-c <class-name>] [-o <output-path>] <wsdl-url>
  yiic wsdl [--url <service-url>] [--class <class-name>] [--output <output-path>] <wsdl-url>

DESCRIPTION
  This command generates a WSDL client for Web service. It uses GWebService extension for generating and connecting to web service.
  Visit http://yii.guenot.info/ for more explanation

PARAMETERS
  * <wsdl-url>    : URL of wsdl definition.
  * <service-url> : URL used to call webservice. If not defined, use first operation name to create class name.
  * <class-name>  : Generated client class name. If not defined, use first operation name to create class name.
  * <output-path> : the directory where generated client will be saved (under $app_path folder). It must already exists.
                    if not defined, use $cpn_path as output folder.

EXAMPLES
  * yiic wsdl http://localhost/?r=service - Generates WSDL client under APP_PATH/components folder
  * yiic wsdl -o wsdl http://localhost/?r=service - Generates WSDL client under APP_PATH/wsdl folder
  * yiic wsdl -c ServiceClient http://localhost/?r=service - Generates WSDL client under APP_PATH/components/ServiceClient.php file

EOD;
	}

	/**
	 * Execute the action.
	 * @param array command line parameters specific for this command
	 */
	public function run($args)
	{
		$getopt = new Getopt(array(
				array('D', 'debug', Getopt::NO_ARGUMENT),
				array('u', 'url', Getopt::REQUIRED_ARGUMENT),
				array('c', 'class', Getopt::REQUIRED_ARGUMENT),
				array('o', 'output', Getopt::REQUIRED_ARGUMENT),
		));

		try {
			$getopt->parse($args);
			$args = $getopt->getOperands();
		} catch(Exception $ex) {
			$this->usageError("Invalid command line : {$ex->getMessage()}.");
			return;
		}
		if (count($args) <= 0) {
			$this->usageError("Please give a WSDL url (using -u).");
			return;
		}

		$option = 'client';
		$wsdlUrl = $args[0];

		$className = null;
		if ($getopt->getOption('c') !== null)
			$className = str_replace(' ', '', ucwords($getopt->getOption('c')));

		$outputFolder = $getopt->getOption('o');
		if ($outputFolder == null)
			$outputFolder = 'components';
		$outputPath = APP_PATH . DIRECTORY_SEPARATOR . $outputFolder;

		if (!file_exists($outputPath)) {
			$this->usageError("Output folder $outputPath does not exists.");
			return;
		}
			
		if (!is_dir($outputPath)) {
			$this->usageError("Output path $outputPath is not a folder.");
			return;
		}

		$this->logger->info("Generating WSDL client for $wsdlUrl");
		$wsdl = new wsdl($wsdlUrl);

		$err = $wsdl->getError();
		if ($err) {
			$this->logger->error("Error parsing $wsdlUrl");
			$this->logger->error($err);
			return;
		}

		$themePath=dirname(__FILE__).'/wsdl';
		$viewFile=$themePath."/views/class.php";
		if (is_array($wsdl->getOperations()) && count($wsdl->getOperations()) >= 1) {
			$operations = $wsdl->getOperations();
			reset($operations);
			if ($className === null)  {
				$className = $this->first_element(".", current($operations)['name'])."Client";
			}
		}

		if ($className === null) {
			$this->usageError("Could not find class name, please give generated client class name (using -c).");
			return;
		}
		$this->logger->info("Generating class application.$outputFolder.$className");

		$content = html_entity_decode($content=$this->renderFile($viewFile,array(
				'className' => $className,
				'wsdlUrl' => $wsdlUrl,
				'wsdl' => $wsdl,
				'debug' => $getopt->getOption('D') !== null,
		),true));
		file_put_contents($outputPath.DIRECTORY_SEPARATOR.$className.'.php',$content);

		$this->logger->info($outputPath.DIRECTORY_SEPARATOR.$className.".php has been written.");
	}

	public function usageError($message) {
		$this->logger->error("$message\n\n".$this->getHelp()."\n");
		exit(1);
	}

	public function last_element($delimiter, $string) {
		$string = explode($delimiter, $string);
		if ($string === false) {
			return false;
		}
		return $string[count($string)-1];
	}

	public	function first_element($delimiter, $string) {
		$string = explode($delimiter, $string);
		if ($string === false) {
			return false;
		}
		return $string[0];
	}

	public function writeDumpComment($debug, $data) {
		if ($debug) {
			echo "/*\n";
			CVarDumper::dump($data);
			echo "\n*/\n";
		}
	}
	
	public function split_type($type) {
		$type = strrev($type);
		$type = explode(':', $type,2);
		if (isset($type[1])) {
			return array(strrev($type[1]), strrev($type[0]));
		} else {
			return array('', strrev($type[0]));
		}
	}
}
