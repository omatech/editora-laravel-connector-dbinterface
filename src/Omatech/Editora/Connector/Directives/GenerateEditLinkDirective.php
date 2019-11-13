<?php
    Blade::directive('generate_edit_link', function ($instance) {
        $instanceId = $instance."['metadata']['id']";
        $classId = $instance."['metadata']['class_id']";

        $php = "<?php
            if(session()->has('user_id') && session()->get('user_id') !== '') {
                echo '<a href=\"/'.config('editora.adminAlias').'/view_instance?p_class_id='.{$classId}.'&p_inst_id='.{$instanceId}.'\" class=\"front-edit\" title=\"Editar\" target=\"_blank\"  style=\"display: none;\"><img class=\"edit_img\" src=\"/images/editar.png\" alt=\"Editar\"/></a>';
            }
        ?>";

        return $php;
    });
