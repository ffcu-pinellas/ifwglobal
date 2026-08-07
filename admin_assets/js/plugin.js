/*===================================================================================================

 - TEMPLATE : Bibric
 - DESCRIPTION : MODERN BOOTSTRAP 4 ADMIN TEMPLATE - FULLY RESPONSIVE
 - AUTHOR : bdCoder (http://www.bdcoder.com/)
 - VERSION : 1.0
 - FILE : PLUGIN JS

===================================================================================================*/


(function ($) {
    "use strict";
    //-----------------------------------------------------------------------------------------------
    // - PLUGINS ------------------------------------------------------------------------------------
    //-----------------------------------------------------------------------------------------------

    $.fn.quantummagazineStartTime = function() {

        var parent = $(this);
        function checkTime(i) {
            return (i < 10) ? "0" + i : i;
        }
        function startTime() {
            var today = new Date(),
                h = checkTime(today.getHours()),
                m = checkTime(today.getMinutes());
            parent.text(h + " : " + m);
            setTimeout(function () {
                startTime()
            }, 1000);
        }
        startTime();
    };

     /* Bibric CIRCLE TIME -----------------------------------------------------------------------

     - INIT : $('selector').quantummagazineCircleTime();

     ---------------------------------------------------------------------------------------------- */

    $.fn.quantummagazineCircleTime = function() {
        var parent = $(this);
        var construct = function(){
            var container = $("<div class='clock-axis'></div>");
            parent.addClass("clock");
            parent.append(container);
            var today = new Date(),
                h = today.getHours()*30,
                m = today.getMinutes()*6;
            document.styleSheets[0].addRule('.clock:after','transform: rotateZ('+h+'deg)');
            document.styleSheets[0].addRule('.clock:before','transform: rotateZ('+m+'deg)');
        };
        construct();
    };

     /* Bibric TIME PICKER ------------------------------------------------------------------------

     - INIT : $('selector').quantummagazineTimePicker();

     ---------------------------------------------------------------------------------------------- */

    $.fn.quantummagazineTimePicker = function() {

        var parent = $(this);

        var construct = function(){

            var $timePicker_container = $("<div class='quantummagazineTimePicker-container'></div>");
            var $timePicker_time = $("<div class='quantummagazineTimePicker-time'></div>");

            var $timePicker_hour = $("<div class='quantummagazineTimePicker-hour'><span class='value'>10</span> <span class='up fa fa-chevron-up'></span>  <span class='down fa fa-chevron-down'></span></div>");
            var $timePicker_minute = $("<div class='quantummagazineTimePicker-minute'><span class='value'>00</span> <span class='up fa fa-chevron-up'></span>  <span class='down fa fa-chevron-down'></span></div>");
            var $timePicker_format = $("<div class='quantummagazineTimePicker-format'><span class='value'>AM</span> <span class='change fa fa-chevron-down'></span></div>");
            var $timePicker_divider = $("<div class='quantummagazineTimePicker-divider'><span>:</span></div>");
            var $timePicker_input = $("<input type='hidden' value='10:00 AM' class='quantummagazineTimePicker-input'/>");

            $timePicker_time.append($timePicker_hour);
            $timePicker_time.append($timePicker_divider);
            $timePicker_time.append($timePicker_minute);

            $timePicker_container.append($timePicker_time);
            $timePicker_container.append($timePicker_format);
            $timePicker_container.append($timePicker_input);

            parent.append($timePicker_container);
        };

        var triggerAction = function () {

            $(parent).on('click', '.quantummagazineTimePicker-hour span', function(){
                var $valueNode = parent.find(".quantummagazineTimePicker-hour .value");
                var $inputNode = parent.find(".quantummagazineTimePicker-input");
                var value;
                if($(this).hasClass("up")){
                    value = parseInt($valueNode.text()) + 1;
                    if(value > 12) value = 0;
                }else if ($(this).hasClass("down")){
                    value = parseInt($valueNode.text()) - 1;
                    if(value < 0) value = 12;
                }
                value = (value).toLocaleString('en-US', {minimumIntegerDigits: 2, useGrouping:false});
                $valueNode.text(value);
                $inputNode.val(value+$inputNode.val().substring(2,8));
            });

            $(parent).on('click', '.quantummagazineTimePicker-minute span', function(){
                var $valueNode = parent.find(".quantummagazineTimePicker-minute .value");
                var $inputNode = parent.find(".quantummagazineTimePicker-input");
                var value;
                if($(this).hasClass("up")){
                    value = parseInt($valueNode.text()) + 1;
                    if(value > 59) value = 0;
                }else   if($(this).hasClass("down")){
                    value = parseInt($valueNode.text()) - 1;
                    if(value < 0) value = 59;
                }
                value = (value).toLocaleString('en-US', {minimumIntegerDigits: 2, useGrouping:false});
                $valueNode.text(value);
                $inputNode.val($inputNode.val().substring(0,3)+value+$inputNode.val().substring(5,8));
            });

            $(parent).on('click', '.quantummagazineTimePicker-format .change', function(){
                var $valueNode = parent.find(".quantummagazineTimePicker-format .value");
                var $inputNode = parent.find(".quantummagazineTimePicker-input");
                var value = $valueNode.text();
                value = (value === "AM")? "PM" : "AM";
                $valueNode.text(value);
                $inputNode.val($inputNode.val().substring(0,6)+value);
            });

        };

        construct();

        triggerAction();

    };

    /* Bibric CALENDAR ---------------------------------------------------------------------------

    - INIT : $('selector').quantummagazineCalendar(params);
    - params = { notes: notes,theme: "light",backgroundColor : "#ff0000"}
        - notes =  [{ "date": "2018-01-01", "time" : "15:45 AM", "content": "New Year" }, ..] ( vector of notes )
        - theme =  "light" or "dark"
        - backgroundColor => "#ff0000" - any color you want !!

    ---------------------------------------------------------------------------------------------- */

    $.fn.quantummagazineCalendar = function(para) {

        var parent = $(this);
        var notes = para.notes;
        var theme = "dark";
        if(para.theme !== undefined)
            theme = para.theme;
        var backgroundColor = '#fff';
        if(para.backgroundColor !== undefined)
            backgroundColor = para.backgroundColor;

        var weekNames = [ 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat' ,'Sun'];
        var monthNames = [ 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec' ];
        var currentDate = new Date();
        var todayDate = new Date();
        var selectedDate = "";

        var construct = function(){

            var currentMonth = currentDate.getMonth();
            var currentYear = currentDate.getFullYear();

            //HEADER ----------------------------------------------------------------------------------
            var header = $("<div class='quantummagazineCalendar-header'></div>");
            var left = $("<span class='action action-prev-month material-icons'>chevron_left</span>");
            var title = $("<h4>"+monthNames[currentMonth]+" "+currentYear+"</h4>");
            var right = $("<span class='action action-next-month material-icons'>chevron_right</span>");
            header.append(left);
            header.append(title);
            header.append(right);

            // TABLE ----------------------------------------------------------------------------------
            var table = $("<table class='quantummagazineCalendar-table'></table>");
            var table_header = $("<thead></thead>");
            var table_body = $("<tbody></tbody>");
            // THEAD -----------------------------------
            var row = $("<tr></tr>");
            for (var i=0; i < weekNames.length;i++){
                row.append("<th>"+weekNames[i]+"</th>");
            }
            table_header.append(row);
            // TBODY -----------------------------------

            var lastDayOfPrevMonth = new Date(currentYear, currentMonth,0);
            lastDayOfPrevMonth = lastDayOfPrevMonth.getDate();

            var firstDayOfMonth = new Date(currentYear, currentMonth,1);
            var firstDayOfMonthPosition = firstDayOfMonth.getUTCDay();
            var lastDayOfMonth = new Date(currentYear, currentMonth+1, 0);
            lastDayOfMonth = lastDayOfMonth.getDate();

            var DateOfNextMonth = new Date(currentYear, currentMonth + 1, 1);
            var nextMonth = DateOfNextMonth.getMonth();
            var nextYear = DateOfNextMonth.getYear();

            var co = 1;
            var pco = firstDayOfMonthPosition-1;
            var nco = 1;
            var row = $("<tr></tr>");

            for (var i = 1; i < 49 ; i++){

                if( firstDayOfMonthPosition < i && lastDayOfMonth >= co){
                    var td = $("<td class='action-day' data-date='"+co+"' data-month='"+(currentMonth+1)+"' data-year='"+currentYear+"'><div class='cell in'>"+co+"</div></td>");
                    if(todayDate.getDate() === co && todayDate.getMonth() === currentMonth && todayDate.getYear() === currentDate.getYear())
                        td.addClass("today");
                    if (FindNotesInCurrentMonth(new Date(currentYear,currentMonth, co)))
                        td.addClass("note");
                    row.append(td);
                    co ++;
                }else if(lastDayOfMonth < co) {
                    row.append("<td class='action-day' data-date='"+nco+"' data-month='"+(nextMonth+1)+"' data-year='"+nextYear+"'><div class='cell out'>"+nco+"</div></td>");
                    nco ++;
                }else if (firstDayOfMonthPosition >= i) {
                    row.append("<td class='action-day' data-date='" + nco + "' data-month='" + (nextMonth + 1) + "' data-year='" + nextYear + "'><div class='cell out'>" + (lastDayOfPrevMonth - pco) + "</div></td>");
                    pco--;
                }

                if(i%7 === 0){
                    table_body.append(row);
                    row = $("<tr></tr>");
                }

            }
            table.append(table_header);
            table.append(table_body);

            // LIST NOTES ----------------------------------------------------------------------------------
            var notes_month = getNotesOfThisMonth();
            var list = $("<ul class='quantummagazineCalendar-list' style='background-color: "+backgroundColor+"'></ul>");
            for (var i = 0; i < notes_month.length; i++) {
                var li = $("<li><span class='title'>Event "+(i+1)+"</span><span class='info'>"+notes_month[i].content+"</span><span class='date'>"+monthNames[currentMonth]+","+notes_month[i].date+"<sup>th</sup> "+notes_month[i].time+"</span></li>");
                list.append(li);
            }

            // ADD-NOTE ----------------------------------------------------------------------------------
            var idTimerPicker = "timepicker-"+parent.attr("id");
            var add = " <div class='quantummagazineCalendar-add' style='background-color: "+backgroundColor+"'> <div id='"+idTimerPicker+"'></div> <textarea class='note-content' placeholder='Write something...'></textarea> <div class='quantummagazineCalendar-actions'> <span class='action action-add-close material-icons'>close</span> <span class='action action-add-confirm material-icons'>add</span> </div> </div>";

            // FOOTER ---------------------------------------------------------------------------------
            var footer = $("<div class='quantummagazineCalendar-footer'></div>");
            var events = $("<span class='action action-events material-icons'>menu</span>");
            var refresh = $("<span class='action action-refresh material-icons'>refresh</span>");
            footer.append(events);
            footer.append(refresh);

            parent.html("");
            parent.addClass("quantummagazineCalendar");
            parent.addClass("quantummagazineCalendar-"+theme);
            parent.attr("style","background-color:"+backgroundColor);
            parent.append(header);
            parent.append(table);
            parent.append(list);
            parent.append(add);
            parent.append(footer);

            $("#"+idTimerPicker).quantummagazineTimePicker();
        };

        /**
         * @return {boolean}
         */
        var FindNotesInCurrentMonth = function(date) {
            var result = false;
            for (var i = 0; i < notes.length; i++) {
                var dateNote = notes[i].date.split('-');
                var nDate = new Date(dateNote[0], dateNote[1] - 1, dateNote[2]);
                if ( nDate.getFullYear() === date.getFullYear() && nDate.getMonth() === date.getMonth() && nDate.getDate() === date.getDate() ) {
                    result =  true;
                }
            }
            return result;
        };

        var getNotesOfThisMonth = function() {
            var result = [];
            for (var i = 0; i < notes.length; i++) {
                var dateNote = notes[i].date.split('-');
                var nDate = new Date(dateNote[0], dateNote[1] - 1, dateNote[2]);

                if (nDate.getFullYear() === currentDate.getFullYear() && nDate.getMonth() === currentDate.getMonth()) {
                    var temp = {};
                    temp['date'] = nDate.getDate();
                    temp['content'] = notes[i].content;
                    temp['time'] = notes[i].time;
                    result.push(temp);
                }
            }
            return result;
        };

        var nextMonth = function(){
            currentDate = new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 1);
            construct();
        };

        var prevMonth = function(){
            currentDate = new Date(currentDate.getFullYear(), currentDate.getMonth() - 1, 1);
            construct();
        };

        var refresh = function(){
            currentDate = new Date();
            construct();
        };

        var triggerAction = function () {

            $(parent).on('click', '.action-day', function() {
                var selectedDay= $(this).data('date');
                var selectedMonth = $(this).data('month');
                var selectedYear = $(this).data('year');
                selectedDate = selectedYear+"-"+selectedMonth+"-"+selectedDay;
                $(parent).find(".quantummagazineCalendar-add").slideToggle();

            });

            $(parent).on('click', '.action-next-month', function() {
                nextMonth();
            });

            $(parent).on('click', '.action-prev-month', function() {
                prevMonth();
            });

            $(parent).on('click', '.action-refresh', function() {
                refresh();
            });

            $(parent).on('click', '.action-events', function() {
                $(parent).find(".quantummagazineCalendar-list").slideToggle();
            });

            $(parent).on('click', '.action-add-confirm', function() {
                var currentTime = $(parent).find(".quantummagazineTimePicker-input").val();
                var currentNote =  $(parent).find(".note-content").val();
                $(parent).find(".note-content").val("");
                notes.push({ "date": selectedDate, "time" : currentTime, "content": currentNote});
                $(parent).find(".quantummagazineCalendar-add").slideToggle();
                refresh();
            });

            $(parent).on('click', '.action-add-close', function() {
                $(parent).find(".quantummagazineCalendar-add").slideToggle();
            });

        };

        construct();

        triggerAction();
    };



})(jQuery);
