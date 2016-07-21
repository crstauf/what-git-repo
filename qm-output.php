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

		echo '<div id="' . esc_attr( $this->collector->id() ) . '" class="qm qm-full">';

			echo '<table cellspacing="0">' .
				'<thead>' .
					'<tr>' .
						'<th colspan="4">Git Repositories/Submodules</th>' .
					'</tr>' .
                    '<tr>' .
                        '<th>Name</th><th>Type</th><th>Branch</th><th>Path</th>' .
                    '</tr>' .
				'</thead>' .
				'<tbody>';

					if (isset($root))
						echo '<tr>' .
							'<th>{Root}</th>' .
							'<th>' . esc_attr('submod' === $root->type ? 'Submodule' : 'Repository') . '</th>' .
							'<td>' . esc_attr($root->branch) . '</td>' .
							'<td>' . esc_attr('submod' === $root->type ? $root->relative_directory : $root->relative) . '</td>' .
						'</tr>';

                    foreach ($repos as $absolute => $repo) {
                        echo '<tr>' .
                            '<th>' . esc_attr($repo->name) . '</th>' .
							'<th>' . esc_attr('submod' === $repo->type ? 'Submodule' : 'Repository') . '</th>' .
                            '<td>' . esc_attr($repo->branch) . '</td>' .
                            '<td>' . esc_attr('submod' === $repo->type ? $repo->relative_directory : $repo->relative) . '</td>' .
                        '</tr>';
                    }

                echo '</tbody>' .
            '</table>' .
        '</div>';

    }

}

?>
