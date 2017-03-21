<?php

require_once(PATH_SDK_ROOT . 'Core/Configuration/Request.php');
require_once(PATH_SDK_ROOT . 'Core/Configuration/Response.php');

/**
 * Contains properties about the Request and Response configuration settings.
 */
class Message
{
	/**
	 * Request configuration settings
	 * @var QBRequest
	 */
	public $Request;

	/**
	 * Response configuration settings
	 * @var Response
	 */
	public $Response;

	/**
	 * Initializes a new instance of the Message class.
	 *
	 * @param QBRequest $Request Request configuration settings
	 * @param Response $Response Response configuration settings
	 */
	public function __construct($Request=NULL, $Response=NULL)
	{
		$this->Request = $Request;
		$this->Response = $Response;
	}
}

?>
