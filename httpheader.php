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
	];

	/**
	 * The static header configuration as array
	 *
	 * @var    array
	 * @since  1.0.6
	 */
	protected $staticHeaderConfiguration = [];

	/**
	 * Defines the Server config file type none
	 *
	 * @var    string
	 * @since  1.0.6
	 */
	const SERVER_CONFIG_FILE_NONE = '';

	/**
	 * Defines the Server config file type htaccess
	 *
	 * @var    string
	 * @since  1.0.6
	 */
	const SERVER_CONFIG_FILE_HTACCESS = '.htaccess';

	/**
	 * Defines the Server config file type web.config
	 *
	 * @var    string
	 * @since  1.0.6
	 */
	const SERVER_CONFIG_FILE_WEBCONFIG = 'web.config';

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
	 * On saving this plugin we may want to generate the htaccess with the latest static headers
	 *
	 * @param   string   $context  The extension
	 * @param   JTable   $table    Database Table object
	 * @param   boolean  $isNew    If the extension is new or not
	 *
	 * @return  void
	 *
	 * @since   1.0.6
	 */
	public function onExtensionAfterSave($context, $table, $isNew)
	{
		// When the updated extension is not plg_system_httpheader we don't do anything
		if ($table->element != $this->_name || $table->folder != $this->_type)
		{
			return;
		}

		// Get the new params saved by the plugin
		$pluginParams = new Registry($table->get('params'));

		// When the option is disabled we don't do anything here.
		if (!$pluginParams->get('write_static_headers', 0))
		{
			return;
		}

		$serverConfigFile = $this->getServerConfigFile();

		if (!$serverConfigFile)
		{
			$this->app->enqueueMessage(
				Text::_('PLG_SYSTEM_HTTPHEADER_MESSAGE_STATICHEADERS_NOT_WRITTEN_NO_SERVER_CONFIGFILE_FOUND'),
				'warning'
			);

			return;
		}

		// Get the StaticHeaderConfiguration
		$this->staticHeaderConfiguration = $this->getStaticHeaderConfiguration($pluginParams);

		// Write the static headers
		$result = $this->writeStaticHeaders();

		if (!$result)
		{
			// Something did not work tell them that and how to update themself.
			$this->app->enqueueMessage(
				Text::sprintf(
					'PLG_SYSTEM_HTTPHEADER_MESSAGE_STATICHEADERS_NOT_WRITTEN',
					$serverConfigFile,
					$this->getRulesForStaticHeaderConfiguration($serverConfigFile)
				),
				'error'
			);

			return;
		}

		// Show messge that everything was done
		$this->app->enqueueMessage(
			Text::sprintf(
				'PLG_SYSTEM_HTTPHEADER_MESSAGE_STATICHEADERS_WRITTEN',
				$serverConfigFile
			),
			'message'
		);
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
		$scriptHashesEnabled = (int) $this->params->get('script_hashes_enabled', 0);
		$styleHashesEnabled  = (int) $this->params->get('style_hashes_enabled', 0);
		$headData            = Factory::getDocument()->getHeadData();
		$scriptHashes        = [];
		$styleHashes         = [];

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
	 * Return the server config file constant
	 *
	 * @return  string  Constante pointing to the correct server config file or none
	 *
	 * @since   1.0.6
	 */
	private function getServerConfigFile()
	{
		if (file_exists($this->getServerConfigFilePath(self::SERVER_CONFIG_FILE_HTACCESS))
			&& substr(strtolower($_SERVER['SERVER_SOFTWARE']), 0, 6) === 'apache')
		{
			return self::SERVER_CONFIG_FILE_HTACCESS;
		}

		// We are not on an apache so lets just check whether the web.config file exits
		if (file_exists($this->getServerConfigFilePath(self::SERVER_CONFIG_FILE_WEBCONFIG)))
		{
			return self::SERVER_CONFIG_FILE_WEBCONFIG;
		}

		return self::SERVER_CONFIG_FILE_NONE;
	}

	/**
	 * Return the path to the server config file we check
	 *
	 * @param   string  $file  Constante pointing to the correct server config file or none
	 *
	 * @return  string  Expected path to the requested file; Or false on error
	 *
	 * @since   1.0.6
	 */
	private function getServerConfigFilePath($file)
	{
		return JPATH_ROOT . DIRECTORY_SEPARATOR . $file;
	}

	/**
	 * Return the static Header Configuration based on the server config file
	 *
	 * @param   string  $serverConfigFile  Constant holding the server configuration file
	 *
	 * @return  string  Buffer style text of the Header Configuration based on the server config file
	 *
	 * @since   1.0.6
	 */
	private function getRulesForStaticHeaderConfiguration($serverConfigFile)
	{
		if ($serverConfigFile === self::SERVER_CONFIG_FILE_HTACCESS)
		{
			return $this->getHtaccessRulesForStaticHeaderConfiguration();
		}

		if ($serverConfigFile === self::SERVER_CONFIG_FILE_WEBCONFIG)
		{
			return $this->getWebConfigRulesForStaticHeaderConfiguration();
		}

		return false;
	}

	/**
	 * Return the static Header Configuration based in the .htaccess format
	 *
	 * @return  string  Buffer style text of the Header Configuration based on the server config file; empty string on error
	 *
	 * @since   1.0.6
	 */
	private function getHtaccessRulesForStaticHeaderConfiguration()
	{
		$oldHtaccessBuffer = file($this->getServerConfigFilePath(self::SERVER_CONFIG_FILE_HTACCESS), FILE_IGNORE_NEW_LINES);
		$newHtaccessBuffer = '';

		if (!$oldHtaccessBuffer)
		{
			// `file` couldn't read the htaccess we can't do anything at this point
			return '';
		}

		$scriptLines = false;

		foreach ($oldHtaccessBuffer as $id => $line)
		{
			if ($line === '### MANAGED BY PLG_SYSTEM_HTTPHEADER DO NOT MANUALLY EDIT! - START ###')
			{
				$scriptLines = true;
				continue;
			}

			if ($line === '### MANAGED BY PLG_SYSTEM_HTTPHEADER DO NOT MANUALLY EDIT! - END ###'
				|| $line === '##############################################################')
			{
				$scriptLines = false;
				continue;
			}

			if ($scriptLines)
			{
				// When we are between our makers all content should be removed
				continue;
			}

			$newHtaccessBuffer .= $line . PHP_EOL;
		}

		$newHtaccessBuffer .= '##############################################################' . PHP_EOL;
		$newHtaccessBuffer .= '### MANAGED BY PLG_SYSTEM_HTTPHEADER DO NOT MANUALLY EDIT! - START ###' . PHP_EOL;
		$newHtaccessBuffer .= '<IfModule mod_headers.c>' . PHP_EOL;

		foreach ($this->staticHeaderConfiguration as $headerAndClient => $value)
		{
			$headerAndClient = explode('#', $headerAndClient);

			// Make sure csp headers are not added to the server config file as they could include non static elements
			if (!in_array(strtolower($headerAndClient[0]), ['content-security-policy', 'content-security-policy-report-only']) && $headerAndClient[1] === 'both')
			{
				$newHtaccessBuffer .= '    Header set ' . $headerAndClient[0] . ' "' . $value . '"' . PHP_EOL;
			}
		}

		$newHtaccessBuffer .= '</IfModule>' . PHP_EOL;
		$newHtaccessBuffer .= '### MANAGED BY PLG_SYSTEM_HTTPHEADER DO NOT MANUALLY EDIT! - END ###' . PHP_EOL;
		$newHtaccessBuffer .= '##############################################################' . PHP_EOL;
		$newHtaccessBuffer .= PHP_EOL;

		return $newHtaccessBuffer;
	}

	/**
	 * Return the static Header Configuration based in the web.config format
	 *
	 * @return  string|boolean  Buffer style text of the Header Configuration based on the server config file or false on error.
	 *
	 * @since   1.0.6
	 */
	private function getWebConfigRulesForStaticHeaderConfiguration()
	{
		$webConfigDomDoc = new DOMDocument('1.0', 'UTF-8');

		// We want a nice output
		$webConfigDomDoc->formatOutput = true;
		$webConfigDomDoc->preserveWhiteSpace = false;

		// Load the current file into our object
		$webConfigDomDoc->load($this->getServerConfigFilePath(self::SERVER_CONFIG_FILE_WEBCONFIG));

		// Get an DOMXPath Object mathching our file
		$xpath = new DOMXPath($webConfigDomDoc);

		// We require an correct tree containing an system.webServer node!
		$systemWebServer = $xpath->query("/configuration/location/system.webServer");

		if ($systemWebServer->length === 0 || $systemWebServer->length > 1)
		{
			// There is only one (or none)
			return false;
		}

		// Check what configurations exists already
		$httpProtocol  = $xpath->query("/configuration/location/system.webServer/httpProtocol");
		$customHeaders = $xpath->query("/configuration/location/system.webServer/httpProtocol/customHeaders");

		// Does the httpProtocol node exist?
		if ($httpProtocol->length === 0)
		{
			$newHttpProtocol = $webConfigDomDoc->createElement('httpProtocol');
			$newCustomHeaders = $webConfigDomDoc->createElement('customHeaders');

			foreach ($this->staticHeaderConfiguration as $headerAndClient => $value)
			{
				$headerAndClient = explode('#', $headerAndClient);

				// Make sure csp headers are not added to the server config file as they could include non static elements
				if (!in_array(strtolower($headerAndClient[0]), ['content-security-policy', 'content-security-policy-report-only']) && $headerAndClient[1] === 'both')
				{
					$newHeader = $webConfigDomDoc->createElement('add');

					$newHeader->setAttribute('name', $headerAndClient[0]);
					$newHeader->setAttribute('value', $value);
					$newCustomHeaders->appendChild($newHeader);
				}
			}

			$newHttpProtocol->appendChild($newCustomHeaders);
			$systemWebServer[0]->appendChild($newHttpProtocol);
		}
		// It seams there are a httpProtocol node so does the customHeaders node exist?
		elseif ($customHeaders->length === 0)
		{
			$newCustomHeaders = $webConfigDomDoc->createElement('customHeaders');

			foreach ($this->staticHeaderConfiguration as $headerAndClient => $value)
			{
				$headerAndClient = explode('#', $headerAndClient);

				// Make sure csp headers are not added to the server config file as they could include non static elements
				if (!in_array(strtolower($headerAndClient[0]), ['content-security-policy', 'content-security-policy-report-only']))
				{
					$newHeader = $webConfigDomDoc->createElement('add');

					$newHeader->setAttribute('name', $headerAndClient[0]);
					$newHeader->setAttribute('value', $value);
					$newCustomHeaders->appendChild($newHeader);
				}
			}

			$httpProtocol[0]->appendChild($newCustomHeaders);
		}
		// Well It seams httpProtocol and customHeaders exists lets check now the individual header (add) nodes
		else
		{
			$oldCustomHeaders = $xpath->query("/configuration/location/system.webServer/httpProtocol/customHeaders/add");

			// Here we check all headers actually exists with the correct value
			foreach ($this->staticHeaderConfiguration as $headerAndClient => $value)
			{
				$headerAndClient = explode('#', $headerAndClient);

				// When no headers exitsts at all we can't find anything :D
				if ($oldCustomHeaders->length === 0)
				{
					$found = false;
				}

				// Check if the header is currently set or not
				foreach ($oldCustomHeaders as $oldCustomHeader)
				{
					$found = false;
					$customHeadersName = $oldCustomHeader->getAttribute('name');

					if ($headerAndClient[0] === $customHeadersName)
					{
						// We found it, well done.
						$found = true;
						break;
					}
				}

				// The header wasn't found we need to create it
				if (!$found)
				{
					// Make sure csp headers are not added to the server config file as they could include non static elements
					if (!in_array(strtolower($headerAndClient[0]), ['content-security-policy', 'content-security-policy-report-only']))
					{
						// Generate the new header Element
						$newHeader = $webConfigDomDoc->createElement('add');
						$newHeader->setAttribute('name', $headerAndClient[0]);
						$newHeader->setAttribute('value', $value);

						// Append the new header
						$customHeaders[0]->appendChild($newHeader);
					}
				}

				$customHeadersValue = $oldCustomHeader->getAttribute('value');

				if ($value === $customHeadersValue)
				{
					continue;
				}

				$oldCustomHeader->setAttribute('value', $value);
			}
		}

		return $webConfigDomDoc->saveXML();
	}

	/**
	 * Wirte the static headers.
	 *
	 * @return  boolean  True on success; false on any error
	 *
	 * @since   1.0.6
	 */
	private function writeStaticHeaders()
	{
		$pathToHtaccess  = $this->getServerConfigFilePath(self::SERVER_CONFIG_FILE_HTACCESS);
		$pathToWebConfig = $this->getServerConfigFilePath(self::SERVER_CONFIG_FILE_WEBCONFIG);

		if (file_exists($pathToHtaccess))
		{
			$htaccessContent = $this->getHtaccessRulesForStaticHeaderConfiguration();

			if (is_readable($pathToHtaccess) && !empty($htaccessContent))
			{
				// Write the htaccess using the Frameworks File Class
				return File::write($pathToHtaccess, $htaccessContent);
			}
		}

		if (file_exists($pathToWebConfig))
		{
			$webConfigContent = $this->getWebConfigRulesForStaticHeaderConfiguration();

			if (is_readable($pathToWebConfig) && !empty($webConfigContent))
			{
				// Setup and than write the web.config write using DOMDocument
				$webConfigDomDoc = new DOMDocument;
				$webConfigDomDoc->formatOutput = true;
				$webConfigDomDoc->preserveWhiteSpace = false;
				$webConfigDomDoc->loadXML($webConfigContent);

				// When the return code is an integer we got the bytes and everything went well if not something broke..
				return is_integer($webConfigDomDoc->save($pathToWebConfig)) ? true : false;
			}
		}
	}

	/**
	 * Get the configured static headers.
	 *
	 * @param   Registry  $pluginParams  An Registry Object containing the plugin parameters
	 *
	 * @return  array  We return the array of static headers with its values.
	 *
	 * @since   1.0.6
	 */
	private function getStaticHeaderConfiguration($pluginParams = false)
	{
		$staticHeaderConfiguration = [];

		// Fallback to $this->params when no params has been passed
		if ($pluginParams === false)
		{
			$pluginParams = $this->params;
		}

		// X-Frame-Options
		if ($pluginParams->get('xframeoptions'))
		{
			$staticHeaderConfiguration['X-Frame-Options#both'] = 'SAMEORIGIN';
		}

		// X-XSS-Protection
		if ($pluginParams->get('xxssprotection'))
		{
			$staticHeaderConfiguration['X-XSS-Protection#both'] = '1; mode=block';
		}

		// X-Content-Type-Options
		if ($pluginParams->get('xcontenttypeoptions'))
		{
			$staticHeaderConfiguration['X-Content-Type-Options#both'] = 'nosniff';
		}

		// Referrer-Policy
		$referrerPolicy = (string) $pluginParams->get('referrerpolicy', 'no-referrer-when-downgrade');

		if ($referrerPolicy !== 'disabled')
		{
			$staticHeaderConfiguration['Referrer-Policy#both'] = $referrerPolicy;
		}

		// Strict-Transport-Security
		$strictTransportSecurity = (int) $pluginParams->get('hsts', 0);

		if ($strictTransportSecurity)
		{
			$maxAge        = (int) $pluginParams->get('hsts_maxage', 31536000);
			$hstsOptions   = [];
			$hstsOptions[] = $maxAge < 300 ? 'max-age=300' : 'max-age=' . $maxAge;

			if ($pluginParams->get('hsts_subdomains', 0))
			{
				$hstsOptions[] = 'includeSubDomains';
			}

			if ($pluginParams->get('hsts_preload', 0))
			{
				$hstsOptions[] = 'preload';
			}

			$staticHeaderConfiguration['Strict-Transport-Security#both'] = implode('; ', $hstsOptions);
		}

		$additionalHttpHeaders = $pluginParams->get('additional_httpheader', []);

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
		$this->staticHeaderConfiguration = $this->getStaticHeaderConfiguration($this->params);

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
		if ($this->params->get('xframeoptions'))
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
