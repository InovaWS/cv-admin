<?php
namespace Rio\Model;

use Slim\Http\Request;
class Uploads
{
	private $data;
	
	public function __construct(Request $request, array &$data = null)
	{
		if ($data === null)
			$this->data = &$_FILES;
		else
			$this->data = &$data;
		
		$post = $request->post();
		
		if ( $request->isPost() && empty($data) && empty($post) && $request->getContentLength() > 0 ) {
			$displayMaxSize = ini_get('post_max_size');
		
			switch ( substr($displayMaxSize,-1) ) {
				case 'G':
					$displayMaxSize = $displayMaxSize * 1024;
				case 'M':
					$displayMaxSize = $displayMaxSize * 1024;
				case 'K':
					$displayMaxSize = $displayMaxSize * 1024;
			}
			
			throw new \RuntimeException('Posted data is too large. ' . $request->getContentLength() .
			' bytes exceeds the maximum size of ' . $displayMaxSize . ' bytes.');
		}
	}
	
	public function get($name)
	{
		if (isset($this->data[$name])) {
			if (is_array($this->data[$name]['error'])) {
				$return = array();
				for ($i = 0, $l = count($this->data[$name]['error']); $i < $l; ++$i) {
					$return[] = new Upload($this->data[$name]['name'][$i], $this->data[$name]['type'][$i],
					$this->data[$name]['tmp_name'][$i], $this->data[$name]['error'][$i], $this->data[$name]['size'][$i]);
				}
				return $return;
			}
			else
				return new Upload($this->data[$name]['name'], $this->data[$name]['type'], $this->data[$name]['tmp_name'],
				$this->data[$name]['error'], $this->data[$name]['size']);
		}
		else
			return null;
	}
	
}