/*===================================================================================================

 - TEMPLATE : Bibric
 - DESCRIPTION : MODERN BOOTSTRAP 4 ADMIN TEMPLATE - FULLY RESPONSIVE
 - AUTHOR : bdCoder (http://www.bdcoder.com/)
 - VERSION : 1.0
 - FILE : ACCOUNT SINGUP JS

 ===================================================================================================*/
(function($) {
    "use strict";
    $(document).ready(function () {

        //---------------------------------------------------------------------------------------------
        // - INTIALISATION ----------------------------------------------------------------------------
        //---------------------------------------------------------------------------------------------

        $("#terms-conditions-accepted").on("click", function () {
            $("#terms-conditions").prop('checked', true);
        });
        $("#terms-conditions-refused").on("click", function () {
            $("#terms-conditions").prop('checked', false);
        });


    });
})(jQuery);


