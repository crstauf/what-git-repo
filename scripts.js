(function($) {

    $(document).on('heartbeat-send',function(ev,data) {
        data['what-git-branch'] = 1;
    });

    $(document).on('heartbeat-tick',function(ev,data) {
        var changed = 0;

        for (var path in data) {
            var repo = data[path];
            var li = $("#wp-admin-bar-what-git-branch_" + repo['name']);
            var orig = li.find('.repo-branch').html();

            if ('/' === repo['relative'])
                $("#wp-admin-bar-what-git-branch .root-repo-branch").html(repo['ajax_branch']);
            else if (li.length) {
                if (orig !== repo['ajax_branch']) {
                    changed++;
                    li.addClass('branch-changed');
                } else
                    li.removeClass('branch-changed');
                li.find('.repo-branch').html(repo['ajax_branch']);
            }

            if ($("#qm-whatgitbranch").length && $("#qm-wgb-" + repo['name']).length) {
                var tr = $("#qm-wgb-" + repo['name']);
                var orig_qm = tr.find('.qm-wgb-branch').html();
                tr.find('.qm-wgb-branch').html(repo['ajax_branch']);
                if (orig_qm !== repo['ajax_branch'])
                    tr.addClass('branch-changed');
                else
                    tr.removeClass('branch-changed');
            }
        }

        if (0 !== changed) {
            $("#wp-admin-bar-what-git-branch").addClass('has-branch-changed');
            $("#wp-admin-bar-what-git-branch .ab-sub-wrapper").slideDown(500);
            setTimeout(function() {
                $("#wp-admin-bar-what-git-branch .ab-sub-wrapper").slideUp(500,function() {
                    $("#wp-admin-bar-what-git-branch").removeClass('has-branch-changed');
                    $("#wp-admin-bar-what-git-branch .ab-sub-wrapper").css('display','');
                });
            },3500);
        }
    });

}(jQuery));
