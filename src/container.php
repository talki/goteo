<?php
/*
 * This file is part of the Goteo Package.
 *
 * (c) Platoniq y Fundación Goteo <fundacion@goteo.org>
 *
 * For the full copyright and license information, please view the README.md
 * and LICENSE files that was distributed with this source code.
 */

// example.com/src/container.php

use Goteo\Application\Config;
use Goteo\Application\App;
use Symfony\Component\DependencyInjection;
use Symfony\Component\DependencyInjection\Reference;

$sc = new DependencyInjection\ContainerBuilder();

// Context and matcher
$sc->register('context', 'Symfony\Component\Routing\RequestContext');
$sc->register('matcher', 'Symfony\Component\Routing\Matcher\UrlMatcher')
   ->setArguments(array('%routes%', new Reference('context')))
;

// Env name
$env = Config::get('env');

// logger sub-references
$sc->register('logger.processor.web', 'Goteo\Util\Monolog\Processor\WebProcessor')
   ->setArguments(array(App::getRequest()));
$sc->register('logger.processor.uid', 'Monolog\Processor\UidProcessor');
$sc->register('logger.processor.memory', 'Monolog\Processor\MemoryUsageProcessor');
$sc->register('logger.processor.instrospection', 'Monolog\Processor\IntrospectionProcessor')
   ->setArguments(array(monolog_level('error')));

//General main log
$sc->register('logger.formatter', 'Monolog\Formatter\LogstashFormatter')
   ->setArguments(array("app_$env", gethostname(), null, 'ctxt_', Monolog\Formatter\LogstashFormatter::V1));
$sc->register('logger.handler', 'Monolog\Handler\StreamHandler')
   ->setArguments(array(GOTEO_LOG_PATH."app_$env.log", monolog_level(Config::get('log.app'))))
   ->addMethodCall('setFormatter', array(new Reference('logger.formatter')))
;
$logger = $sc->register('logger', 'Monolog\Logger')
              ->setArguments(array('main', array(new Reference('logger.handler'))))
              ->addMethodCall('pushProcessor', array(new Reference('logger.processor.web')))
              ->addMethodCall('pushProcessor', array(new Reference('logger.processor.memory')))
;

// Console log
$sc->register('console_logger.formatter', 'Monolog\Formatter\LogstashFormatter')
   ->setArguments(array("console_$env", gethostname(), null, 'ctxt_', Monolog\Formatter\LogstashFormatter::V1));

$sc->register('console_logger.handler', 'Monolog\Handler\StreamHandler')
   ->setArguments(array(GOTEO_LOG_PATH."console_$env.log", monolog_level(Config::get('log.console'))))
   ->addMethodCall('setFormatter', array(new Reference('console_logger.formatter')))
;
$logger = $sc->register('console_logger', 'Monolog\Logger')
             ->setArguments(array('console', array(new Reference('console_logger.handler'))))
             ->addMethodCall('pushProcessor', array(new Reference('logger.processor.uid')))
             ->addMethodCall('pushProcessor', array(new Reference('logger.processor.memory')))
             ->addMethodCall('pushProcessor', array(new Reference('logger.processor.instrospection')))
;

// Syslog
$syslogger = $sc->register('syslogger', 'Monolog\Logger')
                ->setArguments(array('syslog', array(new Reference('logger.handler'))))
                ->addMethodCall('pushProcessor', array(new Reference('logger.processor.web')))
                ->addMethodCall('pushProcessor', array(new Reference('logger.processor.memory')))
;

// payments log
$paylogger = $sc->register('paylogger', 'Monolog\Logger')
                ->setArguments(array('payment', array(new Reference('logger.handler'))))
                ->addMethodCall('pushProcessor', array(new Reference('logger.processor.web')))
                ->addMethodCall('pushProcessor', array(new Reference('logger.processor.memory')))
;

// error mail send if defined
if (Config::get('log.mail')) {
	$sc->register('logger.mail_handler.formatter', 'Monolog\Formatter\HtmlFormatter');
	$mailer = Goteo\Model\Mail::createFromHtml(Config::getMail('fail'), '', "WebApp error in [".Config::get('url.main')."]");
	$mail   = $sc->register('logger.mail_handler', 'Goteo\Util\Monolog\Handler\MailHandler')
	           ->setArguments(array($mailer, '', Monolog\Logger::DEBUG, true))// delayed sending
	           ->addMethodCall('setFormatter', array(new Reference('logger.mail_handler.formatter')))
	// ->addMethodCall('pushProcessor', array("global_formatter"))
	;

	$sc->register('logger.buffer_handler', 'Monolog\Handler\FingersCrossedHandler')
	   ->setArguments(array(new Reference('logger.mail_handler'), monolog_level(Config::get('log.mail'))));
	$paylogger->addMethodCall('pushHandler', array(new Reference('logger.buffer_handler')));
	$logger->addMethodCall('pushHandler', array(new Reference('logger.buffer_handler')));
}

// resolver for the HttpKernel handle()
$sc->register('resolver', 'Symfony\Component\HttpKernel\Controller\ControllerResolver');

// Router for the dispatcher
$sc->register('listener.router', 'Symfony\Component\HttpKernel\EventListener\RouterListener')
   ->setArguments(array(new Reference('matcher')))
;

// always utf-8 output, just in case...
$sc->register('listener.response', 'Symfony\Component\HttpKernel\EventListener\ResponseListener')
   ->setArguments(array('UTF-8'))
;

// APP LISTENERS
// Nice Maintenance message, Other (fatal) thrown exceptions configuration
$sc->register('app.listener.exception', 'Goteo\Application\EventListener\ExceptionListener')
   ->setArguments(array(new Reference('logger')));
// Lang, cookies info, etc
$sc->register('app.listener.session', 'Goteo\Application\EventListener\SessionListener')
   ->setArguments(array(new Reference('logger')));
// Auth listener
$sc->register('app.listener.auth', 'Goteo\Application\EventListener\AuthListener')
   ->setArguments(array(new Reference('logger')));
// Invest listener
$sc->register('app.listener.invest', 'Goteo\Application\EventListener\InvestListener')
   ->setArguments(array(new Reference('paylogger')));
// Legacy Security ACL
$sc->register('app.listener.acl', 'Goteo\Application\EventListener\AclListener')
   ->setArguments(array(new Reference('logger')));

// Event Dispatcher object
$sc->register('dispatcher', 'Symfony\Component\EventDispatcher\EventDispatcher')
   ->addMethodCall('addSubscriber', array(new Reference('app.listener.exception')))
   ->addMethodCall('addSubscriber', array(new Reference('app.listener.session')))
   ->addMethodCall('addSubscriber', array(new Reference('app.listener.auth')))
   ->addMethodCall('addSubscriber', array(new Reference('app.listener.invest')))
   ->addMethodCall('addSubscriber', array(new Reference('app.listener.acl')))
   ->addMethodCall('addSubscriber', array(new Reference('listener.router')))
   ->addMethodCall('addSubscriber', array(new Reference('listener.response')))
;
// Goteo main app
$sc->register('app', 'Goteo\Application\App')
   ->setArguments(array(new Reference('dispatcher'), new Reference('resolver')))
;

// CONSOLE LISTENERS
// Options addons and exception processiongs
$sc->register('console.listener.exception', 'Goteo\Console\EventListener\ConsoleExceptionListener')
   ->setArguments(array(new Reference('console_logger')));
// Project processing
$sc->register('console.listener.project', 'Goteo\Console\EventListener\ConsoleProjectListener')
   ->setArguments(array(new Reference('console_logger')));
// Invest processing
$sc->register('console.listener.invest', 'Goteo\Console\EventListener\ConsoleInvestListener')
   ->setArguments(array(new Reference('console_logger')));
// Mailing processing
$sc->register('console.listener.mailing', 'Goteo\Console\EventListener\ConsoleMailingListener')
   ->setArguments(array(new Reference('console_logger')));
// Event dispatcher for console
$sc->register('console_dispatcher', 'Symfony\Component\EventDispatcher\EventDispatcher')
   ->addMethodCall('addSubscriber', array(new Reference('console.listener.exception')))
   ->addMethodCall('addSubscriber', array(new Reference('console.listener.project')))
   ->addMethodCall('addSubscriber', array(new Reference('console.listener.invest')))
   ->addMethodCall('addSubscriber', array(new Reference('console.listener.mailing')))
;

// Goteo Console
$sc->register('console', 'Goteo\Console\Console')
   ->setArguments(array(new Reference('console_dispatcher')))
;

return $sc;
