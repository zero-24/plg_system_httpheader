<?php
/**
 * HttpHeader Plugin
 *
 * @copyright  Copyright (C) 2017 Tobias Zulauf All rights reserved.
 * @license    http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License Version 2 or Later
 */

defined('_JEXEC') or die;

/**
 * Plugin class for Http Header
 *
 * @since  1.0
 */
class PlgSystemHttpHeader extends JPlugin
{
	/**
	 * Affects constructor behavior. If true, language files will be loaded automatically.
	 *
	 * @var    boolean
	 * @since  1.0
	 */
	 protected $autoloadLanguage = true;

	/**
	 * Application object.
	 *
	 * @var    JApplicationCms
	 * @since  1.0
	 */
	protected $app;

	/**
	 * The list of the suported HTTP headers
	 *
	 * @var    array
	 * @since  1.0
	 */
	protected $supportedhttpHeaders = array(
		'Strict-Transport-Security',
		'Content-Security-Policy',
		'Content-Security-Policy-Report-Only',
		'X-Frame-Options',
		'X-XSS-Protection',
		'X-Content-Type-Options',
		'Referrer-Policy',
		// Upcoming Header
		'Expect-CT',
	);

	/**
	 * Listener for the `onAfterInitialise` event
	 *
	 * @return  void
	 *
	 * @since   1.0
	 */
	public function onAfterInitialise()
	{
		$this->setDefaultHeader();

		$httpheaders = $this->params->get('additional_httpheader', array());

		foreach ($httpheaders as $httpheader)
		{
			if (in_array($httpheader->key, $this->supportedhttpHeaders))
			{
				$this->app->setHeader($httpheader->key, $httpheader->value);
			}
		}
	}

	/**
	 * Set the default headers when enabled
	 *
	 * @return  void
	 *
	 * @since   1.0
	 */
	private function setDefaultHeader()
	{
		// X-Frame-Options
		$xframeoptions = $this->params->get('xframeoptions', 1);

		if ($xframeoptions)
		{
			$this->app->setHeader('X-Frame-Options', 'SAMEORIGIN');
		}

		// X-XSS-Protection
		$xxssprotection = $this->params->get('xxssprotection', 1);

		if ($xxssprotection)
		{
			$this->app->setHeader('X-XSS-Protection', '1; mode=block');
		}

		// X-Content-Type-Options
		$xcontenttypeoptions = $this->params->get('xcontenttypeoptions', 1);
		
		if ($xcontenttypeoptions)
		{
			$this->app->setHeader('X-Content-Type-Options', 'nosniff');
		}

		// Referrer-Policy
		$referrerpolicy = $this->params->get('referrerpolicy', 'no-referrer-when-downgrade');

		$this->app->setHeader('Referrer-Policy', $referrerpolicy);
	}
}
