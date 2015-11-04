<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.stats
 *
 * @copyright   Copyright (C) 2005 - 2015 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

jimport('joomla.filesystem.file');

/**
 * Statistics system plugin
 *
 * @since  3.5
 */
class PlgSystemJstats extends JPlugin
{
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
	 * @param   object &$subject The object to observe
	 * @param   array  $config   An optional associative array of configuration settings.
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
		if (is_readable($this->cacheFile))
		{
			/** @var integer $checkedTime */
			$checkedTime = include $this->cacheFile;

			if ($checkedTime < strtotime('-12 hours'))
			{
				$this->sendStats();
			}
		}
		else
		{
			$this->sendStats();
		}
	}

	/**
	 * Send the system statistics to the remote server
	 *
	 * @return  void
	 *
	 * @since   3.5
	 */
	private function sendStats()
	{
		$http = JHttpFactory::getHttp();

		$data = array(
			'unique_id'   => $this->params->get('unique_id'),
			'php_version' => PHP_VERSION,
			'db_type'     => $this->db->name,
			'db_version'  => $this->db->getVersion(),
			'cms_version' => JVERSION,
			'server_os'   => php_uname('s') . ' ' . php_uname('r')
		);

		$uri = new JUri($this->params->get('url', 'https://developer.joomla.org/stats/submit'));

		try
		{
			// Don't let the request take longer than 2 seconds to avoid page timeout issues
			$status = $http->post($uri, $data, null, 2);

			if ($status->code === 200)
			{
				$this->writeCacheFile();
			}
		}
		catch (UnexpectedValueException $e)
		{
			// There was an error sending stats. Should we do anything?
			JLog::add($e->getMessage(), JLog::WARNING, 'stats');
		}
		catch (RuntimeException $e)
		{
			// There was an error connecting to the server or in the post request
			JLog::add($e->getMessage(), JLog::WARNING, 'stats');
		}
		catch (Exception $e)
		{
			// An unexpected error in processing; don't let this failure kill the site
			JLog::add($e->getMessage(), JLog::WARNING, 'stats');
		}
	}
​
	/**
	 * Write the cache file
	 *
	 * @return  void
	 *
	 * @since   3.5
	 */
	private function writeCacheFile()
	{
		if (is_readable($this->cacheFile))
		{
			JFile::delete($this->cacheFile);
		}

		$now = time();

		$php = <<<PHP
<?php defined('_JEXEC') or die;

return $now;
PHP;

		JFile::write($this->cacheFile, $php);
	}
}
