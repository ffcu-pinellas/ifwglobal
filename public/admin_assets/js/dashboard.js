(function($){

    "use strict";

    $(document).ready(function () {

        //---------------------------------------------------------------------------------------------
        // - quantummagazine CALENDAR ------------------------------------------------------------------------------
        //---------------------------------------------------------------------------------------------

        var today = new Date(),
            month = today.getMonth() + 1,
            year = today.getFullYear(),
            notes =  [
                { "date": year + "-" + month + "-12", "time" : "15:45 AM", "content": "New Year" },
                { "date": year + "-" + month + "-25", "time" : "10:30 AM" , "content": "Christmas" }
            ];

        $("#calendar").quantummagazineCalendar({notes: notes});

        $("#calendar_dark").quantummagazineCalendar({notes: notes,theme: "light",backgroundColor : "#444"});


    });
})(jQuery);

