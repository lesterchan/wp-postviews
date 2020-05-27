jQuery(document).ready(function($) {
    var postViewsIdArray = [];
    var postViewsId = null;
    $("span[id^='postViewsId-']").each(function(i, el) {
        postViewsIdArray[i] = (el.id).replace("postViewsId-", "");
    });
    if (postViewsIdArray) {
        postViewsId = postViewsIdArray.join(',');
        $.post(viewsCacheRefresh.admin_ajax_url+"?action=postviewsrefresh", {
            postviews_ids: postViewsId
        }, function(data) {
            if (data) {
                jQuery.each(data, function(key, val) {
                    $("#postViewsId-" + val.id).text(val.views);
                });
            }
        }, "json");
    }
});