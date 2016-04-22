<?php
include('smll/SmllClassLoader.php');
use smll\framework\utils\AnnotationHandler;
use smll\framework\di\ContainerBuilder;
use smll\modules\DefaultContainerModule;
use smll\SmllClassLoader;


$start = (float) array_sum(explode(' ',microtime()));

$autoloader = new SmllClassLoader();
$autoloader->register();


$dic = new ContainerBuilder(new AnnotationHandler());
$dic->loadModule(new DefaultContainerModule());


$application = $dic->get('smll\framework\IApplication');
$application->setContainer($dic);
$application->init();
$application->run();
$application->close();
#
$end = (float) array_sum(explode(' ',microtime()));
//print "Processing time: ". sprintf("%.4f", ($end-$start))." seconds.";