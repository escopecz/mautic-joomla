<?php
/**
 * Mautic-Joomla plugin
 * @author	  Mautic
 * @copyright   Copyright (C) 2014 Mautic All Rights Reserved.
 * @license	 http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 * Website	  http://www.mautic.org
 */

// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

require_once __DIR__ . '/../mauticApiHelper.php';

/**
 * Form Field class for the Mautic-Joomla plugin.
 * Provides a debug info.
 */
class JFormFieldDebug extends JFormField
{

	/**
	 * The form field type.
	 *
	 * @var string
	 */
	protected $type = 'debug';

	/**
	 * Display debug info
	 *
	 * @return  string  The field input markup.
	 *
	 * @since   11.1
	 */
	protected function getLabel()
	{
		$apiHelper  = new mauticApiHelper;
		$params	    = $apiHelper->getPluginParams();

		if ($params->get('debug_on'))
		{
			return parent::getLabel();
		}
	}

	/**
	 * Display debug info
	 *
	 * @return  string  The field input markup.
	 *
	 * @since   11.1
	 */
	protected function getInput()
	{
		$apiHelper = new mauticApiHelper;
		$settings = $apiHelper->getApiSettings();
		$params	 = $apiHelper->getPluginParams();
		$debug = '';
		$config = JFactory::getConfig();
		$logPath = $config->get('log_path') . '/plg_mautic.php';
		$log = file_exists($logPath) && is_readable($logPath) ? file($logPath) : null;
		$recentLog = is_array($log) ? array_slice($log, -30) : null;

		if ($params->get('debug_on'))
		{
			$debug = '<fieldset>';
			$debug .= '<h3>' . JText::_('PLG_MAUTIC_RECENT_LOG') . ' <small>(' . $logPath . ')</small></h3>';
			$debug .= '<pre>';
			$debug .= is_array($log) ? implode('', array_reverse($recentLog)) : JText::_('PLG_MAUTIC_LOG_NOT_AVAILABLE');
			$debug .= '</pre>';
			$debug .= '<h3>' . JText::_('PLG_MAUTIC_OAUTH_SETTINGS') . '</h3>';
			$debug .= '<pre>';
			$debug .= var_export($settings, true);
			$debug .= '</pre></fieldset>';

			unset($_SESSION['mautic_oauth_message']);

			return $debug;
		}
	}
}
