/*===================================================================================================

 - TEMPLATE : Bibric
 - DESCRIPTION : MODERN BOOTSTRAP 4 ADMIN TEMPLATE - FULLY RESPONSIVE
 - AUTHOR : bdCoder (http://www.bdcoder.com/)
 - VERSION : 1.0
 - FILE : ICON JS

 ===================================================================================================*/
(function($) {
    "use strict";

    $(document).ready(function () {

        //---------------------------------------------------------------------------------------------
        // - SEARCH ICON ------------------------------------------------------------------------------
        //---------------------------------------------------------------------------------------------

        $("#search-icon").on('keyup', function () {
            var  filter, icons, element, small, i,co=0;
            filter = document.getElementById("search-icon").value.toUpperCase();
            icons = document.getElementById("icons");
            element = icons.getElementsByClassName("col-md-2");
            for (i = 0; i < element.length; i++) {
                small = element[i].getElementsByTagName("small")[0];
                if (small.innerHTML.toUpperCase().indexOf(filter) > -1) {
                    element[i].style.display = "";
                    co ++;
                } else {
                    element[i].style.display = "none";
                }
            }

            if (co  === 0)
                $(".not-found").show();
            else
                $(".not-found").hide();
        });

    });

})(jQuery);


