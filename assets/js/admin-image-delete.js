(function($){
  $(document).ready(function(){
    var $btn = $('#plugin-delete-images-btn');
    if (!$btn.length) return;

    $btn.on('click', function(e){
      e.preventDefault();

      var postId = $(this).data('post-id');
      var deletePhysical = $('#plugin-delete-physical').is(':checked') ? 1 : 0;
      var $spinner = $('#plugin-delete-images-spinner');
      var $result = $('#plugin-delete-images-result');

      $btn.prop('disabled', true);
      $spinner.show();
      $result.text('');

      $.ajax({
        url: (typeof PluginImageDelete !== 'undefined' && PluginImageDelete.ajaxUrl) ? PluginImageDelete.ajaxUrl : ajaxurl,
        method: 'POST',
        dataType: 'json',
        data: {
          action: 'plugin_delete_images',
          nonce: (typeof PluginImageDelete !== 'undefined' ? PluginImageDelete.nonce : ''),
          post_id: postId,
          delete_physical: deletePhysical
        },
        success: function(res){
          if (res && res.success) {
            $result.text(res.data && res.data.message ? res.data.message : '操作が完了しました。');
          } else if (res && res.data && res.data.message) {
            $result.text('エラー: ' + res.data.message);
          } else {
            $result.text('不明なレスポンスを受信しました。');
          }
        },
        error: function(xhr, status, err){
          var text = 'AJAX エラー: ' + status + (err ? ' - ' + err : '');
          try {
            var json = xhr && xhr.responseJSON;
            if (json && json.data && json.data.message) {
              text += '\n' + json.data.message;
            } else if (xhr.responseText) {
              text += '\n' + xhr.responseText;
            }
          } catch(e) {}
          $result.text(text);
        },
        complete: function(){
          $btn.prop('disabled', false);
          $spinner.hide();
        }
      });
    });
  });
})(jQuery);
