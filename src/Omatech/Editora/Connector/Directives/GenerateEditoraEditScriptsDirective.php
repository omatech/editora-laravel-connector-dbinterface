<?php
    Blade::directive('editora_scripts', function()
    {
        return "<?php
            if(isset(\$_SESSION[\"rol_id\"]) && \$_SESSION['rol_id'] !== '')
            {
                echo \Omatech\Editora\Utils\Strings::get_headerEM();
                echo \Omatech\Editora\Utils\Strings::get_linkEM();
                echo \Omatech\Editora\Utils\Strings::get_footerEM();
            }
            else
            {
                \$_REQUEST[\"req_debug\"] = 0;
                \$_REQUEST[\"req_info\"] = 0;
            }
        ?>";
    });
