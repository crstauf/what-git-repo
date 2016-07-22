<?php

if (!defined('ABSPATH') || !function_exists('add_filter')) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}

class cssllc_what_git_branch_qm_outputter extends QM_Output_Html {

	public function __construct( QM_Collector $collector ) {
		parent::__construct( $collector );
	}

	public function output() {

		$data = $this->collector->get_data();

        $repos = array();
        foreach ($data['whatgitbranch'] as $repo)
            $repos[$repo->name] = $repo;

        ksort($repos);

        if (
            array_key_exists(ABSPATH,$data['whatgitbranch']) &&
            array_key_exists($data['whatgitbranch'][ABSPATH]->name,$repos)
        ) {
			$root = $repos[$data['whatgitbranch'][ABSPATH]->name];
			unset($repos[$data['whatgitbranch'][ABSPATH]->name]);
		}

		echo '<div id="' . esc_attr( $this->collector->id() ) . '" class="qm qm-half">';

			echo '<table cellspacing="0">' .
				'<thead>' .
					'<tr>' .
						'<th colspan="3">Git Repositories/Submodules</th>' .
					'</tr>' .
				'</thead>' .
				'<tfoot>' .
					'<tr>' .
						'<td colspan="3" style="text-align: right !important;">Count: ' . count($data['whatgitbranch']) . '</td>' .
					'</tr>' .
				'</tfoot>' .
				'<tbody>';

					if (isset($root))
						$this->row($root);

					foreach ($repos as $repo)
						$this->row($repo);

                echo '</tbody>' .
            '</table>' .
        '</div>';

    }

	function row($repo) {
		echo '<tr id="qm-wgb-' . apply_filters('wgb/qm/repo/id',$repo->name,$repo) . '">' .
			'<th>' .
				esc_html(apply_filters('wgb/qm/repo/name',$repo->name,$repo)) .
			'</th>' .
			'<td class="qm-has-inner qm-has-toggle">' .
				'<div class="qm-toggler">' .
					'<div class="qm-inner-toggle">' .
						'<span class="qm-wgb-branch">' . apply_filters('wgb/qm/repo/branch',$repo->get_branch(),$repo) . '</span>' .
						'<a href="#" class="qm-toggle" data-on="+" data-off="-">+</a>' .
					'</div>' .
					'<div class="qm-toggled" style="display: none;">' .
						'<table cellspacing="0" class="qm-inner">' .
							'<tbody>' .
								'<tr>' .
									'<td>' .
											esc_html(apply_filters(
												'wgb/qm/repo/path',
												$repo->is_root() ? $repo->path : $repo->get_relative(),
												$repo
											)) .
									'</td>' .
								'</tr>' .
								'<tr>' .
									'<td>' . esc_html($repo->is_submod() ? 'submodule' : 'repository') . '</td>' .
								'</tr>' .
							'</tbody>' .
						'</table>' .
					'</div>' .
				'</div>' .
			'</td>' .
		'</tr>';
	}

}

?>
