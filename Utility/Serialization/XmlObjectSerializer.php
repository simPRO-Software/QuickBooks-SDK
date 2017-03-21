<?php

require_once(PATH_SDK_ROOT . 'Utility/IEntitySerializer.php');

/**
 * Xml Serialize(r) to serialize and de serialize.
 */
class XmlObjectSerializer extends IEntitySerializer
{
	/**
	 * IDS Logger
	 * @var ILogger
	 */
	public $IDSLogger;


	/**
	 * Initializes a new instance of the XmlObjectSerializer class.
	 * @param ILogger idsLogger The ids logger.
	 */
	public function __construct($idsLogger = NULL)
	{
		if ($idsLogger)
			$this->IDSLogger = $idsLogger;
		else
			$this->IDSLogger = NULL; // new TraceLogger();
	}

	/**
	 * Serializes the specified entity.
	 * @param object entity The entity.
	 * @return string Returns the serialize entity in string format.
	 */
	public function Serialize($entity)
	{
		/*
		  string data = string.Empty;

		  try
		  {
		  UTF8Encoding encoder = new UTF8Encoding();
		  using (MemoryStream memoryStream = new MemoryStream())
		  {
		  XmlSerializer xmlSerializer = new XmlSerializer(entity.GetType());
		  xmlSerializer.Serialize(memoryStream, entity);
		  data = encoder.GetString(memoryStream.ToArray());
		  }
		  }
		  catch (SystemException ex)
		  {
		  SerializationException serializationException = new SerializationException(ex.Message, ex);
		  this.IDSLogger.Log(TraceLevel.Error, serializationException.ToString());
		  IdsExceptionManager.HandleException(serializationException);
		  }
		  data = data.Replace("T00:00:00Z", "");
		  data = data.Replace("T00:00:00", "");
		  return data;
		 */
	}

}

?>
