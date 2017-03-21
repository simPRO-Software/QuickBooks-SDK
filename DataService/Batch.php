<?php

require_once(PATH_SDK_ROOT . 'Data/IntuitRestServiceDef/IPPBatchItemRequest.php');
require_once(PATH_SDK_ROOT . 'Data/IntuitRestServiceDef/IPPBatchItemResponse.php');
require_once(PATH_SDK_ROOT . 'Data/IntuitRestServiceDef/IPPIntuitBatchRequest.php');
require_once(PATH_SDK_ROOT . 'Data/IntuitRestServiceDef/IPPQueryResponse.php');
require_once(PATH_SDK_ROOT . 'DataService/IntuitBatchResponse.php');
require_once(PATH_SDK_ROOT . 'DataService/IntuitResponseStatus.php');
require_once(PATH_SDK_ROOT . 'Utility/Serialization/XmlObjectSerializer.php');
require_once(PATH_SDK_ROOT . 'Exception/IdsExceptionManager.php');
require_once(PATH_SDK_ROOT . 'Exception/IdsError.php');

/**
 * Describes operations that can be included in a batch
 */
class OperationEnum
{
	/**
	 * create Operation
	 * @var string create
	 */
	const create = "create";

	/**
	 * update Operation
	 * @var string update
	 */
	const update = "update";

	/**
	 * sparse update Operation
	 * @var string sparseupdate
	 */
	const sparseupdate = "sparse update";

	/**
	 * delete Operation
	 * @var string delete
	 */
	const delete = "delete";

	/**
	 * void Operation
	 * @var string void
	 */
	const void = "void";

	/**
	 * query Operation
	 * @var string query
	 */
	const query = "query";


	/**
	 * report Operation
	 * @var string report
	 */
	const report = "report";

}

/**
 * This class contains code for Batch Processing.
 */
class Batch
{
	/**
	 * batch requests
	 * @var array batchRequests
	 */
	private $batchRequests;

	/**
	 * batch responses
	 * @var array batchResponses
	 */
	private $batchResponses;

	/**
	 * Intuit batch item responses list.
	 * @var array batchResponses
	 */
	public $intuitBatchItemResponses;

	/**
	 * service context object.
	 * @var ServiceContext serviceContext
	 */
	private $serviceContext;

	/**
	 * rest handler object.
	 * @var IRestHandler restHandler
	 */
	private $restHandler;

	/**
	 * serializer to be used.
	 * @var IEntitySerializer responseSerializer
	 */
	private $responseSerializer;

	/**
	 * Initializes a new instance of the Batch class.
	 * @param $serviceContext The service context.
	 * @param $restHandler The rest handler.
	 */
	public function __construct($serviceContext, $restHandler)
	{
		$this->serviceContext = $serviceContext;
		$this->restHandler = $restHandler;
		$this->responseSerializer = CoreHelper::GetSerializer($this->serviceContext, false);
		$this->batchRequests = array();
		$this->batchResponses = array();
		$this->intuitBatchItemResponses = array();
	}

	public function getBatchRequests()
	{
		return $this->batchRequests;
	}

	public function setBatchRequests($batchRequests)
	{
		$this->batchRequests = $batchRequests;
	}

	public function getBatchResponses()
	{
		return $this->batchResponses;
	}

	public function setBatchResponses($batchResponses)
	{
		$this->batchResponses = $batchResponses;
	}

	public function getIntuitBatchItemResponses()
	{
		return $this->intuitBatchItemResponses;
	}

	public function setIntuitBatchItemResponses($intuitBatchItemResponses)
	{
		$this->intuitBatchItemResponses = $intuitBatchItemResponses;
	}

	public function addIntuitBatchItemResponses($intuitBatchItemResponse, $bId)
	{
		$this->intuitBatchItemResponses[$bId] = $intuitBatchItemResponse;
	}

	public function addBatchResponses($batchResponses)
	{
		$this->batchResponses[] = $batchResponses;
	}

	/**
	 * Gets the count.
	 * @return int count
	 */
	public function Count()
	{
		return count($this->batchRequest);
	}

	/**
	 * Gets list of entites in case ResponseType is Report.
	 */
	public function ReadOnlyCollection()
	{
		return $this->intuitBatchItemResponses;
	}

	/**
	 * Gets the IntuitBatchResponse with the specified id.
	 * @param string $id unique batchitem id
	 */
	public function IntuitBatchResponse($id)
	{
		foreach ($this->batchResponses as $oneBatchResponse) {
			if ($oneBatchResponse->bId == $id) {
				$result = ProcessBatchItemResponse($oneBatchResponse);
				return $result;
			}
		}
		return NULL;
	}

	/**
	 * Adds the specified query.
	 * @param string $query IDS query.
	 * @param string $id unique batchitem id.
	 */
	public function AddQuery($query, $id, $originalId)
	{
		if (!$query) {
			return array('error' => "Query is Empty");
		}
		if (!$id) {
			return array('error' => "ID is not set");
		}

		if (count($this->batchRequests) > 25) {
			return array('error' => "Only 25 queries allowed per batch");
		}
		$batchItem = new IPPBatchItemRequest();
		$batchItem->Query = $query;
		$batchItem->bId = $id;
		$batchItem->originalId = $originalId;
		$batchItem->operationSpecified = true;
		//$batchItem->ItemElementName = ItemChoiceType6::Query;
		$this->batchRequests[] = $batchItem;
	}

	/**
	 * Adds the specified query.
	 * @param IEntity entity entitiy for the batch operation.
	 * @param string id Unique batchitem id
	 * @param OperationEnum operation operation to be performed for the entity.
	 */
	public function AddEntity($entity, $id, $operation)
	{
		if (!$entity) {
			return array('error' => "Entity cannot be empty");
		}

		if (!$id) {
			return array('error' => "ID cannot be empty");
		}

		if (!$operation) {
			return array('error' => "Operation cannot be empty");
		}

		foreach ($this->batchRequests as $oneBatchRequest) {
			if ($oneBatchRequest->bId == $id) {
			return array('error' => "Batch ID already used");
			}
		}

		$batchItem = new IPPBatchItemRequest();
		$batchItem->IntuitObject = $entity;
		$batchItem->bId = $id;
		$batchItem->operation = $operation;
		$batchItem->operationSpecified = true;

		$this->batchRequests[] = $batchItem;
	}

	/**
	 * Removes batchitem with the specified batchitem id.
	 * @param string id unique batchitem id
	 */
	public function Remove($id)
	{
		if (!$id) {
			$exception = new IdsException('BatchItemIdNotFound: id');
			IdsExceptionManager::HandleException($exception);
		}

		$revisedBatchRequests = array();
		foreach ($this->batchRequests as $oneBatchRequest) {
			if ($oneBatchRequest->bId == $id) {
				// Exclude
			} else {
				$revisedBatchRequests[] = $oneBatchRequest;
			}
		}
		$this->batchRequests = $revisedBatchRequests;
	}

	/**
	 * Remove all the batchitem requests.
	 */
	public function RemoveAll()
	{
		$this->batchRequests = array();
	}

	/**
	 * This method executes the batch request.
	 */
	public function Execute()
	{
		$this->serviceContext->IppConfiguration->Logger->CustomLogger->Log(TraceLevel::Info, "Started Executing Method Execute for Batch");

		// Create Intuit Batch Request
		$intuitBatchRequest = new IPPIntuitBatchRequest();
		$intuitBatchRequest->BatchItemRequest = $this->batchRequests;

		$uri = "company/{1}/batch?requestid=" . rand() . rand();
		$uri = str_replace('{1}', $this->serviceContext->realmId, $uri);

		// Creates request parameters
		$requestParameters = NULL;
		if (0) { // ($this->serviceContext->IppConfiguration->Message->Request->SerializationFormat == SerializationFormat::Json)
			// No JSON support here yet
			//$requestParameters = new RequestParameters($uri, 'POST', CoreConstants::CONTENTTYPE_APPLICATIONJSON, NULL);
		} else {
			$requestParameters = new RequestParameters($uri, 'POST', CoreConstants::CONTENTTYPE_APPLICATIONXML, NULL);
		}


		$restRequestHandler = new SyncRestHandler($this->serviceContext);
		try {
			// Get literal XML representation of IntuitBatchRequest into a DOMDocument
			$httpsPostBodyPreProcessed = XmlObjectSerializer::getPostXmlFromArbitraryEntity($intuitBatchRequest, $urlResource);
			$doc = new DOMDocument();
			$domObj = $doc->loadXML($httpsPostBodyPreProcessed);
			$xpath = new DOMXpath($doc);

			// Replace generically-named IntuitObject nodes with tags that describe contained objects
			$objectIndex = 0;
			while (1) {
				$matchingElementArray = $xpath->query("//IntuitObject");
				if (is_null($matchingElementArray))
					break;

				if ($objectIndex >= count($intuitBatchRequest->BatchItemRequest))
					break;

				foreach ($matchingElementArray as $oneNode) {

					// Found a DOMNode currently named "IntuitObject".  Need to rename to
					// entity that describes it's contents, like "ns0:Customer" (determine correct
					// name by inspecting IntuitObject's class).
					if ($intuitBatchRequest->BatchItemRequest[$objectIndex]->IntuitObject) {
						// Determine entity name to use
						$entityClassName = get_class($intuitBatchRequest->BatchItemRequest[$objectIndex]->IntuitObject);
						$entityTransferName = XmlObjectSerializer::cleanPhpClassNameToIntuitEntityName($entityClassName);
						$entityTransferName = 'ns0:' . $entityTransferName;

						// Replace old-named DOMNode with new-named DOMNode
						$newNode = $oneNode->ownerDocument->createElement($entityTransferName);
						if ($oneNode->attributes->length) {
							foreach ($oneNode->attributes as $attribute) {
								$newNode->setAttribute($attribute->nodeName, $attribute->nodeValue);
							}
						}
						while ($oneNode->firstChild)
							$newNode->appendChild($oneNode->firstChild);
						$oneNode->parentNode->replaceChild($newNode, $oneNode);
					}
					break;
				}
				$objectIndex++;
			}
			$httpsPostBody = $doc->saveXML();

			list($responseCode, $responseBody) = $restRequestHandler->GetResponse($requestParameters, $httpsPostBody, NULL);
		} catch (Exception $e) {
			IdsExceptionManager::HandleException($e);
		}

		CoreHelper::CheckNullResponseAndThrowException($responseBody);

		try {

			$this->batchResponses = array();
			$this->intuitBatchItemResponses = array();

			// No JSON support here yet
			// de serialize object
			$responseXmlObj = simplexml_load_string($responseBody);
			foreach ($responseXmlObj as $oneXmlObj) {
				// process batch item
				$intuitBatchResponse = $this->ProcessBatchItemResponse($oneXmlObj);
				$this->intuitBatchItemResponses[] = $intuitBatchResponse;

				if ($intuitBatchResponse && $intuitBatchResponse->entities && count($intuitBatchResponse->entities))
					$this->batchResponses[] = $intuitBatchResponse->entities;
			}
		} catch (Exception $e) {
			return NULL;
		}

		$this->serviceContext->IppConfiguration->Logger->CustomLogger->Log(TraceLevel::Info, "Finished Execute method for batch.");
	}

	/**
	 * Prepare IdsException out of Fault object.
	 * @param Fault fault Fault object.
	 * @return IdsException IdsException object.
	 */
	public function IterateFaultAndPrepareException($fault)
	{
		if ($fault == NULL) {
			return NULL;
		}

		$idsException = null;

		// Create a list of exceptions.
		$aggregateExceptions = array();

		// Check whether the fault is null or not.
		if ($fault != NULL && $fault->type != NULL) {
			// Fault types can be of Validation, Service, Authentication and Authorization. Run them through the switch case.
			switch ($fault->type) {
				// If Validation errors iterate the Errors and add them to the list of exceptions.
				case "Validation":
				case "ValidationFault":
					if ($fault->Error != null && count($fault->Error) > 0) {
						foreach ($fault->Error as $item) {
							// Add commonException to aggregateExceptions
							$aggregateExceptions[] = new IdsError($item->Message);
						}

						// Throw specific exception like ValidationException.
						$idsException = new ValidationException($aggregateExceptions);
					}

					break;
				// If Validation errors iterate the Errors and add them to the list of exceptions.
				case "Service":
				case "ServiceFault":
					if ($fault->Error != null && count($fault->Error) > 0) {
						foreach ($fault->Error as $item) {
							// Add commonException to aggregateExceptions
							$aggregateExceptions[] = new IdsError($item->Message);
						}

						// Throw specific exception like ServiceException.
						$idsException = new ServiceException($aggregateExceptions);
					}

					break;
				// If Validation errors iterate the Errors and add them to the list of exceptions.
				case "Authentication":
				case "AuthenticationFault":
				case "Authorization":
				case "AuthorizationFault":
					if ($fault->Error != null && count($fault->Error) > 0) {
						foreach ($fault->Error as $item) {
							// Add commonException to aggregateExceptions
							$aggregateExceptions[] = new IdsError($item->Message);
						}

						// Throw specific exception like AuthenticationException which is wrapped in SecurityException.
						$idsException = new SecurityException($aggregateExceptions);
					}

					break;
				// Use this as default if there was some other type of Fault
				default:
					if ($fault->Error != null && count($fault->Error) > 0) {
						foreach ($fault->Error as $item) {
							// Add commonException to aggregateExceptions
							// CommonException defines four properties: Message, Code, Element, Detail.
							$aggregateExceptions[] = new IdsError($item->Message);
						}

						// Throw generic exception like IdsException.
						$idsException = new IdsException("Fault Exception of type: " . $fault->type . " has been generated.");
					}
					break;
			}
		}

		// Return idsException which will be of type Validation, Service or Security.
		return $idsException;
	}

}

?>
