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
	public static $repos = false;

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

		if (is_array(self::$repos) && count(self::$repos)) {

			add_filter('wgb/bar/top/branch',			array(__CLASS__,'filter_bar_top_branch'));
			add_filter('wgb/qm/repo/id',				array(__CLASS__,'filter_qm_repo_id'),10,2);
			add_filter('wgb/qm/repo/name',				array(__CLASS__,'filter_qm_repo_name'),10,2);

			add_action('wp_enqueue_scripts',			array(__CLASS__,'action_enqueue_scripts'));
			add_action('admin_enqueue_scripts',			array(__CLASS__,'action_enqueue_scripts'));
			add_action('admin_bar_menu',				array(__CLASS__,'action_admin_bar_menu'),99999999999999);
			add_filter('manage_plugins_columns',		array(__CLASS__,'filter_manage_plugins_columns'));
			add_action('manage_plugins_custom_column',	array(__CLASS__,'action_manage_plugins_custom_column'),10,3);
			add_action('heartbeat_received',			array(__CLASS__,'heartbeat_received'),10,3);
			add_filter('qm/collectors',					array(__CLASS__,'filter_qm_collectors'),20,2);
			add_filter('qm/outputter/html',				array(__CLASS__,'filter_qm_outputters_0'),0);
			add_filter('qm/outputter/html',				'register_what_git_branch_output_html',155,2);

		}

	}

	private static function add($path) {
		if (file_exists(trailingslashit($path) . '.git')) {
			$repo = new cssllc_what_git_branch_repo($path);
			if (false !== $repo->type)
				self::$repos[$path] = $repo;
		}
	}

	public static function get_branch($path) {
		if (!array_key_exists($path,self::$repos) || false === self::$repos[$path]) return false;
		return self::$repos[$path]->get_branch();
	}

	public static function filter_bar_top_branch($text)     { return (false === $text ? apply_filters('wgb/bar/top/text','Git') : $text); }
	public static function filter_qm_repo_id($id,$repo)     { return $repo->is_root() ? 'root' : $id; }
	public static function filter_qm_repo_name($name,$repo) { return $repo->is_root() ? '{ROOT}' : $name; }

	public static function action_enqueue_scripts() {
		wp_enqueue_script('what-git-branch',plugin_dir_url(__FILE__) . 'scripts.js',array('jquery','heartbeat'),'3e46dd6');
		wp_enqueue_style('what-git-branch',plugin_dir_url(__FILE__) . 'style.css',array(),'5019311');
	}

	public static function action_admin_bar_menu($bar) {
		$repos = array();
		foreach (self::$repos as $repo)
			if (is_object($repo) && isset($repo->name))
				$repos[$repo->name] = $repo;

		ksort($repos);

		if (array_key_exists(ABSPATH,self::$repos)) {
			$root = $repos[self::$repos[ABSPATH]->name];
			unset($repos[self::$repos[ABSPATH]->name]);
			$repos = array_merge(array('/' => $root),$repos);
		}

		$bar->add_node(array(
			'id' => 'what-git-branch',
			'title' => apply_filters(
				'wgb/bar/top',
				'<span class="code root-repo-branch">' . apply_filters('wgb/bar/top/branch',self::get_branch(ABSPATH)) . '</span>'
			),
			'href' => '#',
			'parent' => false,
			'meta' => array(
				'class' => (15 >= count($repos) ? 'lte-15-repos' : 'gt-15-repos'),
			),
		));

		foreach ($repos as $repo) {
			$name = apply_filters(
				'wgb/bar/repo/name',
				($repo->is_root() ? '/' : $repo->name),
				$repo
			);
			$branch = apply_filters(
				'wgb/bar/repo/branch',
				$repo->get_branch(),
				$repo
			);
			$path = apply_filters(
				'wgb/bar/repo/path',
				($repo->is_root() ? $repo->path : $repo->get_relative()),
				$repo
			);
			$bar->add_node(array(
				'id' => 'what-git-branch_' . $repo->name,
				'title' =>
					'<span class="repo-name">' . $name . '</span> : ' .
					'<span class="repo-branch code">' . $branch . '</span>' .
					'<span class="repo-path"><br />' . ($repo->is_submod() ? 'submod: ' : '') . $path . '</span>',
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
			$repo->update_branch();
			self::$repos[$i] = $repo;
		}

		return self::$repos;
	}

	public static function filter_qm_collectors( array $collectors, QueryMonitor $qm ) {
		$collectors['whatgitbranch'] = new cssllc_what_git_branch_qm_collector;
		return $collectors;
	}

	public static function filter_qm_outputters_0( array $output ) {
		require_once 'qm-output.php';
		return $output;
	}

}

class cssllc_what_git_branch_repo {

	var $type = false;
	var $name = '';	              // directory name
	var $path = '';               // absolute path to repository location
	var $branch = '';

	var $git_path = '';           // messy path to location of submodule git files
	var $commit = false;

	function __construct() {
		$args = func_get_args();

		$this->path = trailingslashit($args[0]);
		$this->name = basename($this->path);

		if (
			is_dir($this->path . '.git') &&
			file_exists($this->path . '.git/HEAD') &&
			is_file($this->path . '.git/HEAD')
		) {
			$this->type = 'repository';

		} else if (is_file($this->path . '.git')) {
			$submod = file_get_contents($this->path . '.git');
			$path = trailingslashit($this->path . trim(str_replace('gitdir: ','',$submod)));
			if (
				file_exists($path) && is_dir($path) &&
				file_exists($path . 'HEAD')
			) {
				$this->type = 'submodule';
				$this->git_path = $path;
			}
		}

		$this->update_branch();
	}

	function update_branch() {
		if ($this->is_repo())
			$file = file_get_contents($this->path . '.git/HEAD');
		else if ($this->is_submod())
			$file = file_get_contents($this->git_path . 'HEAD');

		if (false !== stripos($file,'ref: ')) {
			$pos = strripos($file,'/');
			$this->branch = substr(trim($file),($pos + 1));
			$this->commit = false;
		} else {
			$this->branch = 'HEAD';
			$this->commit = trim($file);
		}

		if (defined('DOING_AJAX') && DOING_AJAX)
			$this->ajax_branch = $this->get_branch();

	}

	function get_branch($display = true) {
		if (false === $display)
			return $this->branch . (false !== $this->commit ? ' (' . $this->commit . ')' : '');

		return apply_filters(
			'wgb/repo/branch',
			esc_html($this->branch) .
			(
				false !== $this->commit
				? ' (<abbr class="commit-short" title="' . esc_html($this->commit) . '">' . esc_html(substr($this->commit,0,7)) . '</abbr>' .
					'<span class="commit-full">' . esc_html($this->commit) . '</span>)'
				: ''
			),
			$this
		);
	}

	function is_root()   { return      ABSPATH === $this->path; }
	function is_repo()   { return 'repository' === $this->type; }
	function is_submod() { return  'submodule' === $this->type; }

	function get_relative() {
		return apply_filters(
			'wgb/repo/get/relative',
			'.' . str_replace(rtrim(ABSPATH,'/'),'',$this->path),
			$this
		);
	}

}

if (class_exists('QM_Collector')) {
	class cssllc_what_git_branch_qm_collector extends QM_Collector {

		public $id = 'whatgitbranch';

		public function name() {
			return __( 'Git Repositories/Submodules', 'query-monitor' );
		}

		public function __construct() {
			global $wpdb;
			parent::__construct();
		}

		public function process() {
			$this->data['whatgitbranch'] = cssllc_what_git_branch::$repos;
		}
	}
}

?>
