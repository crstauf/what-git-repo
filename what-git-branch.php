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
		self::add(ABSPATH);

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

		add_action('wp_enqueue_scripts',			array(__CLASS__,'action_enqueue_scripts'));
		add_action('admin_enqueue_scripts',			array(__CLASS__,'action_enqueue_scripts'));
		add_action('admin_bar_menu',				array(__CLASS__,'action_admin_bar_menu'),99999999999999);
		add_filter('manage_plugins_columns',		array(__CLASS__,'filter_manage_plugins_columns'));
		add_action('manage_plugins_custom_column',	array(__CLASS__,'action_manage_plugins_custom_column'),10,3);
		add_action('heartbeat_received',			array(__CLASS__,'heartbeat_received'),10,3);
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

	public static function action_enqueue_scripts() {
		wp_enqueue_script('what-git-branch',plugin_dir_url(__FILE__) . 'scripts.js',array('jquery','heartbeat'),'init');
		wp_enqueue_style('what-git-branch',plugin_dir_url(__FILE__) . 'style.css',array(),'init');
	}

	public static function action_admin_bar_menu($bar) {
		$repos = array();
		foreach (self::$repos as $repo)
			if (is_object($repo) && isset($repo->name))
				$repos[$repo->name] = $repo;

		$bar->add_node(array(
			'id' => 'what-git-branch',
			'title' => apply_filters('wgb/bar/top','<span class="code root-repo-branch">' . apply_filters('wgb/bar/top/branch',self::get_branch(ABSPATH,'Git')) . '</span>'),
			'href' => '#',
			'parent' => false,
			'meta' => array(
				'class' => (15 >= count($repos) ? 'lte-15-repos' : 'gt-15-repos'),
			),
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
			$path = apply_filters(
				'wgb/bar/repo/path',
				('submod' === $repo->type ? $repo->relative_directory : ('/' === $repo->relative ? $repo->path : $repo->relative)),
				$repo
			);
			$bar->add_node(array(
				'id' => 'what-git-branch_' . $repo->name,
				'title' => '<span class="repo-name">' . $name . '</span> : <span class="repo-branch code">' . $branch . '</span><span class="repo-path"><br />' . ('submod' === $repo->type ? 'submod: ' : '') . $path . '</span>',
				'href' => '#',
				'parent' => 'what-git-branch',
				'meta' => array(
					'class' => 'type-' . $repo->type,
				),
			));
		}
	}

	public static function filter_manage_plugins_columns($columns) {
		return array_merge($columns,array('git' => 'Git Info'));
	}

	public static function action_manage_plugins_custom_column($column,$file,$data) {
		if ('git' !== $column) return false;
		$path = dirname(WP_PLUGIN_DIR . '/' . $file);
		if (array_key_exists($path,self::$repos))
			echo 'Branch <span class="code">' . self::get_branch($path) . '</span>';
		else
			echo '&mdash;';
	}

	public static function heartbeat_received($response,$data,$screen_id) {
		if (
			!array_key_exists('what-git-branch',$data) ||
			1 != $data['what-git-branch']
		)
			return $response;

		foreach (self::$repos as $i => $repo) {
			$repo->get_branch();
			self::$repos[$i] = $repo;
		}

		return self::$repos;
	}

}

class cssllc_what_git_branch_repo {

	var $type = false;
	var $name = '';	              // directory name
	var $path = '';               // absolute path to repository location
	var $relative = '';           // path relative to ABSPATH
	var $directory = '';          // absolute path to submodule location (not .git files)
	var $relative_directory = ''; // relative path to submodule location (not .git files)
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

		} else if (is_file($this->path . '/.git')) {
			$this->directory = $this->path;
			$this->relative_directory = str_replace(rtrim(ABSPATH,'/'),'',$this->directory);
			$submod = file_get_contents($this->path . '/.git');
			$path = trailingslashit($this->path . '/' . trim(str_replace('gitdir: ','',$submod)));
			if (
				file_exists($path) && is_dir($path) &&
				file_exists($path . 'HEAD')
			) {
				$this->type = 'submod';
				$this->path = $path;
				$this->relative = str_replace(rtrim(ABSPATH,'/'),'',$this->path);
			}
		}

		$this->get_branch();
	}

	function get_branch() {
		$this->branch_changed = false;
		$orig = $this->branch;

		if ('repository' === $this->type) {
			$file = file_get_contents($this->path . '/.git/HEAD');
			$pos = strripos($file,'/');
			$this->branch = esc_attr(substr(trim($file),($pos + 1)));
		} else if ('submod' === $this->type) {
			$file = file_get_contents(trailingslashit($this->path) . 'HEAD');
			if (false !== stripos($file,'ref: ')) {
				$pos = strripos($file,'/');
				$this->branch = esc_attr(substr(trim($file),($pos + 1)));
			} else
				$this->branch = esc_attr(substr(trim($file),0,7));
		}

		if ($this->branch !== $orig) $this->branch_changed = true;
	}

	public static function is_repo()   { return 'repository' === $this->type; }
	public static function is_submod() { return     'submod' === $this->type; }

}
