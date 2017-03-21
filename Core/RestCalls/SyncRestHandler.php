<?php

require_once(PATH_SDK_ROOT . 'Core/RestCalls/RestHandler.php');
require_once(PATH_SDK_ROOT . 'Core/RestCalls/FaultHandler.php');
require_once(PATH_SDK_ROOT . 'Utility/IntuitErrorHandler.php');

/**
 * SyncRestHandler contains the logic for preparing the REST request, calls REST services and returns the response.
 */
class SyncRestHandler extends RestHandler
{
	/**
	 * The context
	 * @var ServiceContext
	 */
	private $serviceContext;

	/**
	 * Initializes a new instance of the SyncRestHandler class.
	 *
	 * @param ServiceContext $context The context
	 */
	public function SyncRestHandler($context)
	{
		parent::__construct($context);
		$this->context = $context;

		return $this;
	}

	/**
	 * Returns the response by calling REST service.
	 *
	 * @param RequestParameters $requestParameters The parameters
	 * @param string $requestBody The request body
	 * @param string $oauthRequestUri The OAuth request uri
	 */
	public function GetResponseSyncRest($requestParameters, $requestBody, $oauthRequestUri)
	{
		$handler = new FaultHandler($this->context);

		// Create a variable for storing the response.
		$response = '';
		$responseCode = NULL;
		try {
			// Check whether the retryPolicy is null.
			if ($this->context->IppConfiguration->RetryPolicy == NULL) {
				// If yes then call the rest service without retry framework enabled.
				list($responseCode, $response) = $this->CallRestService($requestParameters, $requestBody, $oauthRequestUri);
			} else {
				// Not yet implemented
				throw new IdsException("Retry policy not available in this SDK");

				// If no then call the rest service using the execute action of retry framework.
				// $this->context->IppConfiguration->RetryPolicy->ExecuteAction(() =>
				// {
				//     $response = $this->CallRestService($requestParameters, $requestBody, $oauthRequestUri);
				// });
			}
		} catch (Exception $webException) {
			// System.Net.HttpWebRequest.Abort() was previously called.-or- The time-out
			// period for the request expired.-or- An error occurred while processing the request.
			$isIpp = false;
			if ($this->context->ServiceType == IntuitServicesType::IPP) {
				$isIpp = true;
			}

			$idsException = $handler->ParseResponseAndThrowException($webException, $responseCode, $isIpp);
			if ($idsException != null) {
				$this->context->IppConfiguration->Logger->CustomLogger->Log(TraceLevel::Error, $idsException->getMessage());
				throw $idsException;
			}
		}

		if ($this->context->ServiceType == IntuitServicesType::IPP) {
			// Handle errors here
			IntuitErrorHandler::HandleErrors($response);
		} else {
			// Check the response if there are any fault tags and throw appropriate exceptions.
			$oneException = $handler->ParseErrorResponseAndPrepareException($response);
			if (exception != null) {
				throw $oneException;
			}
		}

		// Return the response.
		return $response;
	}

	/**
	 * Returns the response headers and response code from a called OAuth object
	 *
	 * @param OAuth $oauth A called OAuth object
	 * @return array elements are 0: HTTP response code; 1: response content, 2: HTTP response headers
	 */
	private function GetOAuthResponseHeaders($oauth)
	{
		$response_code = NULL;
		$response_xml = NULL;
		$response_headers = array();

		try {
			$response_xml = $oauth->getLastResponse();

			$response_headers = array();
			$response_headers_raw = $oauth->getLastResponseHeaders();
			$response_headers_rows = explode("\r\n", $response_headers_raw);
			foreach ($response_headers_rows as $header) {
				$keyval = explode(":", $header);
				if (2 == count($keyval))
					$response_headers[$keyval[0]] = trim($keyval[1]);

				if (FALSE !== strpos($header, 'HTTP'))
					list(, $response_code, ) = explode(' ', $header);
			}

			// Decompress, if applicable
			if ('QBO' == $this->context->serviceType ||
					'QBD' == $this->context->serviceType) {
				// Even if accept-encoding is set to deflate, server never (as far as we know) actually chooses
				// to respond with Content-Encoding: deflate.  Thus, the inspection of 'Content-Encoding' response
				// header rather than assuming that server will respond with encoding specified by accept-encoding
				if ($this->ResponseCompressor &&
						$response_headers &&
						array_key_exists('Content-Encoding', $response_headers)) {
					$response_xml = $this->ResponseCompressor->Decompress($response_xml, $response_headers);
				}
			}
		} catch (Exception $e) {
			Console::Log('SyncRestHandler GetOAuthResponseHeaders $e', $e);
		}

		return array($response_code, $response_xml, $response_headers);
	}

	/**
	 * Returns the response by calling REST service.
	 *
	 * @param ServiceContext $requestParameters The parameters
	 * @param string $requestBody The request body
	 * @param string $oauthRequestUri The OAuth request uri
	 * @return array elements are 0: HTTP response code; 1: HTTP response body
	 */
	public function GetResponse($requestParameters, $requestBody, $oauthRequestUri, $debug = true)
	{
		if ($this->context->getSimproDebug()) {
			Console::Log("QB Sync Rest Handler :: Called PrepareRequest Update.", TraceLevel::Info);
		}
		// This step is required since the configuration settings might have been changed.
		$this->RequestCompressor = CoreHelper::GetCompressor($this->context, true);
		$this->ResponseCompressor = CoreHelper::GetCompressor($this->context, false);
		$this->RequestSerializer = CoreHelper::GetSerializer($this->context, true);
		$this->ResponseSerializer = CoreHelper::GetSerializer($this->context, false);

		// Determine dest URI
		$requestUri = '';
		if ($requestParameters->ApiName) {
			$requestUri = $this->context->baseserviceURL . $requestParameters->ApiName;
		} else if ($oauthRequestUri) {
			// Prepare the request Uri from base Uri and resource Uri.
			$requestUri = $oauthRequestUri;
		} else if ($requestParameters->ResourceUri) {
			$requestUri = $this->context->baseserviceURL . $requestParameters->ResourceUri;
		}
		if ($this->context->getSimproDebug()) {
			Console::Log("QB Sync Rest Handler :: requestUri", $requestUri);
		}
		//minorVersion support
		if ($this->context->minorVersion) {
			if ($this->queryToArray($requestUri) == false) { //if no query string params
				$requestUri .= "?minorversion=" . $this->context->minorVersion;
			} else {
				$requestUri .= "&minorversion=" . $this->context->minorVersion;
			}
		}
		if ($this->context->getSimproDebug()) {
			Console::Log("QB Sync Rest Handler :: requestUri after minor version", $requestUri);
		}
		$oauth = new OAuth($this->context->requestValidator->ConsumerKey, $this->context->requestValidator->ConsumerSecret);
		$oauth->setToken($this->context->requestValidator->AccessToken, $this->context->requestValidator->AccessTokenSecret);
		$oauth->enableDebug();
		$oauth->setAuthType(OAUTH_AUTH_TYPE_AUTHORIZATION);
		$oauth->disableSSLChecks();
		$httpHeaders = array();
		if ('QBO' == $this->context->serviceType ||
				'QBD' == $this->context->serviceType) {
			// IDS call
			$httpHeaders = array('host' => parse_url($requestUri, PHP_URL_HOST),
				'user-agent' => CoreConstants::USERAGENT,
				'accept' => '*/*',
				'connection' => 'close',
				'content-type' => $requestParameters->ContentType,
				'content-length' => strlen($requestBody));
			// Log Request Body to a file
			$this->RequestLogging->LogPlatformRequests($requestBody, $requestUri, $httpHeaders, TRUE);
			if ($requestBody && $this->RequestCompressor)
				$this->RequestCompressor->Compress($httpHeaders, $requestBody);
			if ($this->ResponseCompressor)
				$this->ResponseCompressor->PrepareDecompress($httpHeaders);
		}
		else {
			// IPP call
			$httpHeaders = array('user-agent' => CoreConstants::USERAGENT);
		}
		try {
			if ('POST' == $requestParameters->HttpVerbType)
				$OauthMethod = OAUTH_HTTP_METHOD_POST;
			else if ('GET' == $requestParameters->HttpVerbType)
				$OauthMethod = OAUTH_HTTP_METHOD_GET;
			$oauth->fetch($requestUri, $requestBody, $OauthMethod, $httpHeaders);
		} catch (OAuthException $e) {
			list($response_code, $response_xml, $response_headers) = $this->GetOAuthResponseHeaders($oauth);
			if ($this->context->getSimproDebug()) {
				Console::Log('QB Sync Rest Handler :: error thrown', $e->getMessage());
			}
			return array("error" => array(
				"intuit" => $oauth->debugInfo['body_recv'],
				"exception" => $e->getMessage(),
				"debug" => $oauth->debugInfo,
				"responseCode" => $response_code,
				"responseXML" => $response_xml
			));
			if ($this->context->getSimproDebug()) {
				Console::Log("error details", array("error" => array(
					"exception" => $e->getMessage(),
					"debug" => $oauth->debugInfo,
					"responseCode" => $response_code,
					"responseXML" => $response_xml
				)));
			}
		}
		// successful - now return array with info in it
		list($response_code, $response_xml, $response_headers) = $this->GetOAuthResponseHeaders($oauth);
		if ($this->context->getSimproDebug()) {
			Console::Log('$response_code, $response_xml, $response_headers', $response_code, $response_xml, $response_headers);
		}
		return array("array" => array($response_code, $response_xml));
	}

	/**
	 * Calls the rest service.
	 *
	 * @param RequestParameters $requestParameters The parameters
	 * @param string $requestBody The request body
	 * @param string $oauthRequestUri The OAuth request uri
	 * @return array elements are 0: HTTP response code; 1: HTTP response body
	 */
	private function CallRestService($requestParameters, $requestBody, $oauthRequestUri)
	{
		// ********************** DONE
		if ($this->context->getSimproDebug()) {
			Console::Log('QB Sync Rest Handler :: CallRestService Getting the response from service', TraceLevel::Info);
		}
		// Call the service and get response.
		$response = $request->GetResponse($requestParameters, $requestBody, $oauthRequestUri);
		if (isset($response['error']) || empty($response)) {
			return $response; // error has occured - return this back to front
		}
		list($httpWebResponseCode, $httpWebResponseBody) = $response['array'];
		if ($this->context->getSimproDebug()) {
			Console::Log('QB Sync Rest Handler :: CallRestService Got the response from service', TraceLevel::Info);
		}
		// Parse the response from the call and return.
		$httpParsedWebResponseBody = $this->ParseResponse($httpWebResponseBody);
		return array($httpWebResponseCode, $httpParsedWebResponseBody);
		// ********************** DONE
	}

	private function queryToArray($qry)
	{
		$result = array();
		//string must contain at least one = and cannot be in first position
		if (strpos($qry, '=')) {

			if (strpos($qry, '?') !== false) {
				$q = parse_url($qry);
				$qry = $q['query'];
			}
		} else {
			return false;
		}

		foreach (explode('&', $qry) as $couple) {
			list ($key, $val) = explode('=', $couple);
			$result[$key] = $val;
		}

		return empty($result) ? false : $result;
	}
}

?>
