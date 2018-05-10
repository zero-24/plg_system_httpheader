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
	protected $supportedHttpHeaders = array(
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

		// Handle CSP
		$cspOptions = $this->params->get('contentsecuritypolicy', 0);

		if ($cspOptions)
		{
			$this->setCspHeader();
		}

		// Handle the additional httpheader
		$httpHeaders = $this->params->get('additional_httpheader', array());

		foreach ($httpHeaders as $httpHeader)
		{
			// Handle the client settings foreach header
			if (!$this->app->isClient($httpHeader->client) && $httpHeader->client != 'both')
			{
				continue;
			}

			if (in_array($httpHeader->key, $this->supportedHttpHeaders))
			{
				$this->app->setHeader($httpHeader->key, $httpHeader->value);
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
		$xFrameOptions = $this->params->get('xframeoptions', 1);

		if ($xFrameOptions)
		{
			$this->app->setHeader('X-Frame-Options', 'SAMEORIGIN');
		}

		// X-XSS-Protection
		$xXssProtection = $this->params->get('xxssprotection', 1);

		if ($xXssProtection)
		{
			$this->app->setHeader('X-XSS-Protection', '1; mode=block');
		}

		// X-Content-Type-Options
		$xContentTypeOptions = $this->params->get('xcontenttypeoptions', 1);
		
		if ($xContentTypeOptions)
		{
			$this->app->setHeader('X-Content-Type-Options', 'nosniff');
		}

		// Referrer-Policy
		$referrerpolicy = $this->params->get('referrerpolicy', 'no-referrer-when-downgrade');

		if ($referrerpolicy !== 'disabled')
		{
			$this->app->setHeader('Referrer-Policy', $referrerpolicy);
		}
	}


	/**
	 * Set the CSP header when enabled
	 *
	 * @return  void
	 *
	 * @since   1.0
	 */
	private function setCspHeader()
	{
		$cspValues    = $this->params->get('contentsecuritypolicy_values', array());
		$cspReadOnly  = $this->params->get('contentsecuritypolicy_report_only', 0);
		$csp          = $cspReadOnly == 0 ? 'Content-Security-Policy' : 'Content-Security-Policy-Report-Only';
		$newCspValues = array();

		foreach ($cspValues as $cspValue)
		{
			// Handle the client settings foreach header
			if (!$this->app->isClient($cspValue->client) && $cspValue->client != 'both')
			{
				continue;
			}

			$newCspValues[] = trim($cspValue->key) . ': ' . trim($cspValue->value);
		}

		if (!empty($newCspValues))
		{
			$this->app->setHeader($csp, implode(';', $newCspValues));
		}
	}
}
