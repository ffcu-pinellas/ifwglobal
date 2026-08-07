/*===================================================================================================

 - TEMPLATE : Bibric
 - DESCRIPTION : MODERN BOOTSTRAP 4 ADMIN TEMPLATE - FULLY RESPONSIVE
 - AUTHOR : bdCoder (http://www.bdcoder.com/)
 - VERSION : 1.0
 - FILE : FILE MANAGER JS

 ===================================================================================================*/
(function($) {
    "use strict";

    $(document).ready(function () {

        $('#mail-nav-toggle').on('click', function(){
            $(".box-mail .nav").slideToggle();
        });


        $("#exemple_4").circliful({
            animation: 1,
            animationStep: 1,
            target: 10,
            start: 2,
            showPercent: 1,
            foregroundColor: '#888fa9',
            fontColor: '#464646',
            iconColor: '#464646',
            icon: 'f0a0',
            iconSize: '40',
            iconPosition: 'middle'
        });

        //---------------------------------------------------------------------------------------------
        // - EXEMPLE 5 --------------------------------------------------------------------------------
        //---------------------------------------------------------------------------------------------

        $("#exemple_5").circliful({
            animationStep: 5,
            foregroundBorderWidth: 5,
            foregroundColor: '#888fa9',
            backgroundBorderWidth: 15,
            percent: 80,
            icon: 'f0a0',
            iconPosition: 'middle',
            text: 'Space Left',
            textBelow: true
        });

        //---------------------------------------------------------------------------------------------
        // - EXEMPLE 6 --------------------------------------------------------------------------------
        //---------------------------------------------------------------------------------------------

        $("#exemple_6").circliful({
            animation: 1,
            animationStep: 5,
            foregroundBorderWidth: 7,
            backgroundBorderWidth: 7,
            textSize: 28,
            textStyle: 'font-size: 12px;',
            textColor: '#666',
            multiPercentage: 1,
            percentages: [
                {'percent': 30, 'color': '#4b5066', 'title': 'Primary' },
                {'percent': 40, 'color': '#888fa9', 'title': 'Info' },
                {'percent': 60, 'color': '#cbc8bf', 'title': 'Secondary' },
                {'percent': 70, 'color': '#ff7400', 'title': 'Warning' }
            ],
            multiPercentageLegend: 0,
            replacePercentageByText: '',
            backgroundColor: '#eee',
            icon: 'f0d0',
            iconPosition: 'middle',
            iconColor: '#273B4E'
        });

        //---------------------------------------------------------------------------------------------
        // - EXEMPLE 7 --------------------------------------------------------------------------------
        //---------------------------------------------------------------------------------------------

        $("#exemple_7").circliful({
            animation: 1,
            animationStep: 5,
            foregroundBorderWidth: 25,
            foregroundColor: '#888fa9',
            backgroundBorderWidth: 15,
            percent: 45,
            textSize: 28,
            textStyle: 'font-size: 12px;',
            textColor: '#666'
        });

        //---------------------------------------------------------------------------------------------
        // - EXEMPLE 8 --------------------------------------------------------------------------------
        //---------------------------------------------------------------------------------------------

        $("#exemple_8").circliful({
            animation: 1,
            animationStep: 5,
            foregroundBorderWidth: 15,
            foregroundColor: '#888fa9',
            backgroundBorderWidth: 25,
            percent: 59,
            textSize: 28,
            textStyle: 'font-size: 12px;',
            textColor: '#666'
        });

    });

})(jQuery);

