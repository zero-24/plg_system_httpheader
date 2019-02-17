<?php
/**
 * HttpHeader Plugin
 *
 * @copyright  Copyright (C) 2017 - 2018 Tobias Zulauf All rights reserved.
 * @license    http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License Version 2 or Later
 */

defined('_JEXEC') or die;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Language\Text;
use Joomla\Registry\Registry;


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
	protected $supportedHttpHeaders = array(
		'strict-transport-security',
		'content-security-policy',
		'content-security-policy-report-only',
		'x-frame-options',
		'x-xss-protection',
		'x-content-type-options',
		'referrer-policy',
		'expect-ct',
		'feature-policy',
	);

	/**
	 * Defines the Server config file type none
	 *
	 * @var    string
	 * @since  1.0.6
	 */
	const SERVER_CONFIG_FILE_NONE = 'none';

	/**
	 * Defines the Server config file type htaccess
	 *
	 * @var    string
	 * @since  1.0.6
	 */
	const SERVER_CONFIG_FILE_HTACCESS = 'htaccess';

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
		// When the Updated extension is not plg_system_httpheader we don't do anything
		if ($context != $this->_name)
		{
			return;
		}

		// Get the new params saved by the plugin
		$pluginParams = new Registry($table->get('params'));

		// When the option is disabled we don't do anything here.
		if ($pluginParams->get('write_static_headers', 0) === 0)
		{
			return;
		}

		$serverConfigFile = $this->getServerConfigFile();

		if ($serverConfigFile === self::SERVER_CONFIG_FILE_NONE) // Constante
		{
			$this->app->enqueueMessage(Text::_('PLG_SYSTEM_HTTPHEADER_MESSAGE_STATICHEADERS_NOT_WRITTEN_NO_SERVER_CONFIGFILE_FOUND'), 'error');
			return;
		}

		// Get the StaticHeaderConfiguration
		$staticHeaderConfiguration = $this->getStaticHeaderConfiguration($pluginParams);

		// Write the static headers
		$result = $this->writeStaticHeaders($staticHeaderConfiguration);

		if ($result)
		{
			// Show messge that everything was done
			$this->app->enqueueMessage(Text::_('PLG_SYSTEM_HTTPHEADER_MESSAGE_STATICHEADERS_WRITTEN'), 'message');
			return;
		}

		// Something did not work tell them that and how to update themself ..
		$this->app->enqueueMessage(
			Text::_('PLG_SYSTEM_HTTPHEADER_MESSAGE_STATICHEADERS_NOT_WRITTEN',
			$this->getRulesForStaticHeaderConfiguration($staticHeaderConfiguration, $serverConfigFile)),
			'error'
		);
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
		if (file_exists($this->getPathTo(self::SERVER_CONFIG_FILE_HTACCESS)))
		{
			return self::SERVER_CONFIG_FILE_HTACCESS;
		}

		if (file_exists($this->getPathTo(self::SERVER_CONFIG_FILE_WEBCONFIG)))
		{
			return self::SERVER_CONFIG_FILE_WEBCONFIG;
		}

		return self::SERVER_CONFIG_FILE_NONE;
	}

	/**
	 * Return the path to the server config file we check
	 * 
	 * @param  string  $file  Constante pointing to the correct server config file or none
	 *
	 * @return  string  Expected path to the requested file; Or false on error
	 *
	 * @since   1.0.6
	 */
	private function getPathTo($file)
	{
		if (self::SERVER_CONFIG_FILE_HTACCESS === $file)
		{
			return JPATH_ROOT . '/' . '.htaccess';
		}

		if (self::SERVER_CONFIG_FILE_WEBCONFIG === $file)
		{
			return JPATH_ROOT . '/' . 'web.config';
		}

		return false;
	}

	/**
	 * Return the static Header Configuration based on the server config file
	 * 
	 * @param   array   Array of the static header configuration
	 * @param   string  Constant holding the server configuration file
	 * 
	 * @return  string  Buffer style text of the Header Configuration based on the server config file
	 *
	 * @since   1.0.6
	 */
	private function getRulesForStaticHeaderConfiguration($staticHeaderConfiguration, $serverConfigFile)
	{
		if ($serverConfigFile === self::SERVER_CONFIG_FILE_HTACCESS)
		{
			return $this->getHtaccessRulesForStaticHeaderConfiguration($staticHeaderConfiguration);
		}

		if ($serverConfigFile === self::SERVER_CONFIG_FILE_WEBCONFIG)
		{
			return $this->getWebConfigRulesForStaticHeaderConfiguration($staticHeaderConfiguration);
		}

		return false;
	}

	/**
	 * Return the static Header Configuration based in the .htaccess format
	 * 
	 * @param   array   Array of the static header configuration
	 * 
	 * @return  string  Buffer style text of the Header Configuration based on the server config file
	 *
	 * @since   1.0.6
	 */
	private function getHtaccessRulesForStaticHeaderConfiguration($staticHeaderConfiguration)
	{
		$htaccessBuffer = PHP_EOL;
		$htaccessBuffer .= '##############################################################' . PHP_EOL;
		$htaccessBuffer .= '### MANAGED BY PLG_SYSTEM_HTTPHEADER DO NOT MANUALLY EDIT! ###' . PHP_EOL;
		$htaccessBuffer .= '<IfModule mod_headers.c>' . PHP_EOL;

		foreach ($staticHeaderConfiguration as $header => $value)
		{
			$htaccessBuffer .= 'Header always set ' . $header . ' "' . $value . '"' . PHP_EOL;
		}

		$htaccessBuffer .= '</IfModule>' . PHP_EOL;
		$htaccessBuffer .= '### MANAGED BY PLG_SYSTEM_HTTPHEADER DO NOT MANUALLY EDIT! ###' . PHP_EOL;
		$htaccessBuffer .= '##############################################################' . PHP_EOL;
		$htaccessBuffer .= PHP_EOL;

		return $htaccessBuffer;
	}

	/**
	 * Return the static Header Configuration based in the web.config format
	 * 
	 * @param   array   Array of the static header configuration
	 * 
	 * @return  string  Buffer style text of the Header Configuration based on the server config file
	 *
	 * @since   1.0.6
	 */
	private function getWebConfigRulesForStaticHeaderConfiguration($staticHeaderConfiguration)
	{
		// Not implemented yet
	}

	/**
	 * Wirte the static headers.
	 *
	 * @param   array  The array containing the headers and values to write
	 * 
	 * @return  boolean  True on success; false on anny error
	 *
	 * @since   1.0.6
	 */
	private function writeStaticHeaders($staticHeaderConfiguration)
	{
		$targetFilePath = $this->getHtaccessVsWebConfigFilePath();
		$staticRulesContent = $this->getRulesForStaticHeaderConfiguration($staticHeaderConfiguration, $this->getServerConfigFile());

		if (file_exists($pathToHtaccess))
		{
			$staticRulesContent = $this->getHtaccessRulesForStaticHeaderConfiguration($staticHeaderConfiguration);
			$targetFilePath = $pathToHtaccess;
		}

		if (file_exists($pathToWebConfig))
		{
			$staticRulesContent = $this->getWebConfigRulesForStaticHeaderConfiguration($staticHeaderConfiguration);
			$targetFilePath     = $pathToWebConfig;
		}

		if (is_readable($targetFilePath) && !empty($staticRulesContent))
		{
			/**
			 * The Joomla Framework Filesystem Package does not support yet appending to an file.
			 * Please see https://github.com/joomla-framework/filesystem/pull/21 for an implementation
			 * When this is implemented we should use: Joomla/Filesystem/File::write($targetFilePath, $staticRulesContent, true);
			 */
			return \is_int(file_put_contents($targetFilePath, $staticRulesContent, FILE_APPEND));
		}
	}

	/**
	 * Get the configured static headers.
	 *
	 * @param   Registry  An Registry Object containing the plugin parameters
	 *
	 * @return  array  We return the array of static headers with its values.
	 *
	 * @since   1.0.6
	 */
	private function getStaticHeaderConfiguration($pluginParams = false)
	{
		$staticHeaderConfiguration = array();

		// Fallback to $this->params when no params has been passed
		if ($pluginParams === false)
		{
			$pluginParams = $this->params;
		}

		// X-Frame-Options
		$xFrameOptions = $pluginParams->get('xframeoptions', 1);

		if ($xFrameOptions)
		{
			$staticHeaderConfiguration['X-Frame-Options' . '#both'] = 'SAMEORIGIN';
		}

		// X-XSS-Protection
		$xXssProtection = $pluginParams->get('xxssprotection', 1);

		if ($xXssProtection)
		{
			$staticHeaderConfiguration['X-XSS-Protection' . '#both'] = '1; mode=block';
		}

		// X-Content-Type-Options
		$xContentTypeOptions = $pluginParams->get('xcontenttypeoptions', 1);
		
		if ($xContentTypeOptions)
		{
			$staticHeaderConfiguration['X-Content-Type-Options' . '#both'] = 'nosniff';
		}

		// Referrer-Policy
		$referrerpolicy = (string) $pluginParams->get('referrerpolicy', 'no-referrer-when-downgrade');

		if ($referrerpolicy !== 'disabled')
		{
			$staticHeaderConfiguration['Referrer-Policy' . '#both'] = $referrerpolicy;
		}

		// Strict-Transport-Security
		$stricttransportsecurity = (int) $pluginParams->get('hsts', 0);

		if ($stricttransportsecurity)
		{
			$maxAge        = (int) $pluginParams->get('hsts_maxage', 31536000);
			$hstsOptions   = array();
			$hstsOptions[] = $maxAge < 300 ? 'max-age=300' : 'max-age=' . $maxAge;

			if ($pluginParams->get('hsts_subdomains', 0))
			{
				$hstsOptions[] = 'includeSubDomains';
			}

			if ($pluginParams->get('hsts_preload', 0))
			{
				$hstsOptions[] = 'preload';
			}

			$staticHeaderConfiguration['Strict-Transport-Security' . '#both'] = implode('; ', $hstsOptions);
		}

		$additionalHttpHeaders = $pluginParams->get('additional_httpheader', array());

		foreach ($httpHeaders as $httpHeader)
		{
			if (empty($httpHeader->key) || empty($httpHeader->value))
			{
				continue;
			}

			if (!in_array(strtolower($httpHeader->key), $this->supportedHttpHeaders))
			{
				continue;
			}

			$staticHeaderConfiguration[$httpHeader->key . '#' . $httpHeader->client] = $httpHeader->value;
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
