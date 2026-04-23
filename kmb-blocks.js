(function(wp) {
    try {
        var el = wp.element.createElement;
        var useState = wp.element.useState;
        var Fragment = wp.element.Fragment;
        var registerBlockType = wp.blocks.registerBlockType;
        var ServerSideRender = wp.serverSideRender; 
        
        var InspectorControls = wp.blockEditor.InspectorControls;
    
        var MediaUpload = wp.blockEditor.MediaUpload;
        var URLInput = wp.blockEditor.URLInput; 
        var BlockControls = wp.blockEditor.BlockControls; 
        
        var PanelBody = wp.components.PanelBody;
        var TextControl = wp.components.TextControl;
        var TextareaControl = wp.components.TextareaControl;
        var Button = wp.components.Button;
        var TabPanel = wp.components.TabPanel; 
        var ColorPalette = wp.components.ColorPalette;
        var ToggleControl = wp.components.ToggleControl;
        var SelectControl = wp.components.SelectControl;
        var ToolbarGroup = wp.components.ToolbarGroup; 
        var ToolbarButton = wp.components.ToolbarButton; 
        var Modal = wp.components.Modal; 

if (typeof kmbBlocksData === 'undefined' || !kmbBlocksData.length) return;

        // Custom Collapsible Row Component for Repeaters
        var CollapsibleRowUI = function(props) {
            var [isOpen, setIsOpen] = useState(false);
            return el('div', { style: { border: '1px solid #ccd0d4', marginBottom: '10px', background: '#fff', borderRadius: '4px', overflow: 'hidden' } },
                el('div', { 
                    style: { padding: '10px 15px', background: '#f6f7f7', display: 'flex', justifyContent: 'space-between', alignItems: 'center', cursor: 'pointer', borderBottom: isOpen ? '1px solid #ccd0d4' : 'none' },
                    onClick: function() { setIsOpen(!isOpen); }
                },
                    el('strong', { style: { fontSize: '13px' } }, props.title),
                    el('div', { style: { display: 'flex', gap: '10px', alignItems: 'center' } },
                        el('div', { onClick: function(e) { e.stopPropagation(); } }, props.actionButton),
                        el('span', { className: isOpen ? 'dashicons dashicons-arrow-up-alt2' : 'dashicons dashicons-arrow-down-alt2', style: { color: '#50575e' } })
                    )
                ),
                isOpen ? el('div', { style: { padding: '15px' } }, props.children) : null
            );
        };

        // Master React Component for Link UI
        var LinkControlUI = function(props) {
            var linkData = { url: '', title: '', target: '' };
            try {
                var parsed = typeof props.value === 'string' ? JSON.parse(props.value) : props.value;
                if (parsed && typeof parsed === 'object') linkData = parsed;
            } catch(e) {}

            var [isEditing, setIsEditing] = useState(!linkData.url);

            var updateData = function(newVals) {
                var newD = Object.assign({}, linkData, newVals);
                props.onChange(JSON.stringify(newD));
            };

            if (!isEditing) {
                return el('div', { style: { border: '1px solid #ccd0d4', padding: '10px 12px', background: '#fff', borderRadius: '3px', display: 'flex', alignItems: 'center', gap: '15px', marginBottom: '15px' } },
                    el('span', { style: { fontWeight: 600, fontSize: '13px' } }, linkData.title || '(No Title)'),
                    el('span', { style: { color: '#2271b1', fontSize: '12px', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis', maxWidth: '140px' } }, linkData.url),
                    el('div', { style: { marginLeft: 'auto', display: 'flex', gap: '5px' } },
                        el(Button, { isSmall: true, icon: 'edit', title: 'Edit', onClick: function(e) { if(e) e.stopPropagation(); setIsEditing(true); } }),
                        el(Button, { isSmall: true, icon: 'no-alt', title: 'Remove', style: { color: '#d63638' }, onClick: function(e) { if(e) e.stopPropagation(); updateData({url: '', title: '', target: ''}); setIsEditing(true); } }) // Added stopPropagation to prevent crash
                    )
                );
            }

            return el('div', { className: 'kmb-link-control-edit-wrap', style: { marginBottom: '15px', padding: '15px', border: '1px solid #c3c4c7', borderRadius: '3px', background: '#f6f7f7', position: 'relative' } },
                el('label', { style: { display: 'block', marginBottom: '12px', fontWeight: 600 } }, props.label),
                
                el('div', { style: { marginBottom: '12px' } },
                    el('label', { style: { display: 'block', fontSize: '11px', marginBottom: '6px', color: '#50575e', textTransform: 'uppercase', fontWeight: 600 } }, 'Search or Paste URL'),
                    el('div', { style: { border: '1px solid #8c8f94', borderRadius: '2px', background: '#fff', overflow: 'hidden', maxWidth: '100%' } },
                        el(URLInput, {
                            className: 'kmb-url-input-field',
                            value: linkData.url || '',
                            onChange: function(url, post) {
                                var newD = Object.assign({}, linkData, {url: url});
                                if (post && post.title) newD.title = post.title;
                                updateData(newD);
                            }
                        })
                    )
                ),

                el(TextControl, { label: 'Link Text', value: linkData.title || '', onChange: function(v) { updateData({title: v}); } }),
                el(ToggleControl, { label: 'Open in new tab', checked: linkData.target === '_blank', onChange: function(v) { updateData({target: v ? '_blank' : ''}); } }),
                el(Button, { isPrimary: true, isSmall: true, style: { marginTop: '5px' }, onClick: function(e) { if(e) e.stopPropagation(); setIsEditing(false); } }, 'Apply Link')
            );
        };

        kmbBlocksData.forEach(function(blockData) {
            var fieldsList = Array.isArray(blockData.fields) ? blockData.fields : Object.values(blockData.fields || {});
          var blockAttributes = { 
                kmb_custom_class: { type: 'string', default: '' },
                className: { type: 'string', default: '' },
                align: { type: 'string', default: '' }
            };
            
    fieldsList.forEach(function(field) {
                if (!field.name) return; // NAYA: Khali slug ko block hone se rokein
                
                var defVal = field.default_value;
                if (typeof defVal === 'object' || Array.isArray(defVal)) defVal = JSON.stringify(defVal); 
                else defVal = defVal || ''; 
                
                blockAttributes[field.name] = { type: 'string', default: defVal };
            });
            
            // Bypass strict schema validation for native Gutenberg properties
            blockAttributes['className'] = { type: 'string', default: '' };
            blockAttributes['align'] = { type: 'string', default: '' };
            blockAttributes['lock'] = { type: 'object', default: {} };

          registerBlockType(blockData.name, {
                title: blockData.title,
                icon: 'layout',
                category: 'design',
                attributes: blockAttributes,
                supports: { 
                    customClassName: false,
                    align: ['wide', 'full'] // NAYA: Toolbar mein Wide/Full options ke liye
                },

                edit: function(props) {
                    var attributes = props.attributes;
                    var setAttributes = props.setAttributes;
                    var modalState = useState(false);
                    var isModalOpen = modalState[0];
                    var setIsModalOpen = modalState[1];
                    
                    var contentFields = [];
                    var styleFields = [];

                    fieldsList.forEach(function(field) {
                        var fName = field.name;
                        var fLabel = field.label;
                        var fTab = field.tab || 'content'; 
                        var fieldUI = null;

                      if (field.type === 'text') {
                            fieldUI = el(TextControl, { label: fLabel, value: attributes[fName], onChange: function(v) { var u={}; u[fName]=v; setAttributes(u); } });
                        } else if (field.type === 'number') {
                            fieldUI = el(TextControl, { type: 'number', label: fLabel, value: attributes[fName], onChange: function(v) { var u={}; u[fName]=v; setAttributes(u); } });
                        }
                        
                       else if (field.type === 'textarea') {
                            fieldUI = el(TextareaControl, { label: fLabel, value: attributes[fName], onChange: function(v) { var u={}; u[fName]=v; setAttributes(u); } });
                        } else if (field.type === 'embed') {
                            fieldUI = el(TextareaControl, { label: fLabel + ' (Embed Code)', value: attributes[fName], onChange: function(v) { var u={}; u[fName]=v; setAttributes(u); }, style: { fontFamily: 'monospace', background: '#f6f7f7' } });
                        } else if (field.type === 'color') {
                            fieldUI = el('div', { style: { marginBottom: '15px' } }, el('label', { style: { display: 'block', marginBottom: '8px', fontWeight: 500 } }, fLabel), el(ColorPalette, { value: attributes[fName], onChange: function(v) { var u={}; u[fName]=v; setAttributes(u); } }));
                       } else if (field.type === 'boolean') {
                            fieldUI = el('div', { style: { marginBottom: '15px' } }, el(ToggleControl, { label: fLabel, checked: attributes[fName] === 'true' || attributes[fName] === true, onChange: function(v) { var u={}; u[fName] = v ? 'true' : 'false'; setAttributes(u); } }));
                        } else if (field.type === 'select') {
                            var choicesArr = [];
                            if (field.choices) {
                                field.choices.split('\n').forEach(function(line) {
                                    var parts = line.split(':');
                                    if(parts.length > 0) {
                                        var val = parts[0].trim();
                                        choicesArr.push({ label: parts.length > 1 ? parts[1].trim() : val, value: val });
                                    }
                                });
                            }
                            fieldUI = el('div', { style: { marginBottom: '15px' } }, el(SelectControl, { label: fLabel, value: attributes[fName], options: choicesArr, onChange: function(v) { var u={}; u[fName]=v; setAttributes(u); } }));
                    } else if (field.type === 'image') {
                            fieldUI = el('div', { style: { marginBottom: '15px' } },
                                el('label', { style: { display: 'block', marginBottom: '5px', fontWeight: 500 } }, fLabel),
                                el(MediaUpload, {
                                    onSelect: function(m) { var u={}; u[fName]=m.url; setAttributes(u); },
                                    type: 'image',
                                    value: attributes[fName],
                                    render: function(obj) {
                                        return el('div', null,
                                            attributes[fName] ? el('img', { src: attributes[fName], style: { maxWidth: '120px', height: 'auto', display: 'block', marginBottom: '8px', border: '1px solid #ccc', padding: '3px', borderRadius: '3px', background: '#fff' } }) : null,
                                            el('div', {style: {display: 'flex', gap: '5px'}},
                                                el(Button, { isSecondary: true, isSmall: true, onClick: obj.open }, attributes[fName] ? 'Change Image' : 'Select Image'),
                                                attributes[fName] ? el(Button, { isDestructive: true, isSmall: true, onClick: function() { var u={}; u[fName]=''; setAttributes(u); } }, 'Remove') : null
                                            )
                                        );
                                    }
                                })
                            );
                        } else if (field.type === 'file') {
                            fieldUI = el('div', { style: { marginBottom: '15px' } },
                                el('label', { style: { display: 'block', marginBottom: '5px', fontWeight: 500 } }, fLabel),
                                el(MediaUpload, {
                                    onSelect: function(m) { var u={}; u[fName]=m.url; setAttributes(u); },
                                    value: attributes[fName],
                                    render: function(obj) { 
                                        return el('div', null, 
                                            attributes[fName] ? el('p', {style: {wordBreak:'break-all', fontSize:'12px', color:'#555', margin:'0 0 5px 0'}}, attributes[fName]) : null, 
                                            el('div', {style: {display: 'flex', gap: '5px'}},
                                                el(Button, { isSecondary: true, isSmall: true, onClick: obj.open }, attributes[fName] ? 'Change File' : 'Select File'),
                                                attributes[fName] ? el(Button, { isDestructive: true, isSmall: true, onClick: function() { var u={}; u[fName]=''; setAttributes(u); } }, 'Remove') : null
                                            )
                                        ); 
                                    }
                                })
                            );
                        } else if (field.type === 'menu') {
                            // Automatically populate dropdown with WP Menus
                            var menuChoices = (typeof kmbGlobalData !== 'undefined' && kmbGlobalData.menus) ? kmbGlobalData.menus : [{label: 'No Menus Found', value: ''}];
                            fieldUI = el('div', { style: { marginBottom: '15px' } }, 
                                el(SelectControl, { 
                                    label: fLabel, 
                                    value: attributes[fName], 
                                    options: menuChoices, 
                                    onChange: function(v) { var u={}; u[fName]=v; setAttributes(u); } 
                                })
                            );
                        
                        }
                        else if (field.type === 'link') {
                            fieldUI = el(LinkControlUI, { label: fLabel, value: attributes[fName], onChange: function(newVal) { var u={}; u[fName]=newVal; setAttributes(u); } });
                       } else if (field.type === 'group') {
                            // --- NAYA: GROUP FIELD LOGIC ---
                            var groupData = {};
                            try { groupData = attributes[fName] ? JSON.parse(attributes[fName]) : {}; } catch(e) { groupData = {}; }
                            var subFieldsList = Array.isArray(field.sub_fields) ? field.sub_fields : Object.values(field.sub_fields || {});

                            var groupElements = subFieldsList.map(function(subField, subIndex) {
                                var sName = subField.name;
                                var sLabel = subField.label;
                                var sVal = groupData[sName] !== undefined ? groupData[sName] : '';

                                var updateSubField = function(newVal) {
                                    var newGroupData = Object.assign({}, groupData);
                                    newGroupData[sName] = newVal;
                                    var u = {}; u[fName] = JSON.stringify(newGroupData);
                                    setAttributes(u);
                                };

                               if (subField.type === 'text') {
                                    return el(TextControl, { key: subIndex, label: sLabel, value: sVal, onChange: updateSubField });
                                } else if (subField.type === 'number') {
                                    return el(TextControl, { type: 'number', key: subIndex, label: sLabel, value: sVal, onChange: updateSubField });
                        } else if (subField.type === 'textarea') {
                                    return el(TextareaControl, { key: subIndex, label: sLabel, value: sVal, onChange: updateSubField });
                                } else if (subField.type === 'embed') {
                                    return el(TextareaControl, { key: subIndex, label: sLabel + ' (Embed Code)', value: sVal, onChange: updateSubField, style: { fontFamily: 'monospace', background: '#f6f7f7' } });
                                } else if (subField.type === 'color') {
                                    return el('div', {key: subIndex, style:{marginBottom:'10px'}}, el('label', {style:{display:'block',marginBottom:'5px'}}, sLabel), el(ColorPalette, { value: sVal, onChange: updateSubField }));
                                } else if (subField.type === 'boolean') {
                                    return el(ToggleControl, { key: subIndex, label: sLabel, checked: sVal === 'true' || sVal === true, onChange: function(v) { updateSubField(v ? 'true' : 'false'); } });
                                } else if (subField.type === 'select') { 
                                    var sChoicesArr = [];
                                    if (subField.choices) {
                                        subField.choices.split('\n').forEach(function(line) {
                                            var parts = line.split(':');
                                            if(parts.length > 0) { var val = parts[0].trim(); sChoicesArr.push({ label: parts.length > 1 ? parts[1].trim() : val, value: val }); }
                                        });
                                    }
                                    return el(SelectControl, { key: subIndex, label: sLabel, value: sVal, options: sChoicesArr, onChange: updateSubField });
} else if (subField.type === 'image') {
                                    return el('div', {key: subIndex, style:{marginBottom:'15px'}},
                                        el('label', {style:{display:'block',marginBottom:'5px', fontWeight:500}}, sLabel),
                                        el(MediaUpload, {
                                            onSelect: function(m) { updateSubField(m.url); },
                                            type: 'image',
                                            value: sVal,
                                            render: function(obj) {
                                                return el('div', null,
                                                    sVal ? el('img', { src: sVal, style: { maxWidth: '120px', height: 'auto', display: 'block', marginBottom: '8px', border: '1px solid #ccc', padding: '3px', borderRadius: '3px', background: '#fff' } }) : null,
                                                    el('div', {style: {display: 'flex', gap: '5px'}},
                                                        el(Button, { isSecondary: true, isSmall: true, onClick: obj.open }, sVal ? 'Change Image' : 'Select Image'),
                                                        sVal ? el(Button, { isDestructive: true, isSmall: true, onClick: function() { updateSubField(''); } }, 'Remove') : null
                                                    )
                                                );
                                            }
                                        })
                                    );
                                } else if (subField.type === 'file') {
                                    return el('div', {key: subIndex, style:{marginBottom:'10px'}}, 
                                        el('label', {style:{display:'block',marginBottom:'5px', fontWeight:500}}, sLabel), 
                                        el(MediaUpload, { 
                                            onSelect: function(m) { updateSubField(m.url); }, 
                                            value: sVal, 
                                            render: function(obj) { 
                                                return el('div', null, 
                                                    sVal ? el('p', {style: {wordBreak:'break-all', fontSize:'12px', color:'#555', margin:'0 0 5px 0'}}, sVal) : null, 
                                                    el('div', {style: {display: 'flex', gap: '5px'}},
                                                        el(Button, { isSecondary: true, isSmall: true, onClick: obj.open }, sVal ? 'Change File' : 'Select File'),
                                                        sVal ? el(Button, { isDestructive: true, isSmall: true, onClick: function() { updateSubField(''); } }, 'Remove') : null
                                                    )
                                                ); 
                                            } 
                                        })
                                    );
                                } else if (subField.type === 'link') {
                                    return el(LinkControlUI, { key: subIndex, label: sLabel, value: sVal, onChange: function(val) { var parsed = val; try { parsed = typeof val === 'string' ? JSON.parse(val) : val; } catch(e) {} updateSubField(parsed); } });
                                } else if (subField.type === 'menu') {
                                    var menuChoices = (typeof kmbGlobalData !== 'undefined' && kmbGlobalData.menus) ? kmbGlobalData.menus : [{label: 'No Menus Found', value: ''}];
                                    return el('div', { key: subIndex, style: { marginBottom: '10px' } }, el(SelectControl, { label: sLabel, value: sVal, options: menuChoices, onChange: updateSubField }));
                                }
                                return null;
                            });

                            // Group ko CollapsibleRow mein daal diya, taaki cleanly close/open ho sake (without delete button)
                            fieldUI = el(CollapsibleRowUI, { title: fLabel + ' (Group)', actionButton: null }, el('div', { style: { padding: '5px' } }, groupElements));

                        } else if (field.type === 'repeater') {
                            var rows = [];
                            try { rows = attributes[fName] ? JSON.parse(attributes[fName]) : []; } catch(e) { rows = []; }
                            if (!Array.isArray(rows)) rows = [];

                            var rowsUI = rows.map(function(row, rowIndex) {
                                if(!row) row = {}; // Safety check to prevent crash
                                var subFieldsList = Array.isArray(field.sub_fields) ? field.sub_fields : Object.values(field.sub_fields || {});

                                var subFieldsElements = subFieldsList.map(function(subField, subIndex) {
                                    var sName = subField.name;
                                    var sLabel = subField.label;
                                    var sVal = row[sName] !== undefined ? row[sName] : '';

                                    var updateSubField = function(newVal) {
                                        var newRows = JSON.parse(JSON.stringify(rows));
                                        newRows[rowIndex][sName] = newVal;
                                        var u = {}; u[fName] = JSON.stringify(newRows);
                                        setAttributes(u);
                                    };

                                    if (subField.type === 'text') {
                                        return el(TextControl, { key: subIndex, label: sLabel, value: sVal, onChange: updateSubField });
                                    } else if (subField.type === 'number') {
                                        return el(TextControl, { type: 'number', key: subIndex, label: sLabel, value: sVal, onChange: updateSubField });
                                    } else if (subField.type === 'textarea') {
                                        return el(TextareaControl, { key: subIndex, label: sLabel, value: sVal, onChange: updateSubField });
                                    } else if (subField.type === 'color') {
                                        return el('div', {key: subIndex, style:{marginBottom:'10px'}}, el('label', {style:{display:'block',marginBottom:'5px'}}, sLabel), el(ColorPalette, { value: sVal, onChange: updateSubField }));
                                    } else if (subField.type === 'boolean') {
                                        return el(ToggleControl, { key: subIndex, label: sLabel, checked: sVal === 'true' || sVal === true, onChange: function(v) { updateSubField(v ? 'true' : 'false'); } });
                                    } else if (subField.type === 'select') { 
                                        var sChoicesArr = [];
                                        if (subField.choices) {
                                            subField.choices.split('\n').forEach(function(line) {
                                                var parts = line.split(':');
                                                if(parts.length > 0) {
                                                    var val = parts[0].trim();
                                                    sChoicesArr.push({ label: parts.length > 1 ? parts[1].trim() : val, value: val });
                                                }
                                            });
                                        }
                                        return el(SelectControl, { key: subIndex, label: sLabel, value: sVal, options: sChoicesArr, onChange: updateSubField });
                                    } else if (subField.type === 'image') {
                                        return el('div', {key: subIndex, style:{marginBottom:'15px'}},
                                            el('label', {style:{display:'block',marginBottom:'5px', fontWeight:500}}, sLabel),
                                            el(MediaUpload, {
                                                onSelect: function(m) { updateSubField(m.url); },
                                                type: 'image',
                                                value: sVal,
                                                render: function(obj) {
                                                    return el('div', null,
                                                        sVal ? el('img', { src: sVal, style: { maxWidth: '120px', height: 'auto', display: 'block', marginBottom: '8px', border: '1px solid #ccc', padding: '3px', borderRadius: '3px', background: '#fff' } }) : null,
                                                        el('div', {style: {display: 'flex', gap: '5px'}},
                                                            el(Button, { isSecondary: true, isSmall: true, onClick: obj.open }, sVal ? 'Change Image' : 'Select Image'),
                                                            sVal ? el(Button, { isDestructive: true, isSmall: true, onClick: function() { updateSubField(''); } }, 'Remove') : null
                                                        )
                                                    );
                                                }
                                            })
                                        );
                                    } else if (subField.type === 'file') {
                                        return el('div', {key: subIndex, style:{marginBottom:'10px'}}, el('label', {style:{display:'block',marginBottom:'5px', fontWeight:500}}, sLabel), el(MediaUpload, { onSelect: function(m) { updateSubField(m.url); }, value: sVal, render: function(obj) { return el('div', null, sVal ? el('p', {style: {wordBreak:'break-all', fontSize:'12px', color:'#555', margin:'0 0 5px 0'}}, sVal) : null, el(Button, { isSecondary: true, isSmall: true, onClick: obj.open }, sVal ? 'Change File' : 'Select File')); } }));
                                  } else if (subField.type === 'link') {
                                        return el(LinkControlUI, { key: subIndex, label: sLabel, value: sVal, onChange: function(val) { var parsed = val; try { parsed = typeof val === 'string' ? JSON.parse(val) : val; } catch(e) {} updateSubField(parsed); } });
                                    } else if (subField.type === 'repeater') {
                                        
                                        var nestedRows = [];
                                        try { nestedRows = typeof sVal === 'string' && sVal ? JSON.parse(sVal) : sVal; } catch(e) { nestedRows = []; }
                                        if (!Array.isArray(nestedRows)) nestedRows = [];

                                        var nestedRowsUI = nestedRows.map(function(nRow, nIndex) {
                                            if(!nRow) nRow = {}; // Safety check
                                            var nFieldsList = Array.isArray(subField.nested_fields) ? subField.nested_fields : Object.values(subField.nested_fields || {});

                                            var nFieldsElements = nFieldsList.map(function(nField, nSubIndex) {
                                                var nName = nField.name;
                                                var nLabel = nField.label;
                                                var nVal = nRow[nName] !== undefined ? nRow[nName] : '';

                                       var updateNestedField = function(newVal) {
                                                    var newNRows = JSON.parse(JSON.stringify(nestedRows));
                                                    newNRows[nIndex][nName] = newVal;
                                                    updateSubField(newNRows); // ANTI-CORRUPTION: Pass as Native Array, NOT stringify!
                                                };

                                              if (nField.type === 'text') {
                                                    return el(TextControl, { key: nSubIndex, label: nLabel, value: nVal, onChange: updateNestedField });
                                                } else if (nField.type === 'number') {
                                                    return el(TextControl, { type: 'number', key: nSubIndex, label: nLabel, value: nVal, onChange: updateNestedField });
                                               } else if (nField.type === 'textarea') {
                                                    return el(TextareaControl, { key: nSubIndex, label: nLabel, value: nVal, onChange: updateNestedField });
                                                } else if (nField.type === 'embed') {
                                                    return el(TextareaControl, { key: nSubIndex, label: nLabel + ' (Embed)', value: nVal, onChange: updateNestedField, style: { fontFamily: 'monospace', background: '#f6f7f7' } });
                                               } else if (nField.type === 'image') {
                                                    return el('div', {key: nSubIndex, style:{marginBottom:'15px'}},
                                                        el('label', {style:{display:'block',marginBottom:'5px', fontWeight:500}}, nLabel),
                                                        el(MediaUpload, {
                                                            onSelect: function(m) { updateNestedField(m.url); },
                                                            type: 'image',
                                                            value: nVal,
                                                            render: function(obj) {
                                                                return el('div', null,
                                                                    nVal ? el('img', { src: nVal, style: { maxWidth: '120px', height: 'auto', display: 'block', marginBottom: '8px', border: '1px solid #ccc', padding: '3px', borderRadius: '3px', background: '#fff' } }) : null,
                                                                    el('div', {style: {display: 'flex', gap: '5px'}},
                                                                        el(Button, { isSecondary: true, isSmall: true, onClick: obj.open }, nVal ? 'Change Image' : 'Select Image'),
                                                                        nVal ? el(Button, { isDestructive: true, isSmall: true, onClick: function() { updateNestedField(''); } }, 'Remove') : null
                                                                    )
                                                                );
                                                            }
                                                        })
                                                    );
                                                } else if (nField.type === 'link') {
                                                    return el(LinkControlUI, { key: nSubIndex, label: nLabel, value: nVal, onChange: function(val) { var parsed = val; try { parsed = typeof val === 'string' ? JSON.parse(val) : val; } catch(e) {} updateNestedField(parsed); } });
                                                }
                                                return null;
                                            });

                                            // Smart Title Logic for Nested Rows (Max 20 chars)

                                            // Smart Title Logic for Nested Rows (Max 20 chars)
                                            var nRowTitle = 'Nested Item ' + (nIndex + 1);
                                            if (nFieldsList.length > 0) {
                                                var firstNField = nFieldsList[0];
                                                var firstNVal = nRow[firstNField.name];
                                                if (firstNVal) {
                                                    if (firstNField.type === 'text' || firstNField.type === 'textarea') {
                                                        nRowTitle = firstNVal;
                                                    } else if (firstNField.type === 'link') {
                                                        try {
                                                            var nLinkParsed = typeof firstNVal === 'string' ? JSON.parse(firstNVal) : firstNVal;
                                                            if (nLinkParsed && nLinkParsed.title) nRowTitle = nLinkParsed.title;
                                                        } catch(e) {}
                                                    }
                                                }
                                            }
                                            if (nRowTitle.length > 20) nRowTitle = nRowTitle.substring(0, 20) + '...';

                                  var moveUpNBtn = nIndex > 0 ? el(Button, { isSmall: true, icon: 'arrow-up-alt2', title: 'Move Up', onClick: function(e) { e.stopPropagation(); var newNRows = nestedRows.slice(); var temp = newNRows[nIndex-1]; newNRows[nIndex-1] = newNRows[nIndex]; newNRows[nIndex] = temp; updateSubField(newNRows); } }) : null;
                                            var moveDownNBtn = nIndex < nestedRows.length - 1 ? el(Button, { isSmall: true, icon: 'arrow-down-alt2', title: 'Move Down', onClick: function(e) { e.stopPropagation(); var newNRows = nestedRows.slice(); var temp = newNRows[nIndex+1]; newNRows[nIndex+1] = newNRows[nIndex]; newNRows[nIndex] = temp; updateSubField(newNRows); } }) : null;
                                            var nDeleteBtn = el(Button, { isDestructive: true, isSmall: true, onClick: function(e) { if(e) e.stopPropagation(); var newNRows = nestedRows.slice(); newNRows.splice(nIndex, 1); updateSubField(newNRows); } }, 'Delete');
                                            var nActionGroup = el('div', { style: { display: 'flex', gap: '5px' } }, moveUpNBtn, moveDownNBtn, nDeleteBtn);
                                            
                                            return el(CollapsibleRowUI, { key: nIndex, title: nRowTitle, actionButton: nActionGroup }, nFieldsElements);
                                        });

                                        return el('div', { key: subIndex, style: { padding: '10px', background: '#eef0f2', marginBottom: '10px', borderLeft: '3px solid #6c7781' } },
                                            el('strong', { style: { display: 'block', marginBottom: '8px', fontSize: '13px' } }, sLabel + ' (Nested)'),
                                            nestedRowsUI,
                                            el(Button, { isSecondary: true, isSmall: true, onClick: function(e) { if(e) e.stopPropagation(); var newNRows = nestedRows.slice(); newNRows.push({}); updateSubField(newNRows); } }, '+ Add Nested Row')
                                        );
                                    }
                                    return null;
                                });
                             
                     // Smart Title Logic for Main Rows (Max 20 chars)
                                var rowTitle = 'Row Item ' + (rowIndex + 1);
                                if (subFieldsList.length > 0) {
                                    var firstField = subFieldsList[0];
                                    var firstVal = row[firstField.name];
                                    if (firstVal) {
                                        if (firstField.type === 'text' || firstField.type === 'textarea') {
                                            rowTitle = firstVal;
                                        } else if (firstField.type === 'link') {
                                            try {
                                                var linkParsed = typeof firstVal === 'string' ? JSON.parse(firstVal) : firstVal;
                                                if (linkParsed && linkParsed.title) rowTitle = linkParsed.title;
                                            } catch(e) {}
                                        }
                                    }
                                }
                                if (rowTitle.length > 20) rowTitle = rowTitle.substring(0, 20) + '...';

                                var moveUpBtn = rowIndex > 0 ? el(Button, { isSmall: true, icon: 'arrow-up-alt2', title: 'Move Up', onClick: function(e) { e.stopPropagation(); var newRows = rows.slice(); var temp = newRows[rowIndex-1]; newRows[rowIndex-1] = newRows[rowIndex]; newRows[rowIndex] = temp; var u = {}; u[fName] = JSON.stringify(newRows); setAttributes(u); } }) : null;
                                var moveDownBtn = rowIndex < rows.length - 1 ? el(Button, { isSmall: true, icon: 'arrow-down-alt2', title: 'Move Down', onClick: function(e) { e.stopPropagation(); var newRows = rows.slice(); var temp = newRows[rowIndex+1]; newRows[rowIndex+1] = newRows[rowIndex]; newRows[rowIndex] = temp; var u = {}; u[fName] = JSON.stringify(newRows); setAttributes(u); } }) : null;
                                var deleteBtn = el(Button, { isDestructive: true, isSmall: true, onClick: function(e) { if(e) e.stopPropagation(); var newRows = rows.slice(); newRows.splice(rowIndex, 1); var u = {}; u[fName] = JSON.stringify(newRows); setAttributes(u); } }, 'Delete');
                                var actionGroup = el('div', { style: { display: 'flex', gap: '5px' } }, moveUpBtn, moveDownBtn, deleteBtn);
                                
                                return el(CollapsibleRowUI, { key: rowIndex, title: rowTitle, actionButton: actionGroup }, subFieldsElements);
                            });

                            fieldUI = el('div', { style: { padding: '15px', background: '#f0f0f1', marginBottom: '15px', borderLeft: '4px solid #2271b1' } },
                                el('strong', { style: { display: 'block', marginBottom: '10px' } }, fLabel),
                                rowsUI,
                                el(Button, { isPrimary: true, onClick: function(e) { if(e) e.stopPropagation(); var newRows = rows.slice(); newRows.push({}); var u = {}; u[fName] = JSON.stringify(newRows); setAttributes(u); } }, '+ Add Row')
                            );
                        }

                        if (fieldUI) {
                            if (fTab === 'style') styleFields.push(fieldUI);
                            else contentFields.push(fieldUI);
                        }
                    });

                    var customCssInput = el('div', { style: { marginTop: '20px', paddingTop: '15px', borderTop: '1px solid #e0e0e0' } },
                        el(TextControl, { label: 'Advanced CSS Class', value: attributes.kmb_custom_class, onChange: function(v) { setAttributes({ kmb_custom_class: v }); } })
                    );

                    var sidebarUI;
                    if (styleFields.length > 0) {
                        styleFields.push(customCssInput);
                        sidebarUI = el(TabPanel, { className: 'kmb-sidebar-tabs', activeClass: 'is-active', tabs: [ { name: 'content', title: 'Content', className: 'tab-content' }, { name: 'style', title: 'Style', className: 'tab-style' } ] }, function(tab) {
                            return el('div', { style: { marginTop: '15px' } }, tab.name === 'content' ? contentFields : styleFields);
                        });
                    } else {
                        contentFields.push(customCssInput);
                        sidebarUI = el('div', { style: { marginTop: '15px' } }, contentFields);
                    }

                    var modalOverlay = null;
                    if (isModalOpen) {
                        modalOverlay = el(Modal, {
                            title: blockData.title + ' Settings',
                            onRequestClose: function() { setIsModalOpen(false); },
                            style: { width: '800px', maxWidth: '95vw', padding: '10px' } 
                        }, sidebarUI);
                    }

                    var toolbarControls = el(BlockControls, { group: "other" },
                        el(ToolbarGroup, null,
                            el(ToolbarButton, {
                                icon: 'edit',
                                label: 'Edit ' + blockData.title + ' Data',
                                title: 'Edit ' + blockData.title + ' Data',
                                onClick: function(e) { if(e) e.stopPropagation(); setIsModalOpen(true); }
                            })
                        )
                    );

                    var livePreview = el(ServerSideRender, { block: blockData.name, attributes: attributes });

                    return el(Fragment, null, toolbarControls, modalOverlay, livePreview);
                },
                save: function() { return null; }
            });
        });
    } catch (error) {
        console.error("🚨 KMB BLOCK ERROR 🚨: ", error);
    }
})(window.wp);