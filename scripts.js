(function($) {

    $(document).on('heartbeat-send',function(ev,data) {
        data['what-git-branch'] = 1;
        if ('function' === typeof HBMonitor)
            HBMonitor('WGB send');
    });

    $(document).on('heartbeat-tick',function(ev,data) {
        var changed = 0;

        for (var path in data) {
            var repo = data[path];
            var li = $("#wp-admin-bar-what-git-branch_" + repo['name']);
            var orig = li.find('.repo-branch').text();

            if ('/' === repo['relative'])
                $("#wp-admin-bar-what-git-branch .root-repo-branch").text(repo['branch']);
            else if (li.length) {
                if (orig !== repo['branch']) {
                    changed++;
                    li.addClass('branch-changed');
                } else
                    li.removeClass('branch-changed');
                li.find('.repo-branch').text(repo['branch']);
            }
        }

        if (0 !== changed) {
            $("#wp-admin-bar-what-git-branch").addClass('has-branch-changed');
            setTimeout(function() {
                $("#wp-admin-bar-what-git-branch .ab-sub-wrapper").fadeOut(500,function() {
                    $("#wp-admin-bar-what-git-branch").removeClass('has-branch-changed');
                    $("#wp-admin-bar-what-git-branch .ab-sub-wrapper").css('display','');
                });
            },3000);
        }

        if ('function' === typeof HBMonitor)
            HBMonitor('WGB tick');
    });

}(jQuery));
