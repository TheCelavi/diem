<?php

/**
 * @author TheCelavi
 */
class dmBehaviorsSortForm extends dmForm {
    
    public function configure() {
        parent::configure();
        $this->widgetSchema['behaviors'] = new sfWidgetFormInputHidden();
        $this->validatorSchema['behaviors'] = new sfValidatorPass();
    }

    public function render($attributes = array()) {
        $attributes = dmString::toArray($attributes, true);
        return
                $this->open($attributes) .
                $this->renderContent($attributes) .
                $this->renderActions() .
                $this->close();
    }

    protected function renderContent($attributes) {
        return '<ul class="dm_form_elements">' . $this->getFormFieldSchema()->render($attributes) . '</ul>';
    }
    
    protected function renderActions() {
        return sprintf(
            '<div class="actions">
                <div class="actions_part clearfix">%s%s</div>
                <div class="actions_part clearfix"></div>
            </div>', sprintf(
                                '<a class="dm cancel close_dialog button fleft">%s</a>', $this->__('Cancel')),
                sprintf('<input type="submit" class="submit and_save green fright" name="and_save" value="%s" />', $this->__('Save'))
        );        
    }
    
    public function saveSortOrder() {
        try {
            $behaviors = json_decode($this->getValue('behaviors'), true);
            DmBehaviorTable::getInstance()->getConnection()->beginTransaction();
            foreach($behaviors as $behavior) {
                $tmp = dmDb::query('DmBehavior b')->where('id = ?', $behavior['id'])->fetchOne();
                $tmp->setPosition($behavior['position']);
                $tmp->save();
            }
            DmBehaviorTable::getInstance()->getConnection()->commit(); 
            return true;
        } catch (Exception $e) {            
            DmBehaviorTable::getInstance()->getConnection()->rollback();
            return false;
        }            
    }
    
}
