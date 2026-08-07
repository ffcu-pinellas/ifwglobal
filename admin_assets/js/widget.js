/*===================================================================================================

 - TEMPLATE : Bibric
 - DESCRIPTION : MODERN BOOTSTRAP 4 ADMIN TEMPLATE - FULLY RESPONSIVE
 - AUTHOR : bdCoder (http://www.bdcoder.com/)
 - VERSION : 1.0
 - FILE : WIDGET JS

 ===================================================================================================*/
(function($) {
    "use strict";

    $(document).ready(function () {

        //---------------------------------------------------------------------------------------------
        // - CHART CIRCLE -----------------------------------------------------------------------------
        //---------------------------------------------------------------------------------------------

        $("#chart-visitors").circliful({
            animation: 1,
            animationStep: 5,
            foregroundBorderWidth: 15,
            foregroundColor: '#888fa9',
            backgroundBorderWidth: 15,
            percent: 13,
            textSize: 28,
            textStyle: 'font-size: 12px;',
            textColor: '#666'
        });

        //---------------------------------------------------------------------------------------------
        // - CHART LINE  ------------------------------------------------------------------------------
        //---------------------------------------------------------------------------------------------

        var line_chart_data = [
            {
                label: 'Internal',
                backgroundColor: "rgba(0,0,0, 0.2)",
                borderColor: "rgba(255,255,255,0.9)",
                borderWidth: 2,
                data: [40,20,30,30,50,60,70,65,75,80,60]
            }
        ];

        var line_chart_config = {
            type: 'line',

            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'Jun', 'Jul', 'Aug','Sep','Oct','Nov','Dec'],
                datasets: line_chart_data
            },
            options: {
                elements: {
                    line: {
                        tension: 0
                    },
                    point: {
                        radius: 0
                    }
                },
                scales: {
                    xAxes: [{
                        display: false
                    }],
                    yAxes: [{
                        display: false
                    }]
                },
                legend: {
                    display:false
                },
                tooltips: {
                    enabled: false
                },
                maintainAspectRatio: false,
                responsive: true
            }
        };

        var line_1_chart_ctx = document.getElementById("chart-line-1").getContext("2d");

        new Chart(line_1_chart_ctx, line_chart_config);

        var line_2_chart_ctx = document.getElementById("chart-line-2").getContext("2d");

        new Chart(line_2_chart_ctx, line_chart_config);


        //---------------------------------------------------------------------------------------------
        // - Bibric CIRCLE TIME  ------------------------------------------------------------------------------
        //---------------------------------------------------------------------------------------------

        $("#timeCircle").quantummagazineCircleTime();

    });
})(jQuery);
