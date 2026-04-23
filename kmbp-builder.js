/* Kamaliya Meta Fields Builder - Admin Builder Script */
jQuery(document).ready(function($) {
    var fieldIndex = (typeof kmbpBuilderData !== 'undefined') ? kmbpBuilderData.fieldIndex : 0;

    // DRAG AND DROP (SORTABLE) ENGINE
    function kmbInitSortable() {
        $('#kmb-fields-container').sortable({
            handle: '.kmb-field-header',
            axis: 'y', opacity: 0.8,
            update: function(event, ui) { kmbReindexAllFields(); }
        });
        $('.kmb-sub-fields-container').sortable({
            handle: '.kmb-sub-field-header',
            axis: 'y', opacity: 0.8,
            update: function(event, ui) { kmbReindexAllFields(); }
        });
    }
    kmbInitSortable();

    function kmbReindexAllFields() {
        $('#kmb-fields-container > .kmb-field-wrap').each(function(mainIndex) {
            var mainWrap = $(this);
            mainWrap.attr('data-index', mainIndex);
            mainWrap.find('[name^="kmb_fields["]').each(function() {
                var name = $(this).attr('name');
                if(name) $(this).attr('name', name.replace(/^kmb_fields\[\d+\]/, 'kmb_fields[' + mainIndex + ']'));
            });
            mainWrap.find('.kmb-sub-fields-container > .kmb-sub-field-wrap').each(function(subIndex) {
                var subWrap = $(this);
                subWrap.find('[name*="[sub_fields]"]').each(function() {
                    var subName = $(this).attr('name');
                    if(subName) $(this).attr('name', subName.replace(/\[sub_fields\]\[\d+\]/, '[sub_fields][' + subIndex + ']'));
                });
                subWrap.find('.kmb-nested-fields-container > .kmb-nested-field-wrap').each(function(nestIndex) {
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
        $('.kmb-input-name').each(function() {
            var val = $(this).val().trim();
            $(this).css('border-color', '#8c8f94');
            $(this).next('.kmb-slug-error').remove();
            if(val !== '') {
                if(slugs[val]) {
                    $(this).css('border-color', '#d63638');
                    $(this).after('<div class="kmb-slug-error" style="color:#d63638; font-size:12px; margin-top:4px;">Error: Field Name (Slug) must be unique!</div>');
                } else {
                    slugs[val] = true;
                }
            }
        });
    }

    $('#kmb-add-field').on('click', function() {
        var html = $('#kmb-field-template').html().replace(/__INDEX__/g, fieldIndex);
        $('#kmb-fields-container').append(html);
        fieldIndex++;
        kmbInitSortable();
    });

    $(document).on('click', '.kmb-field-header, .kmb-close-field', function() {
        $(this).closest('.kmb-field-wrap').find('.kmb-field-body').slideToggle(200);
    });

    $(document).on('click', '.kmb-sub-field-header', function() {
        $(this).closest('.kmb-sub-field-wrap').find('.kmb-sub-field-body').slideToggle(200);
    });

    // Soft Delete for Main Field
    $(document).on('click', '.kmb-remove-field', function(e) {
        e.stopPropagation();
        var wrap = $(this).closest('.kmb-field-wrap');
        wrap.addClass('kmb-deleted');
        wrap.children('.kmb-field-header, .kmb-field-body').hide();
        wrap.find('input, select, textarea').prop('disabled', true);
        if (wrap.find('.kmb-undo-box').length === 0) {
            wrap.append('<div class="kmb-undo-box" style="padding:15px; background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; border-radius:3px; display:flex; justify-content:space-between; align-items:center;"><span>Field temporarily deleted.</span> <button type="button" class="button kmb-undo-btn">Undo</button></div>');
        } else {
            wrap.find('.kmb-undo-box').show();
        }
        validateUniqueSlugs();
    });

    // Undo Button Logic (Main Field)
    $(document).on('click', '.kmb-undo-btn', function(e) {
        e.preventDefault();
        var wrap = $(this).closest('.kmb-field-wrap');
        wrap.removeClass('kmb-deleted');
        wrap.find('.kmb-undo-box').hide();
        wrap.children('.kmb-field-header').show();
        wrap.children('.kmb-field-body').show();
        wrap.find('input, select, textarea').prop('disabled', false);
        $('.kmb-input-type').trigger('change');
        validateUniqueSlugs();
    });

    // Tab-based field type limiting
    $(document).on('change', '.kmb-input-tab', function() {
        var wrap = $(this).closest('.kmb-field-wrap');
        var tabValue = $(this).val();
        wrap.find('> .kmb-field-header .hdr-tab-badge').text(tabValue);
        var typeDropdown = wrap.find('> .kmb-field-body > .kmb-field-row .kmb-input-type').first();
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

    setTimeout(function() { $('.kmb-input-tab').trigger('change'); }, 200);

    // Lock existing fields on page load
    setTimeout(function() {
        $('.kmb-input-name').each(function() {
            if ($(this).val().trim() !== '') {
                $(this).addClass('kmb-slug-locked');
            }
        });
    }, 100);

    // Lock slug if user manually types
    $(document).on('keyup input', '.kmb-input-name', function() {
        $(this).addClass('kmb-slug-locked');
        var wrap = $(this).closest('.kmb-field-wrap');
        if($(this).closest('.kmb-sub-field-wrap').length === 0) {
            wrap.find('> .kmb-field-header .hdr-name').text($(this).val() || 'new_field');
        }
        validateUniqueSlugs();
    });

    // Main Field Label Sync
    $(document).on('keyup input', '.kmb-input-label', function() {
        var wrap = $(this).closest('.kmb-field-wrap');
        var slugInput = wrap.find('> .kmb-field-body > .kmb-field-row .kmb-input-name').not('.kmb-sub-name');
        if (!slugInput.hasClass('kmb-slug-locked')) {
            var slug = $(this).val().toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/(^_|_$)/g, '');
            slugInput.val(slug);
            wrap.find('> .kmb-field-header .hdr-name').text(slug || 'new_field');
            validateUniqueSlugs();
        }
        wrap.find('> .kmb-field-header .hdr-label').text($(this).val() || 'New Field');
    });

    // Sub Field Label Sync
    $(document).on('keyup input', '.kmb-sub-label', function() {
        var wrap = $(this).closest('.kmb-sub-field-wrap');
        var slugInput = wrap.find('.kmb-sub-name').first();
        if (!slugInput.hasClass('kmb-slug-locked')) {
            var slug = $(this).val().toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/(^_|_$)/g, '');
            slugInput.val(slug);
            validateUniqueSlugs();
        }
        wrap.find('.kmb-sub-field-header .s-title').text($(this).val() || 'New Sub Field');
    });

    // Header Slug Copy
    $(document).on('click', '.kmb-copy-header-slug', function(e) {
        e.stopPropagation();
        var wrap = $(this).closest('.kmb-field-wrap');
        var textToCopy = wrap.find('> .kmb-field-body .kmb-input-name').val();
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
    $(document).on('change', '.kmb-input-type', function() {
        var val = $(this).val();
        var isSubField = $(this).closest('.kmb-sub-field-wrap').length > 0;
        var wrap = isSubField ? $(this).closest('.kmb-sub-field-wrap') : $(this).closest('.kmb-field-wrap');
        if (!isSubField) {
            wrap.find('> .kmb-field-header .hdr-type').text($(this).find('option:selected').text());
        }
        var defaultRow = isSubField ? wrap.find('.kmb-sub-row-default-val') : wrap.find('.kmb-row-default-val');
        var subRow = wrap.find('.kmb-row-sub-fields');
        var nestedRow = wrap.find('.kmb-sub-row-nested-fields');
        if(val === 'repeater' || val === 'group') {
            defaultRow.hide(); subRow.show();
            if(isSubField) nestedRow.show();
            defaultRow.find('.kmb-input-default').prop('disabled', true);
        } else if (val === 'link' || val === 'menu') {
            defaultRow.hide();
            if(!isSubField) subRow.hide();
            if(isSubField) nestedRow.hide();
            defaultRow.find('.kmb-input-default').prop('disabled', true);
        } else {
            defaultRow.show();
            if(!isSubField) subRow.hide();
            if(isSubField) nestedRow.hide();
            defaultRow.find('.kmb-input-default, .kmb-default-image, .kmb-choices-wrap, .kmb-default-file').addClass('kmb-hidden');
            defaultRow.find('.kmb-input-default, .kmb-image-url, .kmb-image-alt, .kmb-image-title, .kmb-choices-wrap textarea, .kmb-file-url').prop('disabled', true);
            if(val === 'text') {
                defaultRow.find('.kmb-default-text').removeClass('kmb-hidden').prop('disabled', false);
            } else if (val === 'textarea' || val === 'embed') {
                defaultRow.find('.kmb-default-textarea').removeClass('kmb-hidden').prop('disabled', false);
            } else if (val === 'number') {
                defaultRow.find('.kmb-default-number').removeClass('kmb-hidden').prop('disabled', false);
            } else if (val === 'image') {
                defaultRow.find('.kmb-default-image').removeClass('kmb-hidden');
                defaultRow.find('.kmb-image-url, .kmb-image-alt, .kmb-image-title').prop('disabled', false);
            } else if (val === 'color') {
                defaultRow.find('.kmb-default-color').removeClass('kmb-hidden').prop('disabled', false);
            } else if (val === 'boolean') {
                defaultRow.find('.kmb-default-boolean').removeClass('kmb-hidden').prop('disabled', false);
            } else if (val === 'select') {
                defaultRow.find('.kmb-choices-wrap').removeClass('kmb-hidden');
                defaultRow.find('.kmb-choices-wrap textarea, .kmb-default-select').prop('disabled', false);
            } else if (val === 'file') {
                defaultRow.find('.kmb-default-file').removeClass('kmb-hidden');
                defaultRow.find('.kmb-file-url').prop('disabled', false);
            }
        }
    });

    // IMAGE UPLOADER WITH META DATA
    $(document).on('click', '.kmb-media-btn', function(e) {
        e.preventDefault();
        var button = $(this);
        var wrapper = button.closest('.kmb-default-image');
        var inputUrl = wrapper.find('.kmb-image-url');
        var inputAlt = wrapper.find('.kmb-image-alt');
        var inputTitle = wrapper.find('.kmb-image-title');
        var previewWrap = wrapper.find('.kmb-image-preview-wrap');
        var previewImg = wrapper.find('.kmb-image-preview');
        var metaTitle = wrapper.find('.meta-title');
        var metaAlt = wrapper.find('.meta-alt');
        var removeBtn = wrapper.find('.kmb-remove-image-btn');
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

    $(document).on('click', '.kmb-remove-image-btn', function() {
        var wrapper = $(this).closest('.kmb-default-image');
        wrapper.find('input[type="hidden"]').val('');
        wrapper.find('.kmb-image-preview-wrap').hide();
        wrapper.find('.kmb-image-preview').attr('src', '');
        $(this).hide();
        wrapper.find('.kmb-media-btn').text('Select Image');
    });

    // File Uploader
    $(document).on('click', '.kmb-media-file-btn', function(e) {
        e.preventDefault();
        var inputUrl = $(this).siblings('.kmb-file-url');
        var customUploader = wp.media({ title: 'Select File', button: { text: 'Use this file' }, multiple: false });
        customUploader.on('select', function() {
            var attachment = customUploader.state().get('selection').first().toJSON();
            inputUrl.val(attachment.url);
        });
        customUploader.open();
    });

    // Add Sub Field
    $(document).on('click', '.kmb-add-sub-field', function() {
        var parentIndex = $(this).data('parent');
        var container = $(this).siblings('.kmb-sub-fields-container');
        var subIndex = container.find('.kmb-sub-field-wrap').length;
        var html = $('#kmb-sub-field-template').html();
        html = html.replace(/__PARENT__/g, parentIndex).replace(/__SUB__/g, subIndex);
        container.append(html);
    });

    // Add Nested Sub Field
    $(document).on('click', '.kmb-add-nested-field', function() {
        var parentIndex = $(this).data('parent');
        var subIndex = $(this).data('sub');
        var container = $(this).siblings('.kmb-nested-fields-container');
        var nestIndex = container.find('.kmb-nested-field-wrap').length;
        var html = $('#kmb-nested-field-template').html();
        html = html.replace(/__PARENT__/g, parentIndex).replace(/__SUB__/g, subIndex).replace(/__NEST__/g, nestIndex);
        container.append(html);
    });

    $(document).on('click', '.kmb-remove-nested-field', function() {
        $(this).closest('.kmb-nested-field-wrap').remove();
    });

    // Soft Delete for Sub-Field
    $(document).on('click', '.kmb-remove-sub-field', function() {
        var wrap = $(this).closest('.kmb-sub-field-wrap');
        wrap.addClass('kmb-deleted');
        wrap.children().hide();
        wrap.find('input, select, textarea').prop('disabled', true);
        if (wrap.find('.kmb-undo-sub-box').length === 0) {
            wrap.append('<div class="kmb-undo-sub-box" style="padding:10px; background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; border-radius:3px; display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;"><span>Sub-field deleted.</span> <button type="button" class="button button-small kmb-undo-sub-btn">Undo</button></div>');
        } else {
            wrap.find('.kmb-undo-sub-box').show();
        }
        validateUniqueSlugs();
    });

    // Undo Sub-Field
    $(document).on('click', '.kmb-undo-sub-btn', function(e) {
        e.preventDefault();
        var wrap = $(this).closest('.kmb-sub-field-wrap');
        wrap.removeClass('kmb-deleted');
        wrap.find('.kmb-undo-sub-box').hide();
        wrap.children().not('.kmb-undo-sub-box').show();
        wrap.find('input, select, textarea').prop('disabled', false);
        $('.kmb-input-type').trigger('change');
        validateUniqueSlugs();
    });

    // Copy Slug from Field Body
    $(document).on('click', '.kmb-copy-name', function(e) {
        e.preventDefault();
        var slug = $(this).closest('.kmb-field-row').find('.kmb-input-name').val();
        if(!slug) { alert('Slug is empty!'); return; }
        navigator.clipboard.writeText(slug);
    });

    $(document).on('change', '.kmb-loc-param', function() {
        var param = $(this).val();
        $('.kmb-loc-value').hide().prop('disabled', true);
        $('.kmb-loc-value[data-param="' + param + '"]').show().prop('disabled', false);
    });
});
