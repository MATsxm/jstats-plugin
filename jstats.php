<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.stats
 *
 * @copyright   Copyright (C) 2005 - 2015 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

jimport('joomla.filesystem.file');

// Uncomment the following line to enable debug mode (stats sent every single time)
// define('PLG_SYSTEM_JSTATS_DEBUG', 1);

/**
 * Statistics system plugin
 *
 * @since  3.5
 */
class PlgSystemJstats extends JPlugin
{
 	/**
	 * Application object
	 *
	 * @var    JApplicationCms
	 * @since  3.5
	 */
	protected $app;

	/**
	 * Path to the cache file
	 *
	 * @var    string
	 * @since  3.5
	 */
	protected $cacheFile;

	/**
	 * Database object
	 *
	 * @var    JDatabaseDriver
	 * @since  3.5
	 */
	protected $db;

	/**
	 * Constructor
	 *
	 * @param   object  &$subject  The object to observe
	 * @param   array   $config    An optional associative array of configuration settings.
	 *
	 * @since   3.5
	 */
	public function __construct(&$subject, $config = array())
	{
		$this->cacheFile = JPATH_ROOT . '/cache/jstats.php';

		parent::__construct($subject, $config);
	}

	/**
	 * Listener for the `onAfterInitialise` event
	 *
	 * @return  void
	 *
	 * @since   3.5
	 */
	public function onAfterInitialise()
	{
		// Only run this in admin
		if (!$this->app->isAdmin())
		{
			return;
		}

		// Do we need to run? Compare the last run timestamp stored in the plugin's options with the current
		// timestamp. If the difference is greater than the cache timeout we shall not execute again.
		$now  = time();
		$last = (int) $this->params->get('lastrun', 0);

		// 12 hours - 60*60*12 = 43200
		if (!defined('PLG_SYSTEM_JSTATS_DEBUG') && (abs($now - $last) < 43200))
		{
			return;
		}

		// Update last run status
		$this->params->set('lastrun', $now);

		$uniqueId = $this->params->get('unique_id', '');

		/*
		 * If the unique ID is empty (because we have never submitted a piece of data before or because the refresh button
		 * has been used - generate a new ID and store it in the database for future use.
		 */
		if (empty($uniqueId))
		{
			$this->params->set('unique_id', JCrypt::genRandomBytes(32));
		}

		$query = $this->db->getQuery(true)
			->update($this->db->quoteName('#__extensions'))
			->set($this->db->qn('params') . ' = ' . $this->db->quote($this->params->toString('JSON')))
			->where($this->db->quoteName('type') . ' = ' . $this->db->quote('plugin'))
			->where($this->db->quoteName('folder') . ' = ' . $this->db->quote('system'))
			->where($this->db->quoteName('element') . ' = ' . $this->db->quote('updatenotification'));

		try
		{
			// Lock the tables to prevent multiple plugin executions causing a race condition
			$this->db->lockTable('#__extensions');
		}
		catch (Exception $e)
		{
			// If we can't lock the tables it's too risky to continue execution
			return;
		}

		try
		{
			// Update the plugin parameters
			$result = $this->db->setQuery($query)->execute();

			$this->clearCacheGroups(array('com_plugins'), array(0, 1));
		}
		catch (Exception $exc)
		{
			// If we failed to execute
			$this->db->unlockTables();
			$result = false;
		}

		try
		{
			// Unlock the tables after writing
			$this->db->unlockTables();
		}
		catch (Exception $e)
		{
			// If we can't lock the tables assume we have somehow failed
			$result = false;
		}

		// Abort on failure
		if (!$result)
		{
			return;
		}

		$data = array(
			'unique_id'   => $uniqueId,
			'php_version' => PHP_VERSION,
			'db_type'     => $this->db->name,
			'db_version'  => $this->db->getVersion(),
			'cms_version' => JVERSION,
			'server_os'   => php_uname('s') . ' ' . php_uname('r')
		);

		try
		{
			// Don't let the request take longer than 2 seconds to avoid page timeout issues
			JHttpFactory::getHttp()->post($this->params->get('url', 'https://developer.joomla.org/stats/submit'), $data, null, 2);
		}
		catch (UnexpectedValueException $e)
		{
			// There was an error sending stats. Should we do anything?
			JLog::add('Could not send site statistics to remote server: ' . $e->getMessage(), JLog::WARNING, 'stats');
		}
		catch (RuntimeException $e)
		{
			// There was an error connecting to the server or in the post request
			JLog::add('Could not connect to statistics server: ' . $e->getMessage(), JLog::WARNING, 'stats');
		}
		catch (Exception $e)
		{
			// An unexpected error in processing; don't let this failure kill the site
			JLog::add('Unexpected error connecting to statistics server: ' . $e->getMessage(), JLog::WARNING, 'stats');
		}
	}
}
