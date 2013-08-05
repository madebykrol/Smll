<?php
namespace smll\framework\mvc\filter;

use smll\framework\utils\interfaces\IAnnotationHandler;

use smll\framework\mvc\filter\interfaces\IActionFilter;
class OutputCacheFilter implements IActionFilter {

    /**
     *
     * @var IAnnotationHandler
     */
    private $annotationHandler;

    public function setAnnotationHandler(IAnnotationHandler $annotationHandler) {
        $this->annotationHandler = $annotationHandler;
    }

    public function onActionCall(ActionContext $context) {

        $application = $context->getApplication();

        $action = $context->getAction();
        if($this->annotationHandler->hasAnnotation('OutputCache', $action)) {
            // perform output caching
             
        }


    }
}