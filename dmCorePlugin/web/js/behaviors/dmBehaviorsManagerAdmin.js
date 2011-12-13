(function($) {           
    $.dm.behaviorsManager = $.extend($.dm.behaviorsManager, {        
        initBehaviorsAdministration: function() {
            // Load menu...
            this.reloadBehaviorsMenu();
            // Initialize Context menu
            this.initializeContextMenu();
            // Enable drag&drop of behaviors
            this.enableAddBehaviors();
            // Enable edit and/or delete of behaviors
            this.enableEditBehaviors();
            // Attach tipsy
            $('.dm_edit_behaviors_icon').tipsy({gravity: $.fn.tipsy.autoNorth});
        },
        initializeContextMenu: function() {            
            $('body').append('<div class="dm dm_edit_behaviors_context_menu_template none"></div>');
            $('body').append('<div class="dm dm_edit_behaviors_context_menu none"><ul></ul></div>');            
            //BuildContextMenuItems
            $.ajax(dm_configuration.script_name + '+/dmBehaviors/buildContextMenuItems', { // Strange... $.dm.ctrl.getHref does not work???
                type        :           'post',
                error       :      function(xhr, textStatus, errorThrown) {                    
                    $.dm.ctrl.errorDialog('Error when loading context menu', xhr.responseText);
                },
                success: function(html) {
                    $('.dm_edit_behaviors_context_menu_template').html(html);
                }
            });
            $(document).click(function(evt){
                if (!$(evt.target).hasClass('dm_edit_behaviors_icon')) $('.dm_edit_behaviors_context_menu').hide('fast');
            });
        },
        showContextMenu: function($icon) {
            // TODO TheCelavi - check if it is possible to edit behaviors???
            var $attachedTo = this.getAttachedTo($icon.closest('.dm_behaviors_attachable'));
            $('.dm_edit_behaviors_context_menu ul').empty();
            $.each(this.behaviors, function(){
                if (this.dm_behavior_attached_to == '.dm_' + $attachedTo.dm_attached_to + '_' + $attachedTo.dm_attachable_id) {
                    var $tmp = $('.dm_edit_behaviors_context_menu_template ul li#' + this.dm_behavior_key).clone();
                    $tmp.prop('id', 'dm_behavior_id_' + this.dm_behavior_id);
                    $('.dm_edit_behaviors_context_menu ul').append($tmp);
                    $tmp.click(function(){
                        $.dm.behaviorsManager.editBehavior({
                            dm_behavior_id: $tmp.prop('id').replace('dm_behavior_id_','')
                        });
                    });
                };
            });
            var $tmp = $('.dm_edit_behaviors_context_menu_template ul li#dm_behavior_cm_sort').clone();
            $tmp.prop('id', 'dm_behavior_cm_sort' + '.dm_' + $attachedTo.dm_attached_to + '_' + $attachedTo.dm_attachable_id).click(function(){
                $.dm.behaviorsManager.sortBehaviors({
                    dm_attached_to: $attachedTo.dm_attached_to,
                    dm_attachable_id: $attachedTo.dm_attachable_id
                });
            });
            $('.dm_edit_behaviors_context_menu ul').append($tmp);
            $('.dm_edit_behaviors_context_menu').css('top', $icon.offset().top).css('left', $icon.offset().left).show('fast');            
        },
        getAttachedTo: function($droppable) {   
            var typeAndId = /dm_(page|area|zone|widget)_([0-9]+)/m.exec($droppable.prop('class'));
            if (typeAndId) {
                return {
                    dm_attached_to: typeAndId[1],
                    dm_attachable_id: typeAndId[2]
                };
            };
            // Maybe it is attached to some kind of the content in page|area|zone|widget? Find the parent container
            var $tmp = $droppable.parent();
            do {
                typeAndId = /dm_(page|area|zone|widget)_([0-9]+)/m.exec($tmp.prop('class'));
                if (typeAndId) {                    
                    var selector = $tmp.prop('class').split(' ');
                    if ($tmp.prop('id')!='') selector.push('#' + $tmp.prop('id'));                    
                    return {
                        dm_attached_to: typeAndId[1],
                        dm_attachable_id: typeAndId[2],
                        dm_attachable_selector: selector.reverse().join('.')
                    };
                };
            } while ($tmp.length != 0);
            return {};
        },
        enableAddBehaviors: function() {
            $('.dm_behaviors_attachable').droppable({
                accept      :       '.behavior_add',
                hoverClass  :       'droppable_hover',
                tolerance   :       'pointer',
                greedy      :       true,
                drop        :       function(event, ui) {
                    var $icon = $(ui.draggable, '.dm_droppable_behaviors'), $this = $(this);
                    if ($icon.length == 0) return;
                    var options = $.extend({}, {dm_behavior_key: $icon.prop('id').replace('dmba_', '')}, $.dm.behaviorsManager.getAttachedTo($this));
                    $.dm.behaviorsManager.addBehavior(options);
                }
            });
        },
        enableEditBehaviors: function() {
            $('a.dm_edit_behaviors_icon').removeClass('edit');
            $.each($.dm.behaviorsManager.behaviors, function(){
                var $gearIcon = $(this.dm_behavior_attached_to + ' a.dm_edit_behaviors_icon:first').addClass('edit');
                if (!$gearIcon.hasClass('configured')) {
                    $gearIcon.addClass('configured').click(function(){
                        $.dm.behaviorsManager.showContextMenu($(this));
                    });
                };
            });
        },
        addBehavior: function(options) {
            $.ajax($.dm.ctrl.getHref('+/dmBehaviors/add'), {
                data        :           options,
                type        :           'post',
                dataType    :           'json',
                error       :      function(xhr, textStatus, errorThrown) {                    
                    $.dm.ctrl.errorDialog('Error when adding behavior', xhr.responseText);
                },
                success: function(data) {
                    if (data.error) {
                        $.dm.ctrl.errorDialog(data.error.title, data.error.message);
                        return;
                    }
                    else {
                        $.dm.behaviorsManager.behaviors.push(data.dm_behavior_data); // Added has highest sequence number!!!
                        $.fn.dmExtractEncodedAssets(data);
                        $.dm.behaviorsManager.enableEditBehaviors();
                        $.dm.behaviorsManager.editBehavior({
                            dm_behavior_id: data.dm_behavior_data.dm_behavior_id
                        });
                    };
                }
            });
        },
        deleteBehavior: function(options) {
            options = $.extend({}, options, {dm_action_delete:true});
            $.ajax($.dm.ctrl.getHref('+/dmBehaviors/delete'), {
                data        :           options,
                type        :           'post',
                dataType    :           'json',
                error       :      function(xhr, textStatus, errorThrown) {                    
                    $.dm.ctrl.errorDialog('Error when deleting behavior', xhr.responseText);
                },
                success: function(data) {
                    if (data.error) {
                        $.dm.ctrl.errorDialog(data.error.title, data.error.message);
                        return;
                    }
                    else {
                        var search = -1;
                        $.each($.dm.behaviorsManager.behaviors, function(index){
                            if (this.dm_behavior_id == options.dm_behavior_id) {
                                search = index;
                                return false;
                            }
                        });                        
                        if (search > -1) $.dm.behaviorsManager.behaviors.splice(search, 1);
                        $.dm.behaviorsManager.enableEditBehaviors();
                    };
                }
            });
        },
        editBehavior: function(options) {
            var $dialog = $.dm.ctrl.ajaxDialog({
                url         :           $.dm.ctrl.getHref('+/dmBehaviors/edit'),
                data        :           options,
                type        :           'get',
                title       :           'Edit behavior', // TODO Translate
                width       :           600,
                'class'     :           'dm_widget_edit_dialog_wrap ',
                resizable   :           true,
                resize      :           function(event, ui) {
                    $dialog.maximizeContent('textarea.markItUpEditor');
                }
            });
            $dialog.bind('dmAjaxResponse', function(event){                
                $dialog.prepare();                
                $('a.delete', $dialog).click(function() {
                    if (confirm($(this).tipsyTitle()+" ?")) {
                        $.dm.removeTipsy();
                        $.dm.behaviorsManager.deleteBehavior(options);
                        $dialog.dialog('close');
                    };
                });
                var $form = $('div.dm.dm_behavior_edit.dm_behavior_edit_form', $dialog);
                if (!$form.length) return;
                $dialog.parent().find('div.ui-dialog-titlebar .dm_cut_copy_actions').remove();// Remove if already exists...
                if ($cutCopy = $form.find('div.dm_cut_copy_actions').orNot()) {
                    $cutCopy.appendTo($dialog.parent().find('div.ui-dialog-titlebar')).show().find('a').click(function() {
                        var $a = $(this).addClass('s16_gear');          
                        $.ajax({
                            url:      $(this).attr('href'),
                            success:  function() {
                                $.dm.behaviorsManager.reloadBehaviorsMenu(function() {
                                    $a.removeClass('s16_gear');
                                });
                            }
                        });
                        return false;
                    });
                }
                // enable tool tips
                $dialog.parent().find('a[title], input[title]').tipsy({
                    gravity: $.fn.tipsy.autoSouth
                });
                
                /*
                 * Apply generic front form abilities
                 */
                $form.dmFrontForm();
                /*
                 * Apply specific behavior form abilities
                 */
                if ((formClass = $form.metadata().form_class) && $.isFunction($form[formClass])) $form[formClass]($form);  // TODO check if this is the context to provide???
                
                $form.find('form').dmAjaxForm({
                    beforeSubmit: function(data) {
                        $dialog.block();
                    },
                    error: function(xhr, textStatus, errorThrown) {
                        $dialog.unblock();
                        $.dm.ctrl.errorDialog('Error when updating the behavior', xhr.responseText);
                    },
                    success: function(html) {
                        $dialog.unblock();
                        if(/dm dm_behavior_edit dm_behavior_edit_form/m.exec(html)) {
                            $dialog.html(html).trigger('dmAjaxResponse'); // Form is not valid, it is rendered again
                            return;
                        };
                        try {
                            var $tmp = $(html);
                            var data = $.parseJSON($tmp.val());
                        } catch(e) { 
                            $.dm.ctrl.errorDialog('Error', 'Something went wrong when updating behavior');
                            return;
                        }
                        if (data.error){
                            $.dm.ctrl.errorDialog(data.error.title, data.error.message);
                            return;
                        }
                        
                        $.each($.dm.behaviorsManager.behaviors, function(index){
                            if (this.dm_behavior_id == data.dm_behavior_id) {
                                $.dm.behaviorsManager.behaviors[index] = data;
                                return false;
                            };
                        });
                        
                        $.fn.dmExtractEncodedAssets(data);                      
                        $dialog.dialog('close');
                    }
                });
            });
        },
        sortBehaviors: function(options) {
            var $dialog = $.dm.ctrl.ajaxDialog({
                url         :           $.dm.ctrl.getHref('+/dmBehaviors/sortBehaviors'),
                data        :           options,
                type        :           'get',
                title       :           'Sort behaviors', // TODO Translate
                width       :           300,
                'class'     :           'dm_widget_edit_dialog_wrap ',
                resizable   :           true                
            });
            
            $dialog.bind('dmAjaxResponse', function(event){                
                $dialog.prepare();                
                
                var $form = $('div.dm.dm_behavior_sort.dm_behavior_sort_form', $dialog);
                if (!$form.length) return;
                                
                $dialog.parent().find('a[title]').tipsy({
                    gravity: $.fn.tipsy.autoSouth
                });
                
                var behaviors = $.parseJSON($form.find('#dm_behaviors_sort_form_behaviors').val());
                behaviors.sort(function(a,b){
                    return a.position - b.position;
                });
                var currentPositions = new Array();
                var $sortList = $('<ul class="dm_behaviors_sortable"></ul>');
                $.each(behaviors, function(){
                    currentPositions.push(this.position);
                    var text = this.dm_behavior_name + ' :: ' + this.dm_behavior_attached_to + ' ' + this.dm_behavior_attached_to_id;
                    if (this.dm_behavior_attached_to_selector) text += ' > ' + this.dm_behavior_attached_to_selector;
                    $sortList.append(
                        $('<li><span class="move"><img width="16" height="16" src="' + this.dm_behavior_icon + '" />' + text + '</span></li>')
                        .data('behavior', this)
                        .hover(function(){
                            var data = $(this).data('behavior');
                            $('.dm_' + data.dm_behavior_attached_to + '_' + data.dm_behavior_attached_to_id + ' ' + data.dm_behavior_attached_to_selector).addClass('droppable_hover');                            
                        }, function(){
                            var data = $(this).data('behavior');
                            $('.dm_' + data.dm_behavior_attached_to + '_' + data.dm_behavior_attached_to_id + ' ' + data.dm_behavior_attached_to_selector).removeClass('droppable_hover');
                        }).click(function(){
                            $.dm.behaviorsManager.editBehavior({
                                dm_behavior_id: $(this).data('behavior').id
                            });
                        })
                    );
                });
                $form.find('form').prepend($sortList.sortable({
                    helper: 'clone',
                    update: function(){
                        var sorted = new Array();
                        $.each($sortList.find('li'), function(index){
                            var data = $(this).data('behavior');
                            data.position = currentPositions[index];
                            sorted.push(data);
                        });
                        $dialog.find('#dm_behaviors_sort_form_behaviors').val($.toJSON(sorted));
                    }
                }).disableSelection()).dmAjaxForm({
                    beforeSubmit: function(data) {
                        $dialog.block();
                    },
                    error: function(xhr, textStatus, errorThrown) {
                        $dialog.unblock();
                        $.dm.ctrl.errorDialog('Error when sorting behaviors', xhr.responseText);
                    },
                    success: function(html) {
                        $dialog.unblock();
                        if(/dm dm_behavior_sort dm_behavior_sort_form/m.exec(html)) {
                            $dialog.html(html).trigger('dmAjaxResponse'); // Form is rendered for the first time...
                            return;
                        };
                        try {
                            var $tmp = $(html);
                            var data = $.parseJSON($tmp.val());
                        } catch(e) { 
                            $.dm.ctrl.errorDialog('Error', 'Something went wrong when sorting behavior');
                            return;
                        }
                        if (data.error){
                            $.dm.ctrl.errorDialog(data.error.title, data.error.message);
                            return;
                        }
                        $.each($.dm.behaviorsManager.behaviors, function(){
                            var inMemory = this;
                            $.each(data.dm_behavior_data, function(){
                                if (inMemory.dm_behavior_id == this.dm_behavior_id) {
                                    inMemory.dm_behavior_sequence = this.position;
                                }
                            });                           
                        });
                        $.dm.behaviorsManager.behaviors.sort(function(a,b){
                            return a.position - b.position;
                        });                                             
                        $dialog.dialog('close');
                    }
                });
            });
           
        },
        reloadBehaviorsMenu: function(callback) {
            var $menu = $('div.dm_behaviors_menu');
            if(!$menu.length) return;
            $.ajax({
                url:      $menu.metadata().reload_url,
                success: function(html) {
                    $menu.html(html);
                    var $actions = $menu.find('li.dm_behaviors_menu_actions').prependTo($menu.find('ul.level1'));    
                    $actions.find('a.dm_sort_all_behaviors').click(function(){
                        $.dm.behaviorsManager.sortBehaviors({
                            dm_attached_to: 'page',
                            dm_attachable_id: dm_configuration.page_id
                        });
                        $menu.dmMenu('close');
                    });
                    $menu.dmMenu({
                        hoverClass: 'ui-state-active'
                    })
                    .find('li.dm_droppable_behaviors').disableSelection();
                    $actions.find('input.dm_add_behaviors_search').hint();
                    $menu.find('a.tipable').tipsy({
                        gravity: 's'
                    });
                    $menu.find('input.dm_add_behaviors_search').bind('keyup', function() {
                        var term = new RegExp($.trim($(this).val()), 'i');
                        if(term == ''){
                            $menu.find(':hidden').show();
                            return;
                        }
                        $menu.find('li.dm_droppable_behaviors').each(function(){
                            $(this).show();            
                            if($(this).find('> a').text().match(term)) {
                                $(this).find('li:hidden').show();
                            }
                            else                            {
                                $(this).find('li').each(function()                                {
                                    $(this)[$(this).find('span.move').text().match(term) ? 'show' : 'hide']();
                                });
                                $(this)[$(this).find('li:visible').length ? 'show' : 'hide']();
                            }
                        });
                    });
                    $menu.find('span.behavior_add').draggable({
                        helper: function() {
                            return $('<div class="dm"><div class="dm_behavior_add_helper ui-corner-all">'+ $(this).html()+'</div></div>').maxZIndex();
                        },
                        appendTo: '#dm_page',
                        cursorAt: {
                            left: 30, 
                            top: 10
                        },
                        cursor: 'move',
                        start: function(){
                            $menu.dmMenu('close');
                            $('#dm_tool_bar').dmFrontToolBar('activateEdit', true);                            
                        }
                    });
                    callback && $.isFunction(callback) && callback();
                }
            });            
        }
    });
    
    $.dm.behaviorsManager.initBehaviorsAdministration();

})(jQuery);