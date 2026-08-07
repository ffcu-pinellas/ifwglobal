/*===================================================================================================

 - TEMPLATE : Bibric
 - DESCRIPTION : MODERN BOOTSTRAP 4 ADMIN TEMPLATE - FULLY RESPONSIVE
 - AUTHOR : bdCoder (http://www.bdcoder.com/)
 - VERSION : 1.0
 - FILE : CHART JS

 ===================================================================================================*/
(function($) {
    "use strict";

    $(document).ready(function () {

        //---------------------------------------------------------------------------------------------
        // - BAR CHART --------------------------------------------------------------------------------
        //---------------------------------------------------------------------------------------------

        var bar_chart_data =  {
            labels: ["January", "February", "March", "April", "May", "June", "July"],
            datasets: [{
                label: 'Dataset',
                backgroundColor: "rgba(239,43,65,0.7)",
                borderColor: "#ef2b41",
                borderWidth: 1,
                data: [
                    50,
                    60,
                    90,
                    60,
                    80,
                    50,
                    20
                ]
            }]};

        var bar_chart_config = {
            type: 'bar',
            data: bar_chart_data,
            options: {
                responsive: true,
                legend: {
                    position: 'top'
                },
                title: {
                    display: true,
                    text: 'Bar Chart'
                }
            }
        };

        var bar_chart_ctx = document.getElementById("bar-chart").getContext("2d");

        new Chart(bar_chart_ctx, bar_chart_config);

        //---------------------------------------------------------------------------------------------
        // - LINE CHART -------------------------------------------------------------------------------
        //---------------------------------------------------------------------------------------------

        var line_chart_data =  {
            labels: ["January", "February", "March", "April", "May", "June", "July"],
            datasets: [{
                label: 'Dataset',
                backgroundColor: "rgba(239,43,65,0.7)",
                borderColor: "#ef2b41",
                borderWidth: 1,
                data: [
                    50,
                    60,
                    90,
                    60,
                    80,
                    50,
                    20
                ]
            }]};

        var line_chart_config = {
            type: 'line',
            data: line_chart_data,
            options: {
                responsive: true,
                legend: {
                    position: 'top'
                },
                title: {
                    display: true,
                    text: 'Line Chart'
                }
            }
        };

        var line_chart_ctx = document.getElementById("line-chart").getContext("2d");

        new Chart(line_chart_ctx, line_chart_config);

        //---------------------------------------------------------------------------------------------
        // - PIE CHART --------------------------------------------------------------------------------
        //---------------------------------------------------------------------------------------------

        var pie_chart_data =  {
            labels: ["January", "February", "March", "April", "May", "June", "July"],
            datasets: [{
                label: "Dataset",
                backgroundColor:[
                    "#ef2b41",
                    "#db2b3f",
                    "#c72a3a",
                    "#b32731",
                    "#9f1625",
                    "#8b141d",
                    "#761c1d"
                ],
                data: [
                    50,
                    60,
                    90,
                    60,
                    80,
                    50,
                    20
                ]
            }]};

        var pie_chart_config = {
            type: 'pie',
            data: pie_chart_data,
            options: {
                responsive: true,
                legend: {
                    position: 'top'
                },
                title: {
                    display: true,
                    text: 'Pie Chart'
                }
            }
        };

        var pie_chart_ctx = document.getElementById("pie-chart").getContext("2d");

        new Chart(pie_chart_ctx, pie_chart_config);

        //---------------------------------------------------------------------------------------------
        // - DOUGHNUT CHART ---------------------------------------------------------------------------
        //---------------------------------------------------------------------------------------------

        var doughnut_chart_data =  {
            labels: ["January", "February", "March", "April", "May", "June", "July"],
            datasets: [{
                label: "Dataset",
                backgroundColor:[
                    "#ef2b41",
                    "#db2b3f",
                    "#c72a3a",
                    "#b32731",
                    "#9f1625",
                    "#8b141d",
                    "#761c1d"
                ],
                data: [
                    50,
                    60,
                    90,
                    60,
                    80,
                    50,
                    20
                ]
            }]};

        var doughnut_chart_config = {
            type: 'doughnut',
            data: doughnut_chart_data,
            options: {
                responsive: true,
                legend: {
                    position: 'top'
                },
                title: {
                    display: true,
                    text: 'Doughnut Chart'
                }
            }
        };

        var doughnut_chart_ctx = document.getElementById("doughnut-chart").getContext("2d");

        new Chart(doughnut_chart_ctx, doughnut_chart_config);

        //---------------------------------------------------------------------------------------------
        // - RADAR CHART ------------------------------------------------------------------------------
        //---------------------------------------------------------------------------------------------

        var radar_chart_data =  {
            labels: ["January", "February", "March", "April", "May", "June", "July"],
            datasets: [{
                label: 'Dataset',
                backgroundColor: "rgba(239,43,65,0.7)",
                borderColor: "#ef2b41",
                borderWidth: 1,
                data: [
                    50,
                    60,
                    90,
                    60,
                    80,
                    50,
                    20
                ]
            }]};

        var radar_chart_config = {
            type: 'radar',
            data: radar_chart_data,
            options: {
                responsive: true,
                legend: {
                    position: 'top'
                },
                title: {
                    display: true,
                    text: 'Radar Chart'
                }
            }
        };

        var radar_chart_ctx = document.getElementById("radar-chart").getContext("2d");

        new Chart(radar_chart_ctx, radar_chart_config);

        //---------------------------------------------------------------------------------------------
        // - POLAR CHART ------------------------------------------------------------------------------
        //---------------------------------------------------------------------------------------------

        var polar_chart_data =  {
            labels: ["January", "February", "March", "April", "May", "June", "July"],
            datasets: [{
                label: "Dataset",
                backgroundColor:[
                    "#ef2b41",
                    "#db2b3f",
                    "#c72a3a",
                    "#b32731",
                    "#9f1625",
                    "#8b141d",
                    "#761c1d"
                ],
                data: [
                    50,
                    60,
                    90,
                    60,
                    80,
                    50,
                    20
                ]
            }]};

        var polar_chart_config = {
            type: 'polarArea',
            data: polar_chart_data,
            options: {
                responsive: true,
                legend: {
                    position: 'top'
                },
                title: {
                    display: true,
                    text: 'Polar Chart'
                }
            }
        };

        var polar_chart_ctx = document.getElementById("polar-chart").getContext("2d");

        new Chart(polar_chart_ctx, polar_chart_config);

        //---------------------------------------------------------------------------------------------
        // - BAR-LINE CHART ---------------------------------------------------------------------------
        //---------------------------------------------------------------------------------------------

        var barLine_chart_data =  {
            labels: ["January", "February", "March", "April", "May", "June", "July"],
            datasets: [{
                type: 'line',
                label: 'Dataset Line',
                borderColor: "#ef2b41",
                borderWidth: 1,
                fill: false,
                data: [
                    50,
                    60,
                    90,
                    60,
                    80,
                    50,
                    20
                ]
            }, {
                type: 'bar',
                label: 'Dataset Bar',
                backgroundColor: "rgba(239,43,65,0.7)",
                borderColor: "#ef2b41",
                borderWidth: 1,
                data: [
                    40,
                    50,
                    80,
                    50,
                    70,
                    40,
                    10
                ]
            }]

        };

        var barLine_chart_config = {
            type: 'bar',
            data: barLine_chart_data,
            options: {
                responsive: true,
                title: {
                    display: true,
                    text: 'Bar-Line Chart'
                }
            }
        };

        var barLine_chart_ctx = document.getElementById("barLine-chart").getContext("2d");

        new Chart(barLine_chart_ctx, barLine_chart_config);

    });

})(jQuery);

