<?php
/**
 * HttpHeader Plugin
 *
 * @copyright  Copyright (C) 2017 - 2018 Tobias Zulauf All rights reserved.
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
		'strict-transport-security',
		'content-security-policy',
		'content-security-policy-report-only',
		'x-frame-options',
		'x-xss-protection',
		'x-content-type-options',
		'referrer-policy',
		'expect-ct',
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
		// Set the default header when they are enabled
		$this->setDefaultHeader();

		// Handle CSP Header configuration
		$cspOptions = (int) $this->params->get('contentsecuritypolicy', 0);

		if ($cspOptions)
		{
			$this->setCspHeader();
		}

		// Handle HSTS Header configuration
		$hstsOptions = (int) $this->params->get('hsts', 0);

		if ($hstsOptions)
		{
			$this->setHstsHeader();
		}

		// Handle the additional httpheader
		$httpHeaders = $this->params->get('additional_httpheaders', array());

		foreach ($httpHeaders as $httpHeader)
		{
			// Handle the client settings for each header
			if (!$this->app->isClient($httpHeader->client) && $httpHeader->client != 'both')
			{
				continue;
			}

			if (empty($httpHeader->key) || empty($httpHeader->value))
			{
				continue;
			}

			if (!in_array(strtolower($httpHeader->key), $this->supportedHttpHeaders))
			{
				continue;
			}

			$this->app->setHeader($httpHeader->key, $httpHeader->value, true);
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
	 * Set the HSTS header when enabled
	 *
	 * @return  void
	 *
	 * @since   1.0.1
	 */
	private function setHstsHeader()
	{
		$maxAge        = (int) $this->params->get('hsts_maxage', 31536000);
		$hstsOptions   = array();
		$hstsOptions[] = $maxAge < 300 ? 'max-age: 300' : 'max-age: ' . $maxAge;

		if ($this->params->get('hsts_subdomains', 0))
		{
			$hstsOptions[] = 'includeSubDomains';
		}

		if ($this->params->get('hsts_preload', 0))
		{
			$hstsOptions[] = 'preload';
		}

		$this->app->setHeader('Strict-Transport-Security', implode('; ', $hstsOptions));
	}

	/**
	 * Set the CSP header when enabled
	 *
	 * @return  void
	 *
	 * @since   1.0.1
	 */
	private function setCspHeader()
	{
		$cspValues    = $this->params->get('contentsecuritypolicy_values', array());
		$cspReadOnly  = (int) $this->params->get('contentsecuritypolicy_report_only', 0);
		$csp          = $cspReadOnly === 0 ? 'Content-Security-Policy' : 'Content-Security-Policy-Report-Only';
		$newCspValues = array();

		foreach ($cspValues as $cspValue)
		{
			// Handle the client settings foreach header
			if (!$this->app->isClient($cspValue->client) && $cspValue->client != 'both')
			{
				continue;
			}

			// We can only use this if this is a valid entry
			if (isset($cspValue->directive) && isset($cspValue->value))
			{
				$newCspValues[] = trim($cspValue->directive) . ' ' . trim($cspValue->value);
			}
		}

		if (empty($newCspValues))
		{
			return;
		}

		$this->app->setHeader($csp, implode(';', $newCspValues));
	}
}
