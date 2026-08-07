/*===================================================================================================

 - TEMPLATE : Bibric
 - DESCRIPTION : MODERN BOOTSTRAP 4 ADMIN TEMPLATE - FULLY RESPONSIVE
 - AUTHOR : bdCoder (http://www.bdcoder.com/)
 - VERSION : 1.0
 - FILE : MAP VECTOR JS

 ===================================================================================================*/
(function($) {
    "use strict";

    $(document).ready(function () {

        //---------------------------------------------------------------------------------------------
        // - VECTOR WORLD MAP -------------------------------------------------------------------------
        //---------------------------------------------------------------------------------------------

        $('#map-vector-world').vectorMap({
            map: 'world_mill',
            backgroundColor: 'transparent',
            zoomMin: 0.95,
            focusOn: {
                x: 0.5,
                y: 0.5,
                scale: 0.95
            },
            regionStyle: {
                initial: {
                    fill: '#464646',
                    stroke: 'none',
                    "stroke-width": 2,
                    "stroke-opacity": 1
                },
                hover: {
                    "fill-opacity": 0.7
                }
            },
            onRegionTipShow: function (e, el) {
                el.html();
            }

        });

        //---------------------------------------------------------------------------------------------
        // - VECTOR USA MAP ---------------------------------------------------------------------------
        //---------------------------------------------------------------------------------------------

        $('#map-vector-usa').vectorMap({
            map: 'us_aea',
            backgroundColor: 'transparent',
            zoomMin: 0.95,
            focusOn: {
                x: 0.5,
                y: 0.5,
                scale: 0.95
            },
            regionStyle: {
                initial: {
                    fill: '#4b5066',
                    stroke: 'none',
                    "stroke-width": 2,
                    "stroke-opacity": 1
                },
                hover: {
                    "fill-opacity": 0.7
                }
            },
            onRegionTipShow: function (e, el) {
                el.html();
            }

        });

        //---------------------------------------------------------------------------------------------
        // - VECTOR CANADA MAP ------------------------------------------------------------------------
        //---------------------------------------------------------------------------------------------

        $('#map-vector-ca').vectorMap({
            map: 'ca_lcc',
            backgroundColor: 'transparent',
            zoomMin: 0.95,
            focusOn: {
                x: 0.5,
                y: 0.5,
                scale: 0.95
            },
            regionStyle: {
                initial: {
                    fill: '#888fa9',
                    stroke: 'none',
                    "stroke-width": 2,
                    "stroke-opacity": 1
                },
                hover: {
                    "fill-opacity": 0.7
                }
            },
            onRegionTipShow: function (e, el) {
                el.html();
            }

        });

        //---------------------------------------------------------------------------------------------
        // - VECTOR FRENCH MAP ------------------------------------------------------------------------
        //---------------------------------------------------------------------------------------------

        $('#map-vector-fr').vectorMap({
            map: 'fr_regions_mill',
            backgroundColor: 'transparent',
            zoomMin: 0.95,
            focusOn: {
                x: 0.5,
                y: 0.5,
                scale: 0.95
            },
            regionStyle: {
                initial: {
                    fill: '#ef2b41',
                    stroke: 'none',
                    "stroke-width": 2,
                    "stroke-opacity": 1
                },
                hover: {
                    "fill-opacity": 0.7
                }
            },
            onRegionTipShow: function (e, el) {
                el.html();
            }

        });

        //---------------------------------------------------------------------------------------------
        // - UPDATE VECTOR MAP ------------------------------------------------------------------------
        //---------------------------------------------------------------------------------------------

        $('#sidebarCollapse').on('click', function () {
            $('#map-vector').vectorMap('get','mapObject').updateSize();
        });

    });
})(jQuery);

