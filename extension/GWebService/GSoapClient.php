<?php

include_once(dirname(__FILE__).DIRECTORY_SEPARATOR.implode(DIRECTORY_SEPARATOR, array('nusoap', 'nusoap.php')));

/**
 * Define a SOAP client to access web service.
 *
 * @author HG Development
 * @package webservice
 * @copyright Copyright &copy; 2012 HG Development
 * @license GNU LESSER GPL 3
 * @version $Id: $
*/
class GSoapClient extends CApplicationComponent {

	private $_client;

	/**
	 * @var string URL for calling web service
	 */
	public $serviceUrl = null;

	/**
	 * @var string URL of WSDL
	 */
	public $wsdlUrl = null;

	/**
	 * @var string Proxy host (if connected through a proxy)
	 */
	public $proxyhost = false;

	/**
	 * @var string Proxy username (if connected through a proxy)
	 */
	public $proxyusername = false;

	/**
	 * @var string Proxy password (if connected through a proxy)
	 */
	public $proxypassword = false;

	/**
	 * @var string Proxy port (if connected through a proxy)
	 */
	public $proxyport = false;

	/**
	 * @var int Connection timeout.
	 */
	public $timeout = 30;

	/**
	 * @var string Proxy username (if connected through a proxy)
	 */
	public $portName = '';


	/**
	 * Initialiaze Soap client (using nusoap_client). If an error occured, {@link CException} will be thrown.
	 *
	 * @throws CException If connection to web service failed.
	 */
	public function init() {
		$endpoint = $this->serviceUrl;
		$wsdl = false;
		if ($endpoint == null) {

			$endpoint = $this->wsdlUrl;
			$wsdl = true;
		}

		$this->_client = new nusoap_client(
				$endpoint, $wsdl,
				$this->proxyhost, $this->proxyport, $this->proxyusername, $this->proxypassword,
				$this->timeout, $this->timeout, $this->portName
		);

		$err = $this->_client->getError();
		if ($err) {
			Yii::log($this->_client->getDebug(), CLogger::LEVEL_ERROR, "GWS.client");
			throw new CException($err);
		}
	}

	/**
	 * Call remote operation on web service.
	 *
	 * @param string $operation Remote operation name.
	 * @param array $params Parameters for remote calling.
	 * @param string $namespace Method namespace.
	 * @param string $soapAction Soap action
	 *
	 * @throws CException If remote call failed.
	 *
	 * @return mixed Result of remote calling.
	 */
	public function call($operation, $params=array(), $namespace='http://tempuri.org', $soapAction=''){
		if ($this->_client == null) {
			$this->init();
		}
		$res = $this->_client->call($operation, $params, $namespace, $soapAction);

		if ($this->_client->fault) {
			Yii::log($this->_client->getDebug(), CLogger::LEVEL_ERROR, "GWS.client");
			throw new CException($this->_client->faultstring);
		} else {
			// Check for errors
			$err = $this->_client->getError();
			if ($err) {
				Yii::log($this->_client->getDebug(), CLogger::LEVEL_ERROR, "GWS.client");
				throw new CException($err);
			} else {
				Yii::log($this->_client->getDebug(), CLogger::LEVEL_TRACE, "GWS.client");
				return $res;
			}
		}
	}
	
}
