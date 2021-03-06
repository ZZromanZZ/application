<?php

/**
 * This file is part of the Nette Framework (http://nette.org)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com)
 */

namespace Nette\Bridges\ApplicationDI;

use Nette,
	Latte;


/**
 * Latte extension for Nette DI.
 *
 * @author     David Grudl
 * @author     Petr Morávek
 */
class LatteExtension extends Nette\DI\CompilerExtension
{
	public $defaults = array(
		'xhtml' => FALSE,
		'macros' => array(),
	);

	/** @var bool */
	private $debugMode;

	/** @var string */
	private $tempDir;


	public function __construct($tempDir, $debugMode = FALSE)
	{
		$this->tempDir = $tempDir;
		$this->debugMode = $debugMode;
	}


	public function loadConfiguration()
	{
		if (!class_exists('Latte\Engine')) {
			return;
		}

		// back compatibility
		$config = $this->compiler->getConfig();
		if (isset($config['nette']['latte']) && !isset($config[$this->name])) {
			// trigger_error("Configuration section 'nette.latte' is deprecated, use section '$this->name' instead.", E_USER_DEPRECATED);
			$config = Nette\DI\Config\Helpers::merge($config['nette']['latte'], $this->defaults);
		} else {
			$config = $this->getConfig($this->defaults);
		}
		if (isset($config['nette']['xhtml'])) {
			trigger_error("Configuration option 'nette.xhtml' is deprecated, use section '$this->name.xhtml' instead.", E_USER_DEPRECATED);
			$config['xhtml'] = $config['nette']['xhtml'];
		}

		$this->validateConfig($this->defaults, $config);
		$container = $this->getContainerBuilder();

		$latteFactory = $container->addDefinition('nette.latteFactory')
			->setClass('Latte\Engine')
			->addSetup('setTempDirectory', array($this->tempDir))
			->addSetup('setAutoRefresh', array($this->debugMode))
			->addSetup('setContentType', array($config['xhtml'] ? Latte\Compiler::CONTENT_XHTML : Latte\Compiler::CONTENT_HTML))
			->addSetup('Nette\Utils\Html::$xhtml = ?;', array((bool) $config['xhtml']))
			->setImplement('Nette\Bridges\ApplicationLatte\ILatteFactory');

		$container->addDefinition('nette.templateFactory')
			->setClass('Nette\Application\UI\ITemplateFactory')
			->setFactory('Nette\Bridges\ApplicationLatte\TemplateFactory');

		$container->addDefinition('nette.latte')
			->setClass('Latte\Engine')
			->addSetup('::trigger_error', array('Service nette.latte is deprecated, implement Nette\Bridges\ApplicationLatte\ILatteFactory.', E_USER_DEPRECATED))
			->addSetup('setTempDirectory', array($this->tempDir))
			->addSetup('setAutoRefresh', array($this->debugMode))
			->addSetup('setContentType', array($config['xhtml'] ? Latte\Compiler::CONTENT_XHTML : Latte\Compiler::CONTENT_HTML))
			->addSetup('Nette\Utils\Html::$xhtml = ?;', array((bool) $config['xhtml']))
			->setAutowired(FALSE);

		foreach ($config['macros'] as $macro) {
			if (strpos($macro, '::') === FALSE && class_exists($macro)) {
				$macro .= '::install';
			}
			$this->addMacro($macro);
		}

		if (class_exists('Nette\Templating\FileTemplate')) {
			$container->addDefinition('nette.template')
				->setFactory('Nette\Templating\FileTemplate')
				->addSetup('::trigger_error', array('Service nette.template is deprecated.', E_USER_DEPRECATED))
				->addSetup('registerFilter', array(new Nette\DI\Statement(array($latteFactory, 'create'))))
				->addSetup('registerHelperLoader', array('Nette\Templating\Helpers::loader'))
				->setAutowired(FALSE);
		}
	}


	/**
	 * @param  callable
	 * @return void
	 */
	public function addMacro($macro)
	{
		Nette\Utils\Validators::assert($macro, 'callable');

		$container = $this->getContainerBuilder();
		$container->getDefinition('nette.latte')
			->addSetup('?->onCompile[] = function($engine) { ' . $macro . '($engine->getCompiler()); }', array('@self'));

		$container->getDefinition('nette.latteFactory')
			->addSetup('?->onCompile[] = function($engine) { ' . $macro . '($engine->getCompiler()); }', array('@self'));
	}

}
