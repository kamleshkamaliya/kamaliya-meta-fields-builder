/* Kamaliya Meta Fields Builder - Frontend Repeater Script */
jQuery(document).ready(function($) {

    $(document).on('click', '.kmb-frontend-media-btn', function(e) {
        e.preventDefault();
        var btn = $(this);
        var wrap = btn.closest('.kmb-frontend-image-wrap');
        var input = wrap.find('.kmb-frontend-image-url');
        var preview = wrap.find('.kmb-frontend-image-preview');
        var img = preview.find('img');
        var removeBtn = wrap.find('.kmb-frontend-remove-img');
        var uploader = wp.media({ title: 'Select Image', button: { text: 'Use image' }, multiple: false });
        uploader.on('select', function() {
            var attachment = uploader.state().get('selection').first().toJSON();
            input.val(attachment.url);
            img.attr('src', attachment.url);
            preview.show();
            removeBtn.show();
            btn.text('Change Image');
        });
        uploader.open();
    });

    $(document).on('click', '.kmb-frontend-remove-img', function(e) {
        e.preventDefault();
        var btn = $(this);
        var wrap = btn.closest('.kmb-frontend-image-wrap');
        wrap.find('.kmb-frontend-image-url').val('');
        wrap.find('.kmb-frontend-image-preview').hide();
        wrap.find('img').attr('src', '');
        btn.hide();
        wrap.find('.kmb-frontend-media-btn').text('Select Image');
    });

    $(document).on('click', '.kmb-frontend-add-row', function(e) {
        e.preventDefault();
        var wrapper = $(this).closest('.kmb-frontend-repeater');
        var rowsContainer = wrapper.find('.kmb-frontend-repeater-rows');
        var template = wrapper.find('.kmb-frontend-repeater-template').html();
        var newIndex = new Date().getTime();
        var newRow = template.replace(/__ROW_INDEX__/g, newIndex);
        rowsContainer.append(newRow);
    });

    $(document).on('click', '.kmb-remove-repeater-row', function(e) {
        e.preventDefault();
        if(confirm('Are you sure you want to remove this row?')) {
            $(this).closest('.kmb-repeater-row').remove();
        }
    });

    $(document).on('click', '.kmb-frontend-media-file-btn', function(e) {
        e.preventDefault();
        var btn = $(this);
        var wrap = btn.closest('.kmb-frontend-file-wrap');
        var input = wrap.find('.kmb-frontend-file-url');
        var removeBtn = wrap.find('.kmb-frontend-remove-file');
        var uploader = wp.media({ title: 'Select File', button: { text: 'Use file' }, multiple: false });
        uploader.on('select', function() {
            var attachment = uploader.state().get('selection').first().toJSON();
            input.val(attachment.url);
            removeBtn.show();
        });
        uploader.open();
    });

    $(document).on('click', '.kmb-frontend-remove-file', function(e) {
        e.preventDefault();
        var wrap = $(this).closest('.kmb-frontend-file-wrap');
        wrap.find('.kmb-frontend-file-url').val('');
        $(this).hide();
    });

    $(document).on('click', '.kmb-select-link-btn', function(e) {
        e.preventDefault();
        var wrap = $(this).closest('.kmb-link-field-wrapper');
        var urlInput   = wrap.find('.kmb-link-url');
        var titleInput = wrap.find('.kmb-link-title');
        var targetInput = wrap.find('.kmb-link-target');
        var resultBox  = wrap.find('.kmb-link-result-box');
        var addBtn     = wrap.children('.kmb-select-link-btn');
        var fakeTextareaId = 'kmb-fake-link-target';
        if ($('#' + fakeTextareaId).length === 0) {
            $('body').append('<textarea id="' + fakeTextareaId + '" style="display:none;"></textarea>');
        }
        wpLink.setDefaultValues = function() {
            $('#wp-link-url').val(urlInput.val());
            $('#wp-link-text').val(titleInput.val());
            $('#wp-link-target').prop('checked', targetInput.val() === '_blank');
        };
        wpLink.open(fakeTextareaId);
        $('#wp-link-submit').off('click.kmb').on('click.kmb', function(e) {
            e.preventDefault();
            var attrs = wpLink.getAttrs();
            var linkText = $('#wp-link-text').val() || '';
            urlInput.val(attrs.href);
            titleInput.val(linkText);
            targetInput.val(attrs.target === '_blank' ? '_blank' : '');
            wrap.find('.kmb-display-title').text(linkText);
            wrap.find('.kmb-display-url').text(attrs.href).attr('href', attrs.href);
            addBtn.hide();
            resultBox.css('display', 'flex');
            wpLink.close();
        });
    });

    $(document).on('click', '.kmb-remove-link-btn', function(e) {
        e.preventDefault();
        var wrap = $(this).closest('.kmb-link-field-wrapper');
        wrap.find('input[type="hidden"]').val('');
        wrap.find('.kmb-link-result-box').hide();
        wrap.children('.kmb-select-link-btn').show();
    });
});
