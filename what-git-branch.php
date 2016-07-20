<?php
/*
Plugin Name: What Git Branch?
Plugin URI:
Description:
Version: 0.0.3
Author: Caleb Stauffer
Author URI: http://develop.calebstauffer.com
*/

if (!defined('ABSPATH') || !function_exists('add_filter')) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}

new cssllc_what_git_branch;

class cssllc_what_git_branch {

	private static $scandirs = array();
	private static $repos = false;

	function __construct() {
		self::add(ABSPATH,'Root');

		self::$scandirs = apply_filters('wgb/scandirs',array(
			WP_PLUGIN_DIR,
			get_theme_root(),
		));

		foreach (self::$scandirs as $label => $dir) {
			foreach (scandir($dir) as $file) {
				if (!in_array($file,array('.','..')) && is_dir($dir . '/' . $file)) {
					self::add($dir . '/' . $file,$label);
				}
			}

		}

		add_action('admin_bar_menu',array(__CLASS__,'action_admin_bar_menu'),99999999999999);
	}

	private static function add($path) {
		if (file_exists(trailingslashit($path) . '.git')) {
			$repo = new cssllc_what_git_branch_repo($path);
			if (false !== $repo->type)
				self::$repos[$path] = $repo;
		}
	}

	private static function get_branch($path,$default = false) {
		return !array_key_exists($path,self::$repos) || false === self::$repos[$path] ? $default : self::$repos[$path]->branch;
	}

	public static function action_admin_bar_menu($bar) {
		$repos = array();
		foreach (self::$repos as $repo)
			if (is_object($repo) && isset($repo->name))
				$repos[$repo->name] = $repo;

		$bar->add_node(array(
			'id' => 'what-git-branch',
			'title' => apply_filters('wgb/bar/top','<span class="code" style="display: inline-block; background-image: url(' . plugin_dir_url(__FILE__) . 'git.png); background-size: auto 50%; background-repeat: no-repeat; background-position: 7px center; background-color: #32373c; padding: 0 7px 0 27px; font-family: Consolas,Monaco,monospace;">' . apply_filters('wgb/bar/top/branch',self::get_branch(ABSPATH,'Git')) . '</span>'),
			'href' => '#',
			'parent' => false,
		));

		ksort($repos);

		if (array_key_exists(ABSPATH,self::$repos)) {
			$root = $repos[self::$repos[ABSPATH]->name];
			unset($repos[self::$repos[ABSPATH]->name]);
			$repos = array_merge(array('/' => $root),$repos);
		}

		foreach ($repos as $repo) {
			$name = apply_filters(
				'wgb/bar/repo/name',
				(ABSPATH === $repo->path ? $repo->relative : $repo->name),
				$repo
			);
			$branch = apply_filters(
				'wgb/bar/repo/branch',
				$repo->branch,
				$repo
			);
			$bar->add_node(array(
				'id' => 'what-git-branch_' . $repo->name,
				'title' => $name . ' : <span class="code" style="font-family: Consolas,Monaco,monospace; font-size: 0.9em;">' . $branch . '</span>',
				'href' => '#',
				'parent' => 'what-git-branch',
				'meta' => array(
					'class' => 'wgb-type-' . $repo->type,
				),
			));
		}
	}

}

class cssllc_what_git_branch_repo {

	var $type = false;
	var $name = '';	    // directory name
	var $path = '';     // absolute path to repository location
	var $relative = ''; // path relative to ABSPATH
	var $branch = '';

	function __construct() {
		$args = func_get_args();

		$this->path = $args[0];
		$this->name = basename($this->path);
		$this->relative = str_replace(rtrim(ABSPATH,'/'),'',$this->path);

		if (
			is_dir($this->path . '/.git') &&
			file_exists($this->path . '/.git/HEAD') &&
			is_file($this->path . '/.git/HEAD')
		) {
			$this->type = 'repository';
			$file = file_get_contents($this->path . '/.git/HEAD');
			$pos = strripos($file,'/');
			$this->branch = esc_attr(trim(substr($file,($pos + 1))));

		} else if (is_file($this->path . '/.git')) {
			$submod = file_get_contents($this->path . '/.git');
			$path = $this->path . '/' . str_replace('gitdir: ','',$submod);
			if (
				file_exists($path) && is_dir($path) &&
				file_exists($path . 'HEAD')
			) {
				$this->type = 'submod';
				$this->path = $path;
				$this->relative = str_replace(rtrim(ABSPATH,'/'),'',$this->path);
				$file = file_get_contents($this->path . 'HEAD');
				$this->branch = esc_attr(substr(trim($file),0,7));
			}
		}
	}

	public static function is_repo()   { return 'repository' === $this->type; }
	public static function is_submod() { return     'submod' === $this->type; }

}
