<?php
/**
 * Mautic-Joomla plugin
 * @author		Mautic
 * @copyright	Copyright (C) 2014 Mautic All Rights Reserved.
 * @license		http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 * Website		http://www.mautic.org
 */

// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

jimport( 'joomla.plugin.plugin' );

// Include the MauticApi file which handles the API class autoloading
require_once __DIR__ . '/lib/Mautic/MauticApi.php';

require_once __DIR__ . '/mauticApiHelper.php';

/**
 *
 * @package		Joomla
 * @subpackage	System.Mautic
 */
class plgSystemMautic extends JPlugin
{
	/**
     * mauticApiHelper
     */
	protected $apiHelper;

	/**
	 * This event is triggered after the framework has loaded and the application initialise method has been called.
	 *
	 * @return	void
	 */
	public function onAfterDispatch()
	{
		$app		= JFactory::getApplication();
		$document	= JFactory::getDocument();
		$input		= $app->input;

		// Check to make sure we are loading an HTML view and there is a main component area
		if ($document->getType() !== 'html' || $input->get('tmpl', '', 'cmd') === 'component' || $app->isAdmin())
		{
			return true;
		}

		// Get additional data to send
		$attrs = array();
		$attrs['title']		= $document->title;
		$attrs['language']	= $document->language;
		$attrs['referrer']	= isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : JUri::current();
		$attrs['url'] 		= JURI::getInstance()->toString();

		$user = JFactory::getUser();

		// Get info about the user if logged in
		if (!$user->guest)
		{
			$attrs['email'] = $user->email;

			$name = explode(' ', $user->name);

			if (isset($name[0]))
			{
				$attrs['firstname'] = $name[0];
			}

			if (isset($name[count($name) - 1]))
			{
				$attrs['lastname'] = $name[count($name) - 1];
			}
		}

		$encodedAttrs = urlencode(base64_encode(serialize($attrs)));

		$buffer		= $document->getBuffer('component');
		$image		= '<img style="display:none" src="' . trim($this->params->get('base_url'), ' \t\n\r\0\x0B/') . '/mtracking.gif?d=' . $encodedAttrs . '" />';
		$buffer		.= $image;
		
		$document->setBuffer($buffer, 'component');

		return true;
	}

	/**
	 * Insert form script to the content
	 *
	 * @param	string	The context of the content being passed to the plugin.
	 * @param	object	The article object.  Note $article->text is also available
	 * @param	object	The article params
	 * @param	integer	The 'page' number
	 */
	public function onContentPrepare($context, &$article, &$params, $page = 0)
	{
		$app		= JFactory::getApplication();
		$document	= JFactory::getDocument();
		$input		= $app->input;

		// Check to make sure we are loading an HTML view and there is a main component area and content is not being indexed
		if ($document->getType() !== 'html' 
			|| $input->get('tmpl', '', 'cmd') === 'component' 
			|| $app->isAdmin() 
			|| $context == 'com_finder.indexer')
		{
			return true;
		}

		// simple performance check to determine whether bot should process further
		if (strpos($article->text, '{mauticform') === false)
		{
			return true;
		}

		// expression to search for (positions)
		$regex = '/{mauticform\s+(.*?)}/i';

		// Find all instances of plugin and put in $matches for githubrepo
		// $matches[0] is full pattern match, $matches[1] is the repo declaration
		preg_match_all($regex, $article->text, $matches, PREG_SET_ORDER);

		if ($matches && isset($matches[0]))
		{
			foreach ($matches as $match)
			{
				if (isset($match[1]))
				{
					$formId = (int) $match[1];
					$formTag = '<script type="text/javascript" src="' . trim($this->params->get('base_url'), ' \t\n\r\0\x0B/') . '/form/generate.js?id=' . $formId . '"></script>';
					$article->text = str_replace($match[0], $formTag, $article->text);
				}
			}
		}
	}

	/**
	* Mautic API call
	*/
	public function onAfterRoute()
	{
		$app = JFactory::getApplication();
		$input = $app->input;
		
		if ($input->get('plugin') == 'mautic' || ($input->get('oauth_token') && $input->get('oauth_verifier')))
		{
			$this->authorize($input->get('reauthorize', false, 'BOOLEAN'));
		}
	}

	/**
	 * Create sanitized Mautic Base URL without the slash at the end.
	 * 
	 * @return string
	 */
	public function getMauticApiHelper()
	{
		if ($this->apiHelper)
		{
			return $this->apiHelper;
		}

		$this->apiHelper = new mauticApiHelper;

		return $this->apiHelper;
	}

	/**
	 * Get Table instance of this plugin
	 * 
	 * @return JTableExtension
	 */
	public function authorize($reauthorize = false)
	{
		$app = JFactory::getApplication();
		$re = '';

		// Onlu admin can authorize
		$user = JFactory::getUser();
		$isRoot = $user->authorise('core.admin');

		if (!$isRoot)
		{
			die('Only admin can authorize Mautic API application');
		}

		$apiHelper		= $this->getMauticApiHelper();
		$mauticBaseUrl	= $apiHelper->getMauticBaseUrl();
		$auth			= $apiHelper->getMauticAuth($reauthorize);
		$table			= $apiHelper->getTable();

		if ($reauthorize)
		{
			$re = 're';
		}

		if ($auth->validateAccessToken())
		{
			if ($auth->accessTokenUpdated())
			{
				$accessTokenData = new JRegistry($auth->getAccessTokenData());

				$this->params->merge($accessTokenData);
				$table = $apiHelper->getTable();
				$table->set('params', $this->params->toString());
				$table->store();
				$app->enqueueMessage('Mautic plugin was successfully ' . $re . 'authorized against Mautic.');
			}
			else
			{
				$app->enqueueMessage('Mautic plugin was not need to authorize. It already is authorized.');
			}
		}

		$app->redirect(JURI::root() . 'administrator/index.php?option=com_plugins&view=plugin&layout=edit&extension_id=' . $table->get('extension_id'));
	}

	/**
	 * Create new lead on Joomla user registration
	 * 
	 * For debug is better to switch function to:
	 * public function onUserBeforeSave($success, $isNew, $user)
	 * 
	 * @param array 	$user 		array with user information
	 * @param boolean 	$isNew 		whether the user is new
	 * @param boolean 	$success 	whether the user was saved successfully
	 * @param string 	$msg 		error message
	 */
	public function onUserAfterSave($user, $isNew, $success, $msg = '')
	{
		if ($isNew && $success)
		{
			$apiHelper		= $this->getMauticApiHelper();
			$mauticBaseUrl	= $apiHelper->getMauticBaseUrl();
			$auth			= $apiHelper->getMauticAuth();
			$leadApi		= \Mautic\MauticApi::getContext("leads", $auth, $mauticBaseUrl . '/api/');
			$ip				= $this->getUserIP();
			$name			= explode(' ', $user['name']);
			
			$mauticUser = array(
				'ipAddress' => $ip,
				'firstname' => isset($name[0]) ? $name[0] : '',
				'lastname' => isset($name[1]) ? $name[1] : '',
				'lastname' => $user['email'],
			);

			$lead = $leadApi->create($mauticUser);
		}
	}

	/**
	 * Try to guess the real user IP
	 * 
	 * @return	string
	 */
	public function getUserIP()
	{
		$ip = '';

		if (!empty($_SERVER['HTTP_CLIENT_IP']))
		{
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		}
		elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))
		{
			$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		}
		elseif (!empty($_SERVER['REMOTE_ADDR']))
		{
			$ip = $_SERVER['REMOTE_ADDR'];
		}

		return $ip;
	}
}