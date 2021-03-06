<?php
/**
 * ownCloud - News
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Alessandro Cosentino <cosenal@gmail.com>
 * @author Bernhard Posselt <dev@bernhard-posselt.com>
 * @copyright Alessandro Cosentino 2012
 * @copyright Bernhard Posselt 2012, 2014
 */

namespace OCA\News\Config;

use OCP\AppFramework\IApi;
use OCP\BackgroundJob\IJob;
use OCP\BackgroundJob\IJobList;
use OCP\INavigationManager;
use OCP\IL10N;
use OCP\IURLGenerator;


// Used to parse app.json file, should be in core at some point
class AppConfig {

	private $config;
	private $navigationManager;
	private $urlGenerator;
	private $phpVersion;
	private $ownCloudVersion;
	private $installedApps;
	private $installedExtensions;
	private $databaseType;
	private $l10n;

	/**
	 * TODO: External deps that are needed:
	 * - add jobs
	 * - connect to hooks
	 */
	public function __construct(INavigationManager $navigationManager,
	                            IL10N $l10n,
	                            IURLGenerator $urlGenerator,
	                            $phpVersion,
	                            $ownCloudVersion,
	                            $installedApps,
	                            $installedExtensions,
	                            $databaseType) {
		$this->navigationManager = $navigationManager;
		$this->l10n = $l10n;
		$this->urlGenerator = $urlGenerator;
		$this->ownCloudVersion = $ownCloudVersion;
		$this->phpVersion = $phpVersion;
		$this->installedApps = $installedApps;
		$this->installedExtensions = $installedExtensions;
		$this->databaseType = $databaseType;
		$this->config = array();
	}


	/**
	 * @param string|array $data path to the config file or an array with the config
	 */
	public function loadConfig($data) {
		if(is_array($data)) {
			$this->config = $data;
		} else {
			$json = file_get_contents($data);
			$this->config = json_decode($json, true);
		}

		// fill config with default values if no navigation is added
		if(array_key_exists('navigation', $this->config)) {
			$nav =& $this->config['navigation'];

			// add defaults
			$defaults = array(
				'id' => $this->config['id'],
				'route' => $this->config['id'] . '.page.index',
				'order' => 10,
				'icon' => 'app.svg',
				'name' => $this->config['name']
			);

			foreach($defaults as $key => $value) {
				if(!array_key_exists($key, $nav)) {
					$nav[$key] = $value;
				}	
			}
		}
	}

	/**
	 * @param string $key if given returns the value of the config at index $key
	 * @return array|mixed the config
	 */
	public function getConfig($key=null) {
		// FIXME: is this function interface a good idea?
		if($key !== null) {
			return $this->config[$key];
		} else {
			return $this->config; 
		}
	}


	/**
	 * Parses the navigation and creates a navigation entry if needed
	 */
	public function registerNavigation() {
		// if key is missing, dont create a navigation
		if(array_key_exists('navigation', $this->config)) {
			$nav =& $this->config['navigation'];
			
			$navConfig = array(
				'id' => $nav['id'],
				'order' => $nav['order']
			);

			$navConfig['name'] = $this->l10n->t($nav['name']);
			$navConfig['href'] = $this->urlGenerator->linkToRoute($nav['route']);
			$navConfig['icon'] = $this->urlGenerator->imagePath($nav['id'], 
				$nav['icon']);

			$this->navigationManager->add($navConfig);
		}

	}


	/**
	 * Registers all jobs in the config
	 */
	public function registerBackgroundJobs() {
		// FIXME: this is temporarily static because core jobs are not public 
		// yet, therefore legacy code
		foreach ($this->config['jobs'] as $job) {
			\OCP\Backgroundjob::addRegularTask($job, 'run');
		}
	}


	/**
	 * Registers all hooks in the config
	 */
	public function registerHooks() {
		// FIXME: this is temporarily static because core emitters are not future 
		// proof, therefore legacy code in here
		foreach ($this->config['hooks'] as $listen => $react) {
			$listener = explode('::', $listen);
			$reaction = explode('::', $react);

			// config is written like HookNamespace::method => Class::method
			\OCP\Util::connectHook($listener[0], $listener[1], $reaction[0],
			                       $reaction[1]);
		}
	}


	/**
	 * Validates all dependencies that the app has
	 * @throws \OCA\News\DependencyException if one version is not satisfied
	 */
	public function testDependencies() {
		$msg = '';

		// test databases
		if(array_key_exists('databases', $this->config)) {
			if(!in_array($this->databaseType, $this->config['databases'])) {
				$msg .= 'Database ' . $this->databaseType . ' not supported.' .
					'App is only compatible with ' . 
					implode(', ', $this->config['databases']);
			}
		}
		
		// test dependencies
		if(array_key_exists('dependencies', $this->config)) {
		
			$deps = $this->config['dependencies'];

			if(array_key_exists('php', $deps)) {
				$msg .= $this->requireVersion($this->phpVersion, $deps['php'],
					'PHP');
			}

			if(array_key_exists('owncloud', $deps)) {
				$msg .= $this->requireVersion($this->ownCloudVersion, 
					$deps['owncloud'], 'ownCloud');
			}

			if(array_key_exists('apps', $deps)) {
				foreach ($deps['apps'] as $app => $versions) {
					if(array_key_exists($app, $this->installedApps)) {
						$msg .= $this->requireVersion($this->installedApps[$app], 
							$versions, 'App ' . $app);
					} else {
						$msg .= 'ownCloud app ' . $app . ' required but not installed';
					}
				}
			}

			if(array_key_exists('libs', $deps)) {
				foreach ($deps['libs'] as $lib => $versions) {
					if(array_key_exists($lib, $this->installedExtensions)) {
						$msg .= $this->requireVersion($this->installedExtensions[$lib], 
							$versions, 'PHP extension ' . $lib);
					} else {
						$msg .= 'PHP extension ' . $lib . ' required but not installed';
					}
				}
			}
		}

		if($msg !== '') {
			throw new DependencyException($msg);
		}
	}


	/**
	 * Compares a version with a version requirement string
	 * @param string $actual the actual version that is there
	 * @param string $required a version requirement in the form of 
	 * <=5.3,>4.5 versions are seperated with a comma
	 * @param string $versionType a description of the string that is prepended 
	 * to the error message
	 * @return an error message if the version is not met, empty string if ok
	 */
	private function requireVersion($actual, $required, $versionType) {
		$requiredVersions = $this->splitVersions($required);

		foreach($requiredVersions as $version) {
			// accept * as wildcard for any version
			if($version['version'] === '*') {
				continue;
			}
			if(!version_compare($actual, $version['version'], $version['operator'])) {
				return $versionType . ' Version not satisfied: ' . $version['operator'] . 
					$version['version'] . ' required but found ' . $actual . '\n';				
			}
		}

		return '';
	}


	/**
	 * Versions can be seperated by a comma so split them
	 * @param string $versions a version requirement in the form of 
	 * <=5.3,>4.5 versions are seperated with a comma
	 * @return array of arrays with key=version value=operator
	 */
	private function splitVersions($versions) {
		$result = array();
		$versions = explode(',', $versions);

		foreach($versions as $version) {
			preg_match('/^(?<operator><|<=|>=|>|<>)?(?<version>.*)$/', $version, $matches);
			if($matches['operator'] !== '') {
				$required = array(
					'version' => $matches['version'],
					'operator' => $matches['operator'],
				);
			} else {
				$required = array(
					'version' => $matches['version'],
					'operator' => '==',
				);
			}
			$result[] = $required;
		}

		return $result;
	}


}