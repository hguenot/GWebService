<?php

/**
 * Class used for WSDL generation on a nusoap_server object.
 * It based on {@link CWsdlGenerator} for doc comment parsing and interpretation.
 * 
 * @author HG Development, heavily based on previous work by Qiang Xue 
 * @package webservice
 * @copyright Copyright &copy; 2012 HG Development
 * @license GNU LESSER GPL 3
 * @version $Id: $
 */
class GWsdlGenerator extends CComponent
{
	private $_operations;
	private $_types;
	private $_messages;
	
	public static $typeMap=array(
				'string'=>'xsd:string',
				'str'=>'xsd:string',
				'int'=>'xsd:int',
				'integer'=>'xsd:int',
				'float'=>'xsd:float',
				'double'=>'xsd:float',
				'bool'=>'xsd:boolean',
				'boolean'=>'xsd:boolean',
				'date'=>'xsd:date',
				'time'=>'xsd:time',
				'datetime'=>'xsd:dateTime',
				'array'=>'soap-enc:Array',
				'object'=>'xsd:struct',
				'mixed'=>'xsd:anyType',
		);

	/**
	 * Register WSDL types in nusoap_server using reflection on class.
	 *
	 * @param string|object $provider Povider object or class name used to generate WSDL
	 * @param nusoap_server $server NuSoap server reference
	 * @param string $serviceUrl Service URL for web service execution
	 */
	public function generateWsdl($provider, &$server, $serviceUrl)
	{
		$this->_operations=array();
		$this->_types=array();
		$this->_messages=array();

		if (is_object($provider)) {
			$className = get_class($className);
		} else {
			$className = $provider;
		}

		$reflection=new ReflectionClass($className);
		foreach($reflection->getMethods() as $method)
		{
			if($method->isPublic())
				$this->processMethod($method);
		}

		$this->buildWsdl($className, $server, $serviceUrl);
	}

	/*
	 * @param ReflectionMethod $method method
	*/
	private function processMethod($method)
	{
		$comment=$method->getDocComment();
		if(strpos($comment,'@soap')===false)
			return;

		$methodName=$method->getName();
		$comment=preg_replace('/^\s*\**(\s*?$|\s*)/m','',$comment);
		$params=$method->getParameters();
		$message=array();
		$n=preg_match_all('/^@param\s+([\w\.]+(\[\s*\])?)\s*?(.*)$/im',$comment,$matches);
		if($n>count($params))
			$n=count($params);
		for($i=0;$i<$n;++$i)
			$message[$params[$i]->getName()]=array($this->processType($matches[1][$i]), trim($matches[3][$i])); // name => type, doc

		$this->_messages[$methodName.'Request']=$message;

		if(preg_match('/^@return\s+([\w\.]+(\[\s*\])?)\s*?(.*)$/im',$comment,$matches))
			$return=array($this->processType($matches[1]),trim($matches[2])); // type, doc
		else
			$return=null;
		
		if ($return != null)
			$this->_messages[$methodName.'Response']=array('return'=>$return);

		if(preg_match('/^\/\*+\s*([^@]*?)\n@/s',$comment,$matches))
			$doc=trim($matches[1]);
		else
			$doc='';
		$this->_operations[$methodName]=$doc;
	}

	/*
	 * @param string $type PHP variable type
	*/
	private function processType($type)
	{
		$typeMap=self::$typeMap;
		
		if(isset($typeMap[$type]))
			return $typeMap[$type];
		else if(isset($this->_types[$type]))
			return is_array($this->_types[$type]) ? 'tns:'.$type : $this->_types[$type];
		else if(($pos=strpos($type,'[]'))!==false) // if it is an array
		{
			$type=substr($type,0,$pos);
			if(isset($typeMap[$type]))
				$this->_types[$type.'[]']='xsd:'.$type.'Array';
			else
			{
				$this->_types[$type.'[]']=$type.'Array';
				$this->processType($type);
			}
			return $this->_types[$type.'[]'];
		}
		else // class type
		{
			$type=Yii::import($type,true);
			$this->_types[$type]=array();
			$class=new ReflectionClass($type);
			foreach($class->getProperties() as $property)
			{
				$comment=$property->getDocComment();
				if($property->isPublic() && strpos($comment,'@soap')!==false)
				{
					if(preg_match('/@var\s+([\w\.]+(\[\s*\])?)\s*?(.*)$/mi',$comment,$matches))
						$this->_types[$type][$property->getName()]=array('type' => $this->processType($matches[1]), 'name' => $property->getName()); //, 'comment' => trim($matches[3]));  // name => type, doc
				}
			}
			return 'tns:'.$type;
		}
	}

	/**
	 * Register WSDL types and action in nusoap_server instance.
	 *
	 * @param string $className Class name of the provider
	 * @param nusoap_server $server Soap server provided using nusoap
	 * @param string $serviceUrl Url for service execution.
	 */
	protected function buildWsdl($className, &$server, $serviceUrl) {

		if (!$server->wsdl) {
			$server->configureWSDL($className . "Service", false, htmlentities($serviceUrl));
		}
		$wsdl = &$server->wsdl;

		Yii::log(CVarDumper::dumpAsString($this, 10), CLogger::LEVEL_TRACE, "GWS");
		// first create complex types
		foreach ($this->_types as $name => $def) {
			if (is_array($def)) {
				$wsdl->addComplexType($name, 'complexType', 'struct', 'all', '', $def);
			}
		}

		foreach ($this->_types as $name => $def) {
			if (!is_array($def)) {
				if(($pos=strpos($name,'[]'))!==false) // if it is an array
				{
					$type=substr($name,0,$pos);
					$wsdl->addComplexType(
							$def, 
							'complexType', 
							'array', 
							'', 
							'SOAP-ENC:Array',
							array($type =>array ('name' => $type, 'type' => $type)), 
							array(
									$type => array('ref'=>'SOAP-ENC:arrayType','wsdl:arrayType'=>$type.'[]')
							),
							$type);
				}
			}
		}

		foreach ($this->_operations as $name => $cmt) {
			$request = array();
			$response = array();
			if (isset($this->_messages[$name.'Request'])) {
				foreach ($this->_messages[$name.'Request'] as $key => $def) {
					$request[$key] = $def[0];
				}
			}
			if (isset($this->_messages[$name.'Response'])) {
				foreach ($this->_messages[$name.'Response'] as $key => $def) {
					$response[$key] = $def[0];
				}
			}
			$server->register($className.".".$name, $request, $response, false, false, 'rpc', 'encoded', $cmt, '');
		}
	}
}
