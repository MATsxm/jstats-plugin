<?php
/**
 * @copyright   Copyright (C) 2013 Don Gilbert. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Class plgSystemJstatsInstallerScript
 */
class plgSystemJstatsInstallerScript
{
	/**
	 * Enable the plugin and set default params.
	 *
	 * @param $type
	 * @param $parent
	 *
	 */
	public function postflight($type, $parent)
	{
		$this->removeCacheFile();
	}

	public function uninstall($parent)
	{
		$this->removeCacheFile();
	}

	/**
	 * Remove the cache file on uninstall and upgrade.
	 *
	 * @since 1.0
	 */
	protected function removeCacheFile()
	{
		$cacheFile = JPATH_ROOT . '/cache/jstats.php';

		if (is_readable($cacheFile))
		{
			unlink($cacheFile);
		}
	}
}
