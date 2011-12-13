(function($) {
    $.dm.behaviorsManager = {
        behaviors:      null,      
        state:          0, // nothing = 0 || constructed = 1 || initialized = 2 || started = 3
        _construct: function() {                        
            // Init behavior manager
            $.dm.behaviorsManager.behaviors = new Array();
            var $beahvior_settings = $('div.dm_behaviors'); // Destroy after?            
            if ($beahvior_settings.length == 0) return;            
            var behaviors = $beahvior_settings.metadata().behaviors;
            $.each(behaviors, function(){                
                 $.dm.behaviorsManager.behaviors.push(this);
            });            
            $.dm.behaviorsManager.state = 1;
        },
        init: function() {      
            if ($.dm.behaviorsManager.state < 1) $.dm.behaviorsManager._construct();
            // Initialize behaviors
            $.each($.dm.behaviorsManager.behaviors, function(){
                // Maybe behavior is defect...
                try {
                    $.dm.behaviors[this.dm_behavior_key].init(this);
                }catch(e) {
                    alert(e);
                    // TODO AJAX call to the function to register error to the DIEM log!!!!
                    // Valuable feedback to the developer
                };
            });
            $.dm.behaviorsManager.state = 2;
        },
        start: function() {
            if ($.dm.behaviorsManager.state < 2) $.dm.behaviorsManager.init();
            $.each($.dm.behaviorsManager.behaviors, function(){
                // Maybe behavior is defect...
                try {
                    $.dm.behaviors[this.dm_behavior_key].start();
                }catch(e) {
                    // TODO AJAX call to the function to register error to the DIEM log!!!!
                    // Valuable feedback to the developer
                };
            });
            $.dm.behaviorsManager.state = 3;
        }
    };
    
    $.dm.behaviorsManager.start();
    
})(jQuery);