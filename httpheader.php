<?php
/**
 * HttpHeader Plugin
 *
 * @copyright  Copyright (C) 2017 - 2019 Tobias Zulauf All rights reserved.
 * @license    http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License Version 2 or Later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Filesystem\File;
use Joomla\Registry\Registry;

/**
 * Plugin class for Http Header
 *
 * @since  1.0
 */
class PlgSystemHttpHeader extends CMSPlugin
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
	 * @var    CMSApplication
	 * @since  1.0
	 */
	protected $app;

	/**
	 * The list of the suported HTTP headers
	 *
	 * @var    array
	 * @since  1.0
	 */
	protected $supportedHttpHeaders = [
		'strict-transport-security',
		'content-security-policy',
		'content-security-policy-report-only',
		'x-frame-options',
		'x-xss-protection',
		'x-content-type-options',
		'referrer-policy',
		'expect-ct',
		'feature-policy',
		'cross-origin-opener-policy',
		'permissions-policy',
	];

	/**
	 * The static header configuration as array
	 *
	 * @var    array
	 * @since  1.0.6
	 */
	protected $staticHeaderConfiguration = [];

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
		$this->setStaticHeaders();

		// Handle CSP Header configuration
		$cspOptions = (int) $this->params->get('contentsecuritypolicy', 0);

		if ($cspOptions)
		{
			$this->setCspHeader();
		}
	}

	/**
	 * Lets make sure the csp hashes are added to the csp header when enabled
	 *
	 * @return  void
	 *
	 * @since   1.0.7
	 */
	public function onAfterRender()
	{
		// CSP is only relevant on html pages. Let's early exit here.
		if (Factory::getDocument()->getType() != 'html')
		{
			return;
		}

		$scriptHashesEnabled = (int) $this->params->get('script_hashes_enabled', 0);
		$styleHashesEnabled  = (int) $this->params->get('style_hashes_enabled', 0);

		// Early exit when both options are disabled
		if (!$scriptHashesEnabled && !$styleHashesEnabled)
		{
			return;
		}

		// Make sure the getHeadData method exists
		if (!method_exists(Factory::getDocument(), 'getHeadData'))
		{
			return;
		}

		$headData      = Factory::getDocument()->getHeadData();
		$scriptHashes  = [];
		$styleHashes   = [];

		if ($scriptHashesEnabled)
		{
			// Generate the hashes for the script-src
			$inlineScripts = is_array($headData['script']) ? $headData['script'] : [];

			foreach ($inlineScripts as $type => $scriptContent)
			{
				$scriptHashes[] = "'sha256-" . base64_encode(hash('sha256', $scriptContent, true)) . "'";
			}
		}

		if ($styleHashesEnabled)
		{
			// Generate the hashes for the style-src
			$inlineStyles = is_array($headData['style']) ? $headData['style'] : [];

			foreach ($inlineStyles as $type => $styleContent)
			{
				$styleHashes[] = "'sha256-" . base64_encode(hash('sha256', $styleContent, true)) . "'";
			}
		}

		// Replace the hashes in the csp header when set.
		$headers = $this->app->getHeaders();

		foreach ($headers as $id => $headerConfiguration)
		{
			if (strtolower($headerConfiguration['name']) === 'content-security-policy'
				|| strtolower($headerConfiguration['name']) === 'content-security-policy-report-only')
			{
				$newHeaderValue = $headerConfiguration['value'];

				if (!empty($scriptHashes))
				{
					$newHeaderValue = str_replace('{script-hashes}', implode(' ', $scriptHashes), $newHeaderValue);
				}
				else
				{
					$newHeaderValue = str_replace('{script-hashes}', '', $newHeaderValue);
				}

				if (!empty($styleHashes))
				{
					$newHeaderValue = str_replace('{style-hashes}', implode(' ', $styleHashes), $newHeaderValue);
				}
				else
				{
					$newHeaderValue = str_replace('{style-hashes}', '', $newHeaderValue);
				}

				$this->app->setHeader($headerConfiguration['name'], $newHeaderValue, true);
			}
		}
	}

	/**
	 * Get the configured static headers.
	 *
	 * @return  array  We return the array of static headers with its values.
	 *
	 * @since   1.0.6
	 */
	private function getStaticHeaderConfiguration()
	{
		$staticHeaderConfiguration = [];

		// X-Frame-Options
		if ($this->params->get('xframeoptions'))
		{
			$staticHeaderConfiguration['X-Frame-Options#both'] = 'SAMEORIGIN';
		}

		// X-XSS-Protection
		if ($this->params->get('xxssprotection'))
		{
			$staticHeaderConfiguration['X-XSS-Protection#both'] = '1; mode=block';
		}

		// X-Content-Type-Options
		if ($this->params->get('xcontenttypeoptions'))
		{
			$staticHeaderConfiguration['X-Content-Type-Options#both'] = 'nosniff';
		}

		// Referrer-Policy
		$referrerPolicy = (string) $this->params->get('referrerpolicy', 'no-referrer-when-downgrade');

		if ($referrerPolicy !== 'disabled')
		{
			$staticHeaderConfiguration['Referrer-Policy#both'] = $referrerPolicy;
		}

		// Strict-Transport-Security
		$strictTransportSecurity = (int) $this->params->get('hsts', 0);

		if ($strictTransportSecurity)
		{
			$maxAge        = (int) $this->params->get('hsts_maxage', 31536000);
			$hstsOptions   = [];
			$hstsOptions[] = $maxAge < 300 ? 'max-age=300' : 'max-age=' . $maxAge;

			if ($this->params->get('hsts_subdomains', 0))
			{
				$hstsOptions[] = 'includeSubDomains';
			}

			if ($this->params->get('hsts_preload', 0))
			{
				$hstsOptions[] = 'preload';
			}

			$staticHeaderConfiguration['Strict-Transport-Security#both'] = implode('; ', $hstsOptions);
		}

		$additionalHttpHeaders = $this->params->get('additional_httpheader', []);

		foreach ($additionalHttpHeaders as $additionalHttpHeader)
		{
			if (empty($additionalHttpHeader->key) || empty($additionalHttpHeader->value))
			{
				continue;
			}

			if (!in_array(strtolower($additionalHttpHeader->key), $this->supportedHttpHeaders))
			{
				continue;
			}

			$staticHeaderConfiguration[$additionalHttpHeader->key . '#' . $additionalHttpHeader->client] = $additionalHttpHeader->value;
		}

		return $staticHeaderConfiguration;
	}

	/**
	 * Set the default headers when enabled
	 *
	 * @return  void
	 *
	 * @since   1.0
	 */
	private function setStaticHeaders()
	{
		$this->staticHeaderConfiguration = $this->getStaticHeaderConfiguration();

		if (empty($this->staticHeaderConfiguration))
		{
			return;
		}

		foreach ($this->staticHeaderConfiguration as $headerAndClient => $value)
		{
			$headerAndClient = explode('#', $headerAndClient);
			$header = $headerAndClient[0];
			$client = isset($headerAndClient[1]) ? $headerAndClient[1] : 'both';

			if (!$this->app->isClient($client) && $client != 'both')
			{
				continue;
			}

			$this->app->setHeader($header, $value, true);
		}
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
		$cspValues    = $this->params->get('contentsecuritypolicy_values', []);
		$cspReadOnly  = (int) $this->params->get('contentsecuritypolicy_report_only', 0);
		$csp          = $cspReadOnly === 0 ? 'Content-Security-Policy' : 'Content-Security-Policy-Report-Only';
		$newCspValues = [];

		$scriptHashesEnabled = (int) $this->params->get('script_hashes_enabled', 0);
		$styleHashesEnabled  = (int) $this->params->get('style_hashes_enabled', 0);

		foreach ($cspValues as $cspValue)
		{
			// Handle the client settings foreach header
			if (!$this->app->isClient($cspValue->client) && $cspValue->client != 'both')
			{
				continue;
			}

			// Append the script hashes placeholder
			if ($scriptHashesEnabled && strpos($cspValue->directive, 'script-src') === 0)
			{
				$cspValue->value .= ' {script-hashes}';
			}
			// Append the style hashes placeholder
			if ($styleHashesEnabled && strpos($cspValue->directive, 'style-src') === 0)
			{
				$cspValue->value .= ' {style-hashes}';
			}

			// We can only use this if this is a valid entry
			if (isset($cspValue->directive) && isset($cspValue->value))
			{
				$newCspValues[] = trim($cspValue->directive) . ' ' . trim($cspValue->value);
			}
		}

		// Add the xframeoptions directive to the CSP too when enabled
		if ($this->params->get('xframeoptions', 1) || $this->params->get('frame_ancestors_self_enabled', 1))
		{
			$newCspValues[] = "frame-ancestors 'self'";
		}

		if (empty($newCspValues))
		{
			return;
		}

		$this->app->setHeader($csp, implode('; ', $newCspValues));
	}
}
