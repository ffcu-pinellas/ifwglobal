/*===================================================================================================

 - TEMPLATE : Bibric
 - DESCRIPTION : MODERN BOOTSTRAP 4 ADMIN TEMPLATE - FULLY RESPONSIVE
 - AUTHOR : bdCoder (http://www.bdcoder.com/)
 - VERSION : 1.0
 - FILE : GALLERY JS

 ===================================================================================================*/
(function($) {
    "use strict";

    $(document).ready(function () {

        //---------------------------------------------------------------------------------------------
        // - INITIALISATION ---------------------------------------------------------------------------
        //---------------------------------------------------------------------------------------------

        $(".gallery-item").on('click', function(){
            $("#myModal").modal().find(".current").attr("src",$(this).find("img").attr("src"));
        });

    });
})(jQuery);


