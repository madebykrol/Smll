<?php
namespace smll\framework;
use smll\framework\di\interfaces\IDependencyContainer;

use smll\framework\IApplication;
use smll\framework\io\Request;
use smll\framework\io\interfaces\IRequest;
use smll\framework\route\interfaces\IRouter;
use smll\framework\mvc\filter\interfaces\IFilterConfig;
use smll\framework\di\ContainerBuilder;
use smll\modules\DefaultContainerModule;
use smll\framework\di\Service;
use smll\framework\route\Route;
use smll\framework\utils\HashMap;
use smll\framework\utils\ArrayList;
use smll\framework\mvc\interfaces\IController;
use smll\framework\security\interfaces\IAuthenticationProvider;
use smll\framework\security\Principal;
use smll\framework\security\Identity;
use smll\framework\security\interfaces\IIdentity;
use smll\framework\mvc\filter\AuthorizationContext;
use smll\framework\exceptions\EmptyResultException;
use smll\framework\mvc\interfaces\IViewResult;
use smll\framework\mvc\interfaces\IModelBinder;
use smll\framework\mvc\interfaces\IViewEngineRepository;
use smll\framework\mvc\SmllViewEngine;
use smll\framework\utils\interfaces\IAnnotationHandler;

use \ReflectionMethod;
use \ReflectionClass;
use \ReflectionProperty;
use \Exception;

abstract class HttpApplication Implements IApplication {
	
	private $request;
	private $router;
	
	/**
	 * [Inject(smll\framework\mvc\interfaces\IModelBinder)]
	 * @var IModelBinder
	 */
	private $modelBinder;
	
	protected $bundleConfig;
	protected $filterConfig;
	protected $routerConfig;
	
	protected $controllerPaths;
	protected $viewPaths;
	
	/**
	 * [Inject(smll\framework\mvc\interfaces\IViewEngineRepository)]
	 * @var IViewEngineRepository
	 */
	protected $viewEngines;
	
	/**
	 * 
	 * @var IController
	 */
	protected $currentExecutingController;
	
	/**
	 * 
	 * @var IDependencyContainer
	 */
	protected $container;
	
	
	protected $annotationHandler = null;
	
	public function __construct(
			IRequest $request, 
			IRouter $router, 
			IFilterConfig $actionFilters) {
		
		$this->request 	= $request;
		$this->router 	= $router;
		$this->actionFilters = $actionFilters;

		$this->routerConfig = $router->getRouterConfig();
		$this->filterConfig = $actionFilters;
		$this->controllerPaths = new ArrayList();
	}
	
	public function setModelBinder(IModelBinder $binder) {
		if($this->modelBinder == null) {
			$this->modelBinder = $binder;
		} else {
			throw new Exception("Cannot change modelbinder after initialization");
		}
	}
	
	public function setViewEngines(IViewEngineRepository $engines) {
		$this->viewEngines = $engines;
	}
	
	/**
	 * @return IModelBinder
	 */
	public function getModelBinder() {
		return $this->modelBinder;
	}
	
	public function install() {
		$this->applicationInstall();
	}
	
	public function run($request = null) {
		
		
		if(!$this->checkInstallStatus()) {
			$this->install();
		}
		
		if($request == null) {
			$request = $this->request;
		}
		
		$this->preStart();
		
		$this->applicationStart();
		// Verify request
		$this->verifyRequest($request);
		
		// Lookup 
		$action = $this->lookup($request);
		
		$controller = $action->getController()."Controller";
		$actionName = $action->getAction();
		
		$output = $this->processAction($controller, $actionName, $action->getParameters());
		
		print $output;
		
		$this->applicationFinish();
	}
	
	/**
	 * @return IDependencyContainer
	 */
	public function getContainer() {
		return $this->container;
	}
	
	public function setContainer(IDependencyContainer $container) {
		$this->container = $container;
	}
	
	
	public function setAnnotationHandler(IAnnotationHandler $handler) {
		$this->annotationHandler = $handler;
	}
	
	public function getAnnotationHandler() {
		return $this->annotationHandler;
	}
	
	/**
	 * (non-PHPdoc)
	 * @see IApplication::getCurrentExecutingController()
	 */
	public function &getCurrentExecutingController() {
		return $this->currentExecutingController;
	}
	
	public function getApplicationRoot() {
		return $this->request->getApplicationRoot();
	}
	
	public function init() {
		$this->configControllerPaths();
		
		foreach($this->controllerPaths->getIterator() as $path) {
			$handle = opendir($path);
			while (false !== ($entry = readdir($handle))) {
				if(strpos($entry, "Controller") !== FALSE) {
					$entry = explode(".", $entry);
					$controllerName = $entry[0];
					
					$namespace = str_replace('/', '\\', $path);
					
					$this->container->register($namespace.$controllerName, 'controllers-'.$controllerName)
						->set('application', $this)
						->set('modelState', new Service('smll\framework\mvc\interfaces\IModelState'))
						->inRequestScope();
				}
			}
		}
		
		$this->setAnnotationHandler($this->container->get('smll\framework\utils\interfaces\IAnnotationHandler'));
		
		$this->viewEngines->addEngine(new SmllViewEngine());
	}
	
	/**
	 * @return boolean;
	 */
	protected function checkViewFile($file) {
		if(is_file($file)) {
			return true;
		} 
		return false;
	}
	
	protected function verifyRequest($request) {
		// Perform some verification and first hint of tinkered requests.
	}
	
	public function processAction($controller, $actionName, HashMap $parameters = null) {
	
		$controller = $this->container->get('controllers-'.$controller);
		
		if($controller instanceof IController) {
			$class = get_class($controller);
			
			$this->attachPrincipal($controller);
			
			$this->currentExecutingController = $controller;
			
			$class = new ReflectionClass($class);
			
			$actionName = str_replace(array('-', ' ', '+'),'_', $actionName);
			
			$passed = false;
			$output = "";
			$result = null;
			if($this->request->getRequestMethod() == Request::METHOD_POST 
					&& $class->hasMethod("post_".$actionName)) {
				$method = $class->getMethod("post_".$actionName);
			} else {
				$method = $class->getMethod($actionName);
			}
			
			$result = $this->processFilters($method, $class, $controller);
			
			if($result == null) {
				$result = $this->callAction($method, $controller, $parameters);
			}
			
			if($result instanceof IViewResult) {
				$engines = $this->viewEngines->getEngines();
				$render = "";
				
				$controllerName = str_replace("Controller", "", get_class($controller));
				$controllerName = explode('\\', $controllerName);
				$controllerName = $controllerName[count($controllerName)-1];
				
				foreach($engines->getIterator() as $engine) {
					$render = $engine->renderResult($result, $controllerName, $actionName);
				}
				$output .= $render;
			} else if(is_string($result)) {
				$output = $result;
			} else {
				throw new EmptyResultException('Action '. $actionName . ' on '. get_class($controller). ' did not yeild any result');
			}
			
				// Output Action.
		} else {
			throw new Exception();
		}
		
		return $output;
	}
	
	protected function processFilters(ReflectionMethod $method, ReflectionClass $class, IController $controller) {
		
		$result = null;
		// Get AuthorizationFilters
		$annotationHandler = $this->getAnnotationHandler();
			
		foreach($this->filterConfig->getAuthorizationFilters()->getIterator() as $filter) {
			$authorizationContext = new AuthorizationContext();
			$authorizationContext->setController($controller);
			$authorizationContext->setApplication($this);
		
			$annotations = array();
		
			if($annotationHandler->hasAnnotation('Authorize', $class)) {
				$annotations['Authorize'] = $annotationHandler->getAnnotation('Authorize', $class);
			} else {
				if($annotationHandler->hasAnnotation('Authorize', $method)) {
					$annotations['Authorize'] = $annotationHandler->getAnnotation('Authorize', $method);
				}
			}
		
			if($annotationHandler->hasAnnotation('AllowAnonymous', $method)) {
				$annotations['AllowAnonymous'] = $annotationHandler->getAnnotation('AllowAnonymous', $method);
			}
		
			if($annotationHandler->hasAnnotation('InRole', $method)) {
				$annotations['InRole'] = $annotationHandler->getAnnotation('InRole', $method);
					
			}
		
			$filter->setAnnotations($annotations);
			$filter->onAuthorization($authorizationContext);
			$result = $authorizationContext->getResult();
		}
		
		return $result;
		
	}
	
	protected function filter(ReflectionMethod $method) {
		$passed = true;
		$filters = $this->filterConfig->getFilters();
		
		foreach($filters->getIterator() as $filter) {
			$passed = $filter->pass($method);
			$message = $filter->getMessage();
		}
		
		return $passed;
	}
	
	protected function checkInstallStatus() {
		return true;
	}
	
	protected function attachPrincipal(IController $controller) {
		$authenticationHandler = $this->container->get('smll\framework\security\interfaces\IAuthenticationProvider');
		$principal = new Principal();
		$principal->setIdentity(new Identity(null, false, null));
		
		if($authenticationHandler instanceof IAuthenticationProvider) {
			$p = $authenticationHandler->getPrincipal();
			if($p != null) {
				$principal = $p;
			}
		}
			
		$controller->setPrincipal($principal);
	}
	
	protected function lookup(IRequest $request) {
		$action = $this->router->lookup($request);
		
		return $action;
	}
	
	protected function callAction(ReflectionMethod $method, IController &$controller, HashMap $parameters = null) {
		$args = array();
		if(isset($parameters)) {
			foreach($method->getParameters() as $parameter) {
				$name = $parameter->getName();
				$class = $parameter->getClass();
				
				if($class != null && $class instanceof ReflectionClass) {
					
					
					$args[] = $this->modelBinder->bindModel($class, $controller, $parameters);
				} else {
					$args[] = $parameters->get($name);
				}
				
			}
		}
		return $method->invokeArgs($controller, $args);
	}

	abstract protected function applicationStart();
	
	protected function applicationFinish() {}
	protected function applicationInstall() {}
	protected function preStart() {}
	protected function configControllerPaths() {
		$this->controllerPaths->add('src/controllers/');
	}
	
	/**
	 * 
	 * @param ApplicationState $appstate
	 */
	protected function applicationResume(ApplicationState $appstate) {}
}