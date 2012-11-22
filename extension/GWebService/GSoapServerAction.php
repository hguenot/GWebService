<?php
Yii::import('ext.GWebService.*');

/**
 * GSoapServerAction implements an action that provides Web services using {@link GSoapServer}.
 * 
 * Following GET parameter ({@link ws}, this action can <ul>
 * <li>display the WSDL content associated to Soap Server.</li> 
 * <li>respond to a web service call by invoking the requesting method</li>
 * </ul>
 * 
 * By default, GSoapServerAction will use the current controller as the Web service provider. 
 * See {@link GWsdlGenerator} on how to declare methods that can be remotely invoked.
 *
 * @author HG Development
 * @package webservice
 * @copyright Copyright &copy; 2012 HG Development
 * @license GNU LESSER GPL 3
 * @version $Id: $
 */
class GSoapServerAction extends CAction
{
	/**
	 * @var mixed the Web service provider object or class name.
	 * Defaults to null, meaning the current controller is used as the service provider.
	 */
	public $provider;
	
	/**
	 * @var string the name of the GET parameter that differentiates a WSDL request
	 * from a Web service request. If this GET parameter exists, the request is considered
	 * as a Web service request; otherwise, it is a WSDL request.  Defaults to 'ws'.
	 */
	public $serviceVar='ws';
	
	/**
	 * @var string URL used by SOAP client to request SOAP method execution.
	 */
	public $serviceUrl=null;
	
	private $_service;

	/**
	 * Runs the action.
	 * If the GET parameter {@link serviceVar} exists, the action handle the remote method invocation.
	 * If not, the action will serve WSDL content;
	 */
	public function run()
	{
		$controller=$this->getController();
		
		if(($provider=$this->provider)===null)
			$provider=$controller;
				
		if(($serviceVar=$this->serviceVar)===null)
			$serviceVar='ws';
				
		if(($serviceUrl=$this->serviceUrl)===null) {
			$params = array($serviceVar=>1);
			$serviceUrl=Yii::app()->createAbsoluteUrl($_GET['r'],$params);
		}
		
		$this->_service=$this->createWebService($provider, $serviceUrl);
		
		if(isset($_GET[$this->serviceVar]))
			$this->_service->run();
		else
			$this->_service->renderWsdl();

		Yii::app()->end();
	}

	/**
	 * Returns the Web service instance currently being used.
	 * @return CWebService the Web service instance
	 */
	public function getService()
	{
		return $this->_service;
	}

	/**
	 * Creates a {@link CWebService} instance.
	 * You may override this method to customize the created instance.
	 * @param mixed $provider the web service provider class name or object
	 * @param string $serviceUrl the URL for the Web service.
	 * @return GSoapServer the Soap Server instance
	 */
	protected function createWebService($provider, $serviceUrl)
	{
		return Yii::createComponent(array(
				'class' => 'ext.GWebService.GSoapServer',
				'provider' => $provider,
				'serviceUrl' => $serviceUrl
		));
	}
}