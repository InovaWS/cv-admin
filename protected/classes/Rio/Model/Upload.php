<?php
namespace Rio\Model;

class Upload
{
	
	const NO_ERROR = UPLOAD_ERR_OK;
	const ERROR_EXCEEDS_UPLOAD_SIZE = UPLOAD_ERR_INI_SIZE;
	const ERROR_EXCEEDS_FORM_UPLOAD_SIZE = UPLOAD_ERR_FORM_SIZE;
	const ERROR_PARTIALLY_UPLOADED = UPLOAD_ERR_PARTIAL;
	const ERROR_NO_FILE = UPLOAD_ERR_NO_FILE;
	const ERROR_NO_TMP_DIR = UPLOAD_ERR_NO_TMP_DIR;
	const ERROR_CANT_WRITE = UPLOAD_ERR_CANT_WRITE;
	const ERROR_EXTENSION = UPLOAD_ERR_EXTENSION;
	
	private $name;
	private $contentType;
	private $tmpName;
	private $error;
	private $size;
	
	public function __construct($name, $contentType, $tmpName, $error, $size)
	{
		$this->name = $name;
		$this->contentType = $contentType;
		$this->tmpName = $tmpName;
		$this->error = $error;
		$this->size = $size;
	}
	
	public function getName()
	{
		return $this->name;
	}
	
	public function getFilename()
	{
		return pathinfo($this->name, PATHINFO_FILENAME);
	}
	
	public function getExtension()
	{
		return pathinfo($this->name, PATHINFO_EXTENSION);
	}
	
	public function getContentType()
	{
		return $this->contentType;
	}
	
	public function getTemporaryFilePath()
	{
		return $this->tmpName;
	}
	
	public function getError()
	{
		return $this->error;
	}
	
	public function getSize()
	{
		return $this->size;
	}
	
	public function moveTo($path)
	{
		return move_uploaded_file($this->tmpName, $path);
	}
}