/*===================================================================================================

 - TEMPLATE : Bibric
 - DESCRIPTION : MODERN BOOTSTRAP 4 ADMIN TEMPLATE - FULLY RESPONSIVE
 - AUTHOR : bdCoder (http://www.bdcoder.com/)
 - VERSION : 1.0
 - FILE : TIMELINE JS

 ===================================================================================================*/
(function($) {
    "use strict";

    $(document).ready(function () {

        //---------------------------------------------------------------------------------------------
        // - INITIALISATION ---------------------------------------------------------------------------
        //---------------------------------------------------------------------------------------------

        var line_chart_data = [
            {
                label: 'Internal',
                backgroundColor: "rgba(0,0,0,0.05)",
                borderColor: "rgba(0,0,0,0.125)",
                borderWidth: 2,
                data: [40, 20, 30, 30, 50, 60, 70, 65, 75, 80, 60]
            }
        ];

        var line_chart_config = {
            type: 'line',

            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
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
                        display: false,
                        ticks: {
                            min: 0,
                            max: 100,
                            stepSize: 25
                        }
                    }]
                },
                legend: {
                    display: false
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

    });
})(jQuery);

