<?php
namespace smll\framework\mvc;
use smll\framework\mvc\interfaces\IController;
use smll\framework\mvc\ViewReslut;
use smll\framework\IApplication;
use smll\framework\security\interfaces\IAuthenticationProvider;
use smll\framework\security\interfaces\IMembershipProvider;
use smll\framework\security\interfaces\IPrincipal;
use smll\framework\mvc\interfaces\IModelState;
use smll\framework\http\interfaces\IHeaderRepository;


class Controller implements IController {
	
	protected $viewBag = array(
		'title' => '',		
	);
	/**
	 * [Inject(IApplication)]
	 * @var IApplication
	 */
	private $application = null;
	
	/**
	 * [Inject(smll\framework\http\interfaces\IHeaderRepository)]
	 * @var IHeaderRepository;
	 */
	protected $headers = null;
	
	/**
	 * @var ImodelState
	 */
	protected $modelState = null;
	
	/**
	 * [Inject(smll\framework\security\interfaces\IAuthenticationProvider)]
	 * @var IAuthenticationProvider
	 */
	protected $authentication;
	
	/**
	 * [Inject(smll\framework\security\interfaces\IMembershipProvider)]
	 * @var IMembershipProvider
	 */
	protected $membership;
	
	/**
	 * @var IPrincipal
	 */
	protected $user;
	
	/**
	 * 
	 * (non-PHPdoc)
	 * @see IController::setApplication()
	 */
	public function setApplication(IApplication $application) {
		$this->application = $application;
	}
	
	public function setPrincipal(IPrincipal $user) {
		$this->user = $user;
	}
	
	/**
	 * @return IPrincipal
	 */
	public function getPrincipal() {
		return $this->user;
	}
	
	public function setHeaders(IHeaderRepository $headers) {
		$this->headers = $headers;
	}
	
	public function setMembership(IMembershipProvider $membership) {
		$this->membership = $membership;
	}
	
	public function setAuthentication(IAuthenticationProvider $authentication) {
		$this->authentication = $authentication;
	}
	
	/**
	 * (non-PHPdoc)
	 * @see IController::setModelState()
	 */
	public function setModelState(IModelState $modelState) {
		$this->modelState = $modelState;
	}
	
	/**
	 * (non-PHPdoc)
	 * @see IController::getModelState()
	 */
	public function &getModelState() {
		return $this->modelState;
	}
	
	/**
	 * @return ViewResult
	 * @param object $model
	 * @param string $view;
	 */
	public function view($model = null, $view = null) { 
		
		$result = new ViewResult();
		$result->init();
		if($model != null) {
			$result->setModel($model);
		}
		
		if($view != null) {
			$result->setView($view);
		}
		
		$result->setViewBag($this->viewBag);
		$result->setHeaders($this->headers->getHeaders());
		
		return $result;
		
	}

	
	/**
	 * 
	 * Overloaded HttpRedirect
	 * redirectToAction($action)
	 * redirectToAction($action, $controller)
	 * redirectToAction($action, $controller, $parameters)
	 * 
	 * @param unknown $action
	 * @param string $controller
	 * @param unknown $parameters
	 */
	public function redirectToAction($action, $controller = null, $parameters = array()) {
		
		$result = new ViewResult();
		$result->init();
		if($controller == null) {
			$controller = str_replace("Controller", "", get_class($this));
		}
		if(strtolower($action) == "index") {
			$action = "";
		} else {
			$action = "/".$action;
		}
		
		$controller = explode('\\', $controller);
		$controller = $controller[count($controller)-1];
		
		$controller = "/".$controller;
		$this->headers->add("Location", $this->application->getApplicationRoot().$controller.$action);
		
		$result->setHeaders($this->headers->getHeaders());
		return $result;
	}
	
	public function onActionError() {
		return "";
	}
	
	public function __toString() {
		return str_replace("Controller", "", get_class($this));
	}
	
}