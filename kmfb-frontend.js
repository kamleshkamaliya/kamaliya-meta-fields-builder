/* Kamaliya Meta Fields Builder - Frontend Repeater Script */
jQuery(document).ready(function($) {

    $(document).on('click', '.kmfb-frontend-media-btn', function(e) {
        e.preventDefault();
        var btn = $(this);
        var wrap = btn.closest('.kmfb-frontend-image-wrap');
        var input = wrap.find('.kmfb-frontend-image-url');
        var preview = wrap.find('.kmfb-frontend-image-preview');
        var img = preview.find('img');
        var removeBtn = wrap.find('.kmfb-frontend-remove-img');
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

    $(document).on('click', '.kmfb-frontend-remove-img', function(e) {
        e.preventDefault();
        var btn = $(this);
        var wrap = btn.closest('.kmfb-frontend-image-wrap');
        wrap.find('.kmfb-frontend-image-url').val('');
        wrap.find('.kmfb-frontend-image-preview').hide();
        wrap.find('img').attr('src', '');
        btn.hide();
        wrap.find('.kmfb-frontend-media-btn').text('Select Image');
    });

    $(document).on('click', '.kmfb-frontend-add-row', function(e) {
        e.preventDefault();
        var wrapper = $(this).closest('.kmfb-frontend-repeater');
        var rowsContainer = wrapper.find('.kmfb-frontend-repeater-rows');
        var template = wrapper.find('.kmfb-frontend-repeater-template').html();
        var newIndex = new Date().getTime();
        var newRow = template.replace(/__ROW_INDEX__/g, newIndex);
        rowsContainer.append(newRow);
    });

    $(document).on('click', '.kmfb-remove-repeater-row', function(e) {
        e.preventDefault();
        if(confirm('Are you sure you want to remove this row?')) {
            $(this).closest('.kmfb-repeater-row').remove();
        }
    });

    $(document).on('click', '.kmfb-frontend-media-file-btn', function(e) {
        e.preventDefault();
        var btn = $(this);
        var wrap = btn.closest('.kmfb-frontend-file-wrap');
        var input = wrap.find('.kmfb-frontend-file-url');
        var removeBtn = wrap.find('.kmfb-frontend-remove-file');
        var uploader = wp.media({ title: 'Select File', button: { text: 'Use file' }, multiple: false });
        uploader.on('select', function() {
            var attachment = uploader.state().get('selection').first().toJSON();
            input.val(attachment.url);
            removeBtn.show();
        });
        uploader.open();
    });

    $(document).on('click', '.kmfb-frontend-remove-file', function(e) {
        e.preventDefault();
        var wrap = $(this).closest('.kmfb-frontend-file-wrap');
        wrap.find('.kmfb-frontend-file-url').val('');
        $(this).hide();
    });

    $(document).on('click', '.kmfb-select-link-btn', function(e) {
        e.preventDefault();
        var wrap = $(this).closest('.kmfb-link-field-wrapper');
        var urlInput   = wrap.find('.kmfb-link-url');
        var titleInput = wrap.find('.kmfb-link-title');
        var targetInput = wrap.find('.kmfb-link-target');
        var resultBox  = wrap.find('.kmfb-link-result-box');
        var addBtn     = wrap.children('.kmfb-select-link-btn');
        var fakeTextareaId = 'kmfb-fake-link-target';
        if ($('#' + fakeTextareaId).length === 0) {
            $('body').append('<textarea id="' + fakeTextareaId + '" style="display:none;"></textarea>');
        }
        wpLink.setDefaultValues = function() {
            $('#wp-link-url').val(urlInput.val());
            $('#wp-link-text').val(titleInput.val());
            $('#wp-link-target').prop('checked', targetInput.val() === '_blank');
        };
        wpLink.open(fakeTextareaId);
        $('#wp-link-submit').off('click.kmfb').on('click.kmfb', function(e) {
            e.preventDefault();
            var attrs = wpLink.getAttrs();
            var linkText = $('#wp-link-text').val() || '';
            urlInput.val(attrs.href);
            titleInput.val(linkText);
            targetInput.val(attrs.target === '_blank' ? '_blank' : '');
            wrap.find('.kmfb-display-title').text(linkText);
            wrap.find('.kmfb-display-url').text(attrs.href).attr('href', attrs.href);
            addBtn.hide();
            resultBox.css('display', 'flex');
            wpLink.close();
        });
    });

    $(document).on('click', '.kmfb-remove-link-btn', function(e) {
        e.preventDefault();
        var wrap = $(this).closest('.kmfb-link-field-wrapper');
        wrap.find('input[type="hidden"]').val('');
        wrap.find('.kmfb-link-result-box').hide();
        wrap.children('.kmfb-select-link-btn').show();
    });
});