jQuery(function($){
    $(document).on('click', '#plugin-delete-images-btn', function(e){
        e.preventDefault();
        var postId = $(this).data('postid');
        var deletePhysical = $('#plugin_delete_physical').is(':checked') ? 1 : 0;
        var btn = $(this);
        var result = $('#plugin-delete-images-result');
        if (!confirm('本当に画像を削除しますか？（元に戻せません）')) return;
        btn.prop('disabled', true).text('実行中...');
        $.post(PluginImageDelete.ajaxUrl, {
            action: 'plugin_delete_images',
            nonce: PluginImageDelete.nonce,
            post_id: postId,
            delete_physical: deletePhysical
        }).done(function(resp){
            if (resp.success) {
                result.html('');
            } else {
                result.html('');
            }
        }).fail(function(){
            result.html('');
        }).always(function(){
            btn.prop('disabled', false).text('画像を削除');
        });
    });
});
