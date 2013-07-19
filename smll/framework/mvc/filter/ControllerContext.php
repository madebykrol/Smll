<?php
namespace smll\framework\mvc\filter;
use smll\framework\mvc\interfaces\IViewResult;

use smll\framework\utils\HashMap;

use smll\framework\mvc\filter\interfaces\IContext;

class ControllerContext implements IContext {
	
	private $result = null;
	
	public function setParameters(HashMap $parameters) {}
	public function getParameters() {}
	
	public function setResult(IViewResult $result = null) {
		$this->result = $result;
	}
	
	public function getResult() {
		return $this->result;
	}
}