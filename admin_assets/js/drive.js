/*===================================================================================================

 - TEMPLATE : Bibric
 - DESCRIPTION : MODERN BOOTSTRAP 4 ADMIN TEMPLATE - FULLY RESPONSIVE
 - AUTHOR : bdCoder (http://www.bdcoder.com/)
 - VERSION : 1.0
 - FILE : DRIVE JS

 ===================================================================================================*/
(function($) {
    "use strict";

    $(document).ready(function () {

        //---------------------------------------------------------------------------------------------
        // - INITIALISATION ---------------------------------------------------------------------------
        //---------------------------------------------------------------------------------------------

        $('#file-nav-toggle').on('click', function () {
            $(".box-file .nav").slideToggle();
        });
        $("#storage").circliful({
            percent: 75,
            animation: 1,
            animationStep: 10,
            foregroundBorderWidth: 25,
            backgroundBorderWidth: 20,
            backgroundColor: '#e9e6dd',
            foregroundColor: '#ef2b41',
            fillColor: 'transparent',
            fontColor: '#444'
        });

    });
})(jQuery);


