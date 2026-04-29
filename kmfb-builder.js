/* Kamaliya Meta Fields Builder - Admin Builder Script */
jQuery(document).ready(function($) {
    var fieldIndex = (typeof kmfbBuilderData !== 'undefined') ? kmfbBuilderData.fieldIndex : 0;

    // DRAG AND DROP (SORTABLE) ENGINE
    function kmfbInitSortable() {
        $('#kmfb-fields-container').sortable({
            handle: '.kmfb-field-header',
            axis: 'y', opacity: 0.8,
            update: function(event, ui) { kmfbReindexAllFields(); }
        });
        $('.kmfb-sub-fields-container').sortable({
            handle: '.kmfb-sub-field-header',
            axis: 'y', opacity: 0.8,
            update: function(event, ui) { kmfbReindexAllFields(); }
        });
    }
    kmfbInitSortable();

    function kmfbReindexAllFields() {
        $('#kmfb-fields-container > .kmfb-field-wrap').each(function(mainIndex) {
            var mainWrap = $(this);
            mainWrap.attr('data-index', mainIndex);
            mainWrap.find('[name^="kmfb_fields["]').each(function() {
                var name = $(this).attr('name');
                if(name) $(this).attr('name', name.replace(/^kmfb_fields\[\d+\]/, 'kmfb_fields[' + mainIndex + ']'));
            });
            mainWrap.find('.kmfb-sub-fields-container > .kmfb-sub-field-wrap').each(function(subIndex) {
                var subWrap = $(this);
                subWrap.find('[name*="[sub_fields]"]').each(function() {
                    var subName = $(this).attr('name');
                    if(subName) $(this).attr('name', subName.replace(/\[sub_fields\]\[\d+\]/, '[sub_fields][' + subIndex + ']'));
                });
                subWrap.find('.kmfb-nested-fields-container > .kmfb-nested-field-wrap').each(function(nestIndex) {
                    $(this).find('[name*="[nested_fields]"]').each(function() {
                        var nestName = $(this).attr('name');
                        if(nestName) $(this).attr('name', nestName.replace(/\[nested_fields\]\[\d+\]/, '[nested_fields][' + nestIndex + ']'));
                    });
                });
            });
        });
    }

    // UNIQUE SLUG VALIDATION
    function validateUniqueSlugs() {
        var slugs = {};
        $('.kmfb-input-name').each(function() {
            var val = $(this).val().trim();
            $(this).css('border-color', '#8c8f94');
            $(this).next('.kmfb-slug-error').remove();
            if(val !== '') {
                if(slugs[val]) {
                    $(this).css('border-color', '#d63638');
                    $(this).after('<div class="kmfb-slug-error" style="color:#d63638; font-size:12px; margin-top:4px;">Error: Field Name (Slug) must be unique!</div>');
                } else {
                    slugs[val] = true;
                }
            }
        });
    }

    $('#kmfb-add-field').on('click', function() {
        var html = $('#kmfb-field-template').html().replace(/__INDEX__/g, fieldIndex);
        $('#kmfb-fields-container').append(html);
        fieldIndex++;
        kmfbInitSortable();
    });

    $(document).on('click', '.kmfb-field-header, .kmfb-close-field', function() {
        $(this).closest('.kmfb-field-wrap').find('.kmfb-field-body').slideToggle(200);
    });

    $(document).on('click', '.kmfb-sub-field-header', function() {
        $(this).closest('.kmfb-sub-field-wrap').find('.kmfb-sub-field-body').slideToggle(200);
    });

    // Soft Delete for Main Field
    $(document).on('click', '.kmfb-remove-field', function(e) {
        e.stopPropagation();
        var wrap = $(this).closest('.kmfb-field-wrap');
        wrap.addClass('kmfb-deleted');
        wrap.children('.kmfb-field-header, .kmfb-field-body').hide();
        wrap.find('input, select, textarea').prop('disabled', true);
        if (wrap.find('.kmfb-undo-box').length === 0) {
            wrap.append('<div class="kmfb-undo-box" style="padding:15px; background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; border-radius:3px; display:flex; justify-content:space-between; align-items:center;"><span>Field temporarily deleted.</span> <button type="button" class="button kmfb-undo-btn">Undo</button></div>');
        } else {
            wrap.find('.kmfb-undo-box').show();
        }
        validateUniqueSlugs();
    });

    // Undo Button Logic (Main Field)
    $(document).on('click', '.kmfb-undo-btn', function(e) {
        e.preventDefault();
        var wrap = $(this).closest('.kmfb-field-wrap');
        wrap.removeClass('kmfb-deleted');
        wrap.find('.kmfb-undo-box').hide();
        wrap.children('.kmfb-field-header').show();
        wrap.children('.kmfb-field-body').show();
        wrap.find('input, select, textarea').prop('disabled', false);
        $('.kmfb-input-type').trigger('change');
        validateUniqueSlugs();
    });

    // Tab-based field type limiting
    $(document).on('change', '.kmfb-input-tab', function() {
        var wrap = $(this).closest('.kmfb-field-wrap');
        var tabValue = $(this).val();
        wrap.find('> .kmfb-field-header .hdr-tab-badge').text(tabValue);
        var typeDropdown = wrap.find('> .kmfb-field-body > .kmfb-field-row .kmfb-input-type').first();
        if (tabValue === 'style') {
            typeDropdown.find('option').hide().prop('disabled', true);
            typeDropdown.find('option[value="color"], option[value="boolean"], option[value="select"]').show().prop('disabled', false);
            var currentType = typeDropdown.val();
            if (['color', 'boolean', 'select'].indexOf(currentType) === -1) {
                typeDropdown.val('color').trigger('change');
            }
        } else {
            typeDropdown.find('option').show().prop('disabled', false);
        }
    });

    setTimeout(function() { $('.kmfb-input-tab').trigger('change'); }, 200);

    // Lock existing fields on page load
    setTimeout(function() {
        $('.kmfb-input-name').each(function() {
            if ($(this).val().trim() !== '') {
                $(this).addClass('kmfb-slug-locked');
            }
        });
    }, 100);

    // Lock slug if user manually types
    $(document).on('keyup input', '.kmfb-input-name', function() {
        $(this).addClass('kmfb-slug-locked');
        var wrap = $(this).closest('.kmfb-field-wrap');
        if($(this).closest('.kmfb-sub-field-wrap').length === 0) {
            wrap.find('> .kmfb-field-header .hdr-name').text($(this).val() || 'new_field');
        }
        validateUniqueSlugs();
    });

    // Main Field Label Sync
    $(document).on('keyup input', '.kmfb-input-label', function() {
        var wrap = $(this).closest('.kmfb-field-wrap');
        var slugInput = wrap.find('> .kmfb-field-body > .kmfb-field-row .kmfb-input-name').not('.kmfb-sub-name');
        if (!slugInput.hasClass('kmfb-slug-locked')) {
            var slug = $(this).val().toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/(^_|_$)/g, '');
            slugInput.val(slug);
            wrap.find('> .kmfb-field-header .hdr-name').text(slug || 'new_field');
            validateUniqueSlugs();
        }
        wrap.find('> .kmfb-field-header .hdr-label').text($(this).val() || 'New Field');
    });

    // Sub Field Label Sync
    $(document).on('keyup input', '.kmfb-sub-label', function() {
        var wrap = $(this).closest('.kmfb-sub-field-wrap');
        var slugInput = wrap.find('.kmfb-sub-name').first();
        if (!slugInput.hasClass('kmfb-slug-locked')) {
            var slug = $(this).val().toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/(^_|_$)/g, '');
            slugInput.val(slug);
            validateUniqueSlugs();
        }
        wrap.find('.kmfb-sub-field-header .s-title').text($(this).val() || 'New Sub Field');
    });

    // Header Slug Copy
    $(document).on('click', '.kmfb-copy-header-slug', function(e) {
        e.stopPropagation();
        var wrap = $(this).closest('.kmfb-field-wrap');
        var textToCopy = wrap.find('> .kmfb-field-body .kmfb-input-name').val();
        if(!textToCopy) { alert('Slug is empty!'); return; }
        var icon = $(this);
        navigator.clipboard.writeText(textToCopy).then(function() {
            icon.removeClass('dashicons-admin-page').addClass('dashicons-yes').css('color', '#00a32a');
            setTimeout(function() {
                icon.removeClass('dashicons-yes').addClass('dashicons-admin-page').css('color', '#2271b1');
            }, 2000);
        });
    });

    // TYPE CHANGE FOR BOTH PARENT AND SUB FIELDS
    $(document).on('change', '.kmfb-input-type', function() {
        var val = $(this).val();
        var isSubField = $(this).closest('.kmfb-sub-field-wrap').length > 0;
        var wrap = isSubField ? $(this).closest('.kmfb-sub-field-wrap') : $(this).closest('.kmfb-field-wrap');
        if (!isSubField) {
            wrap.find('> .kmfb-field-header .hdr-type').text($(this).find('option:selected').text());
        }
        var defaultRow = isSubField ? wrap.find('.kmfb-sub-row-default-val') : wrap.find('.kmfb-row-default-val');
        var subRow = wrap.find('.kmfb-row-sub-fields');
        var nestedRow = wrap.find('.kmfb-sub-row-nested-fields');
        if(val === 'repeater' || val === 'group') {
            defaultRow.hide(); subRow.show();
            if(isSubField) nestedRow.show();
            defaultRow.find('.kmfb-input-default').prop('disabled', true);
        } else if (val === 'link' || val === 'menu') {
            defaultRow.hide();
            if(!isSubField) subRow.hide();
            if(isSubField) nestedRow.hide();
            defaultRow.find('.kmfb-input-default').prop('disabled', true);
        } else {
            defaultRow.show();
            if(!isSubField) subRow.hide();
            if(isSubField) nestedRow.hide();
            defaultRow.find('.kmfb-input-default, .kmfb-default-image, .kmfb-choices-wrap, .kmfb-default-file').addClass('kmfb-hidden');
            defaultRow.find('.kmfb-input-default, .kmfb-image-url, .kmfb-image-alt, .kmfb-image-title, .kmfb-choices-wrap textarea, .kmfb-file-url').prop('disabled', true);
            if(val === 'text') {
                defaultRow.find('.kmfb-default-text').removeClass('kmfb-hidden').prop('disabled', false);
            } else if (val === 'textarea' || val === 'embed') {
                defaultRow.find('.kmfb-default-textarea').removeClass('kmfb-hidden').prop('disabled', false);
            } else if (val === 'number') {
                defaultRow.find('.kmfb-default-number').removeClass('kmfb-hidden').prop('disabled', false);
            } else if (val === 'image') {
                defaultRow.find('.kmfb-default-image').removeClass('kmfb-hidden');
                defaultRow.find('.kmfb-image-url, .kmfb-image-alt, .kmfb-image-title').prop('disabled', false);
            } else if (val === 'color') {
                defaultRow.find('.kmfb-default-color').removeClass('kmfb-hidden').prop('disabled', false);
            } else if (val === 'boolean') {
                defaultRow.find('.kmfb-default-boolean').removeClass('kmfb-hidden').prop('disabled', false);
            } else if (val === 'select') {
                defaultRow.find('.kmfb-choices-wrap').removeClass('kmfb-hidden');
                defaultRow.find('.kmfb-choices-wrap textarea, .kmfb-default-select').prop('disabled', false);
            } else if (val === 'file') {
                defaultRow.find('.kmfb-default-file').removeClass('kmfb-hidden');
                defaultRow.find('.kmfb-file-url').prop('disabled', false);
            }
        }
    });

    // IMAGE UPLOADER WITH META DATA
    $(document).on('click', '.kmfb-media-btn', function(e) {
        e.preventDefault();
        var button = $(this);
        var wrapper = button.closest('.kmfb-default-image');
        var inputUrl = wrapper.find('.kmfb-image-url');
        var inputAlt = wrapper.find('.kmfb-image-alt');
        var inputTitle = wrapper.find('.kmfb-image-title');
        var previewWrap = wrapper.find('.kmfb-image-preview-wrap');
        var previewImg = wrapper.find('.kmfb-image-preview');
        var metaTitle = wrapper.find('.meta-title');
        var metaAlt = wrapper.find('.meta-alt');
        var removeBtn = wrapper.find('.kmfb-remove-image-btn');
        var customUploader = wp.media({ title: 'Select Image', button: { text: 'Use this image' }, multiple: false });
        customUploader.on('select', function() {
            var attachment = customUploader.state().get('selection').first().toJSON();
            inputUrl.val(attachment.url);
            inputAlt.val(attachment.alt);
            inputTitle.val(attachment.title || attachment.filename);
            previewImg.attr('src', attachment.url);
            metaTitle.text(attachment.title || attachment.filename);
            metaAlt.text(attachment.alt || 'None');
            previewWrap.show();
            removeBtn.show();
            button.text('Change Image');
        });
        customUploader.open();
    });

    $(document).on('click', '.kmfb-remove-image-btn', function() {
        var wrapper = $(this).closest('.kmfb-default-image');
        wrapper.find('input[type="hidden"]').val('');
        wrapper.find('.kmfb-image-preview-wrap').hide();
        wrapper.find('.kmfb-image-preview').attr('src', '');
        $(this).hide();
        wrapper.find('.kmfb-media-btn').text('Select Image');
    });

    // File Uploader
    $(document).on('click', '.kmfb-media-file-btn', function(e) {
        e.preventDefault();
        var inputUrl = $(this).siblings('.kmfb-file-url');
        var customUploader = wp.media({ title: 'Select File', button: { text: 'Use this file' }, multiple: false });
        customUploader.on('select', function() {
            var attachment = customUploader.state().get('selection').first().toJSON();
            inputUrl.val(attachment.url);
        });
        customUploader.open();
    });

    // Add Sub Field
    $(document).on('click', '.kmfb-add-sub-field', function() {
        var parentIndex = $(this).data('parent');
        var container = $(this).siblings('.kmfb-sub-fields-container');
        var subIndex = container.find('.kmfb-sub-field-wrap').length;
        var html = $('#kmfb-sub-field-template').html();
        html = html.replace(/__PARENT__/g, parentIndex).replace(/__SUB__/g, subIndex);
        container.append(html);
    });

    // Add Nested Sub Field
    $(document).on('click', '.kmfb-add-nested-field', function() {
        var parentIndex = $(this).data('parent');
        var subIndex = $(this).data('sub');
        var container = $(this).siblings('.kmfb-nested-fields-container');
        var nestIndex = container.find('.kmfb-nested-field-wrap').length;
        var html = $('#kmfb-nested-field-template').html();
        html = html.replace(/__PARENT__/g, parentIndex).replace(/__SUB__/g, subIndex).replace(/__NEST__/g, nestIndex);
        container.append(html);
    });

    $(document).on('click', '.kmfb-remove-nested-field', function() {
        $(this).closest('.kmfb-nested-field-wrap').remove();
    });

    // Soft Delete for Sub-Field
    $(document).on('click', '.kmfb-remove-sub-field', function() {
        var wrap = $(this).closest('.kmfb-sub-field-wrap');
        wrap.addClass('kmfb-deleted');
        wrap.children().hide();
        wrap.find('input, select, textarea').prop('disabled', true);
        if (wrap.find('.kmfb-undo-sub-box').length === 0) {
            wrap.append('<div class="kmfb-undo-sub-box" style="padding:10px; background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; border-radius:3px; display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;"><span>Sub-field deleted.</span> <button type="button" class="button button-small kmfb-undo-sub-btn">Undo</button></div>');
        } else {
            wrap.find('.kmfb-undo-sub-box').show();
        }
        validateUniqueSlugs();
    });

    // Undo Sub-Field
    $(document).on('click', '.kmfb-undo-sub-btn', function(e) {
        e.preventDefault();
        var wrap = $(this).closest('.kmfb-sub-field-wrap');
        wrap.removeClass('kmfb-deleted');
        wrap.find('.kmfb-undo-sub-box').hide();
        wrap.children().not('.kmfb-undo-sub-box').show();
        wrap.find('input, select, textarea').prop('disabled', false);
        $('.kmfb-input-type').trigger('change');
        validateUniqueSlugs();
    });

    // Copy Slug from Field Body
    $(document).on('click', '.kmfb-copy-name', function(e) {
        e.preventDefault();
        var slug = $(this).closest('.kmfb-field-row').find('.kmfb-input-name').val();
        if(!slug) { alert('Slug is empty!'); return; }
        navigator.clipboard.writeText(slug);
    });

    $(document).on('change', '.kmfb-loc-param', function() {
        var param = $(this).val();
        $('.kmfb-loc-value').hide().prop('disabled', true);
        $('.kmfb-loc-value[data-param="' + param + '"]').show().prop('disabled', false);
    });
});