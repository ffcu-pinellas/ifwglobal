/*===================================================================================================

 - TEMPLATE : Bibric
 - DESCRIPTION : MODERN BOOTSTRAP 4 ADMIN TEMPLATE - FULLY RESPONSIVE
 - AUTHOR : bdCoder (http://www.bdcoder.com/)
 - VERSION : 1.0
 - FILE : CALENDAR JS

 ===================================================================================================*/
(function($) {
    "use strict";
    $(document).ready(function () {

        //---------------------------------------------------------------------------------------------
        // - SETUP PARAM ------------------------------------------------------------------------------
        //---------------------------------------------------------------------------------------------

        var today = new Date(),
            month = today.getMonth() + 1,
            year = today.getFullYear(),
            notes =  [
                { "date": year + "-" + month + "-12", "time" : "15:45 AM", "content": "New Year" },
                { "date": year + "-" + month + "-25", "time" : "10:30 AM" , "content": "Christmas" }
            ];

        //---------------------------------------------------------------------------------------------
        // - DNCALENDAR -------------------------------------------------------------------------------
        //---------------------------------------------------------------------------------------------

        $("#calendar").quantummagazineCalendar({notes: notes,theme: "light",backgroundColor : "#ef2b41"});

        $("#calendar-1").quantummagazineCalendar({notes: notes,theme: "light",backgroundColor : "#4b5066"});

        $("#calendar-2").quantummagazineCalendar({notes: notes,theme: "dark",backgroundColor : "#fff"});

        $("#calendar-3").quantummagazineCalendar({notes: notes,theme: "light",backgroundColor : "#888fa9"});

    });
})(jQuery);


