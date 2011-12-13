<?php

/**
 * Description of BasedmBehaviorsActions
 *
 * @author TheCelavi
 */
class BasedmBehaviorsActions extends dmBaseActions {

    public function executeReloadAddMenu(dmWebRequest $request) {
        
        $menu = $this->getService('behaviors_add_menu');
        $menu->build()->render();
        $menu .= '<li class="dm_behaviors_menu_actions clearfix">' .
                '<input class="dm_add_behaviors_search" title="' . $this->getI18n()->__('Search for a behavior') . '" />' .
                '<a class="dm_sort_all_behaviors"><span class="s16 s16_sort"></span>' . $this->getI18n()->__('Sort content behaviors') . '</a>'.
                '</li>';
        return $this->renderText($menu);
    }
    
    public function executeSortBehaviors(dmWebRequest $request) {
        if ($request->hasParameter('dm_attached_to') && $request->hasParameter('dm_attachable_id')) {
            $behaviorsManager = $this->getService('behaviors_manager');
            try {
                $behaviors = $behaviorsManager->getBehaviorsForSort($request->getParameter('dm_attached_to'), $request->getParameter('dm_attachable_id'));
            } catch (Exception $e) {
                return $this->renderError($this->getI18n ()->__('Error'), __('The behaviors could not be fetched'));
            }
            
            $tmp = array();
            foreach ($behaviors as $behavior) {
                $settings = $behaviorsManager->getBehaviorSettings($behavior['dm_behavior_key']);
                $tmp[] = array(
                    'id'                                =>                  $behavior['id'],
                    'dm_behavior_key'                   =>                  $behavior['dm_behavior_key'],
                    'dm_behavior_name'                  =>                  $this->getI18n()->__($settings['name']),
                    'dm_behavior_icon'                  =>                  $settings['icon'],
                    'dm_behavior_attached_to'           =>                  $this->getI18n()->__($behavior['dm_behavior_attached_to']),
                    'dm_behavior_attached_to_id'        =>                  $behavior['dm_page_id'] + $behavior['dm_area_id'] + $behavior['dm_zone_id'] + $behavior['dm_widget_id'], // null + null + null + number = number
                    'dm_behavior_attached_to_selector'  =>                  $behavior['dm_behavior_attached_to_selector'],
                    'position'                          =>                  $behavior['position']
                );
            }
            
            $form = new dmBehaviorsSortForm(array('behaviors'=>  json_encode($tmp)));
              
            if ($request->isMethod('post') && $form->bindAndValid($request) && $request->hasParameter('and_save')){

                if ($form->saveSortOrder()) return $this->renderJson(array(
                    'error' => false,              
                    'dm_behavior_data' => $form->getValue('behaviors')
                ));
                else return $this->renderError (
                    $this->getI18n ()->__('Error'),
                    $this->getI18n ()->__('Behaviors are not saved.'));
                
            }
            
            return $this->renderAsync(array(
              'html'  => $this->getHelper()->tag('div.dm.dm_behavior_sort.dm_behavior_sort_form' , $form->render())
            ), true);
            
            
        } else return $this->renderError (
                $this->getI18n ()->__('Error'),
                $this->getI18n ()->__('You must supply behavior for edit'));
    }

    public function executeBuildContextMenuItems(dmWebRequest $request) {
        $behaviorsManager = $this->getService('behaviors_manager');
        $behaviors = $behaviorsManager->getListOfRegisteredBehaviors();
        $html = '<ul>';
        if ($this->getUser()->can('behavior_delete') || $this->getUser()->can('behavior_edit')) {
            foreach ($behaviors as $key => $behavior) {
                $html .= sprintf('<li id="%s" class="dm_behavior_cm_item"><span><img width="16" height="16" src="%s"/>%s</span></li>', $key, $behavior['icon'], $this->getI18n()->__($behavior['name']));
            }
            if ($this->getUser()->can('behavior_edit')) $html .= sprintf('<li id="dm_behavior_cm_sort"><span class="s16 s16_sort"></span>%s</li>', $this->getI18n()->__('Sort behaviors'));
            $html .= '</ul>';
        } else $html = sprintf('<ul class="not_allowed"><li>%s<li></ul>', $this->getI18n()->__('You do not have permissions to edit behaviors.'));
        return $this->renderAsync(array('html'=>$html));
    }

    public function executeDelete(dmWebRequest $request) {
        if ($request->hasParameter('dm_behavior_id') && $request->hasParameter('dm_action_delete') && $request->getParameter('dm_action_delete')) {
            $behaviorsManager = $this->getService('behaviors_manager');
            try {
                $behavior = $behaviorsManager->getDmBehavior($request->getParameter('dm_behavior_id'));
                $behavior->delete();
            } catch (Exception $e) {
                return $this->renderError($this->getI18n ()->__('Error'), __('The behavior does not exists aymore'));
            }
            return $this->renderJson(array(
                'error'=>false
            ));
        } else return $this->renderError (
                $this->getI18n ()->__('Error'),
                $this->getI18n ()->__('Behavior is not deleted'));
    }

    public function executeAdd(dmWebRequest $request) {
        if ($request->hasParameter('dm_behavior_key')) {
            $behaviorsManager = $this->getService('behaviors_manager');
            
            if (!$behaviorsManager->isExists($request->getParameter('dm_behavior_key'))) 
                    return $this->renderError ($this->getI18n ()->__('Error'), $this->getI18n()->__('The behavior with key "%key%" does not exists aymore'), array('key'=>$request->getParameter('dm_behavior_key')));
            
            try {
                $behavior = $behaviorsManager->createEmptyInstance(
                        $request->getParameter('dm_behavior_key'),
                        $request->getParameter('dm_attached_to'),
                        $request->getParameter('dm_attachable_id'),
                        $request->getParameter('dm_attachable_selector', null)
                        );
            } catch (Exception $e) {
                return $this->renderError($this->getI18n ()->__('Error'), $this->getI18n ()->__('The behavior could not be created'));
            }
            try {
                $viewClass = $behaviorsManager->getBehaviorViewClass($request->getParameter('dm_behavior_key'));
                $behaviorView = new $viewClass($this->context, $behavior);
            } catch (Exception $e) {
                return $this->renderError($this->getI18n ()->__('Error'), $this->getI18n()->__('Could not initialize behavior view class'));
            }
            
            return $this->renderJson(array(
                'error' => false,              
                'dm_behavior_data' => $behaviorView->renderArray(),
                'js' => $this->parseJavascripts($behaviorView->getJavascripts()),
                'css' => $this->parseStylesheets($behaviorView->getStylesheets())
            ));
        } else return $this->renderError (
                $this->getI18n ()->__('Error'),
                $this->getI18n ()->__('You must supply behavior key'));
    }

    public function executeEdit(dmWebRequest $request) {
        if ($request->hasParameter('dm_behavior_id')) {
            $behaviorsManager = $this->getService('behaviors_manager');
            try {
                $behavior = $behaviorsManager->getDmBehavior($request->getParameter('dm_behavior_id'));
            } catch (Exception $e) {
                return $this->renderError($this->getI18n ()->__('Error'), __('The behavior does not exists aymore'));
            }
            if (!$behaviorsManager->isExists($behavior->getDmBehaviorKey())) {
                return $this->renderAsync(array(
                    'html'  => $this->renderFormNotExist()
                ));
            }                    
            $formClass = $behaviorsManager->getBehaviorFormClass($behavior->getDmBehaviorKey());
            $viewClass = $behaviorsManager->getBehaviorViewClass($behavior->getDmBehaviorKey());
            try {
                $form = new $formClass($behavior);
                $view = new $viewClass($this->context, $behavior);
            } catch (Exception $e) {
                return $this->renderError($this->getI18n ()->__('Error'), __('The form for behavior with key "%key%" could not be saved'), array('%key%'=>$behavior->getDmBehaviorKey()));
            }
            
            if ($request->isMethod('post') && $form->bindAndValid($request)){
                $form->updateBehavior();
                if ($request->hasParameter('and_save')) {
                    $behavior->save();
                    return $this->renderJson(array(
                        'error' => false,              
                        'dm_behavior_data' => $view->renderArray(),
                        'js' => $this->parseJavascripts($view->getJavascripts()),
                        'css' => $this->parseStylesheets($view->getStylesheets())
                    ));
                }
            }
            
            return $this->renderAsync(array(
              'html'  => $this->renderEdit($form, $behaviorsManager, true),//
              'js'    => array_merge(array('lib.hotkeys'), $form->getJavascripts()),
              'css'   => $form->getStylesheets()
            ), true);
            
            
        } else return $this->renderError (
                $this->getI18n ()->__('Error'),
                $this->getI18n ()->__('You must supply behavior for edit'));
    }
    
    public function renderEdit(dmBehaviorBaseForm $form, $behaviorsManager, $withCopyActions = true) {
        $helper = $this->getHelper();
        $copyActions = '';
        if ($withCopyActions && $this->getUser()->can('behavior_add')) {
            $copyActions = $helper->tag('div.dm_cut_copy_actions.none',
            $helper->link('+/dmBehavior/cut')
            ->param('dm_behavior_id', $form->getDmBehavior()->get('id'))
            ->text('')
            ->title($this->getI18n()->__('Cut'))
            ->set('.s16block.s16_cut.dm_behavior_cut').
            $helper->link('+/dmBehavior/copy')
            ->param('dm_behavior_id', $form->getDmBehavior()->get('id'))
            ->text('')
            ->title($this->getI18n()->__('Copy'))
            ->set('.s16block.s16_copy.dm_behavior_cut')
          );
        }
        
        return $helper->tag('div.dm.dm_behavior_edit.dm_behavior_edit_form.'.dmString::underscore($form->getDmBehavior()->get('dm_behavior_key')).'_form',
            array('json' => array('form_class' => $behaviorsManager->getBehaviorFormClass($form->getDmBehavior()->get('dm_behavior_key')), 'form_name' => $form->getName())),
            $form->render('.dm_form.list.little').$copyActions
        );
        
    }

    protected function renderError($title, $message) {
        return $this->renderJson(array(
            'error' => array(
                'title' => $title,
                'message' => $message
            )
        ));
    }

    protected function renderFormNotExist() {
        return sprintf(
                '<p class="s16 s16_error">%s</p>
                    <div class="clearfix mt30">
                    <a class="dm cancel close_dialog button mr10">%s</a>
                    <a class="dm delete button red" title="%s">%s</a>
                 </div>',
            $this->getI18n()->__('The behavior can not be rendered because its type does not exist anymore.'),
            $this->getI18n()->__('Cancel'),
            $this->getI18n()->__('Delete this behavior'),
            $this->getI18n()->__('Delete')
        );
    }
    
    protected function parseJavascripts($javascripts) {
        if (!is_array($javascripts)) $javascripts = array($javascripts);
        foreach ($javascripts as &$js) {
            $js = $this->getHelper()->getJavascriptWebPath($js);
        }
        return $javascripts;
    }
    
    protected function parseStylesheets($stylesheets) {
        if (!is_array($stylesheets)) $stylesheets = array($stylesheets);
        foreach ($stylesheets as &$css) {
            $css = $this->getHelper()->getStylesheetWebPath($css);
        }
        return $stylesheets;
    }


}

