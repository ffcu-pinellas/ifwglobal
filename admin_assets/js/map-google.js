/*===================================================================================================

 - TEMPLATE : Bibric
 - DESCRIPTION : MODERN BOOTSTRAP 4 ADMIN TEMPLATE - FULLY RESPONSIVE
 - AUTHOR : bdCoder (http://www.bdcoder.com/)
 - VERSION : 1.0
 - FILE : MAP GOOGLE JS

 ===================================================================================================*/

//---------------------------------------------------------------------------------------------
// - GOOGLE MAP -------------------------------------------------------------------------------
//---------------------------------------------------------------------------------------------
(function($) {
    "use strict";

    function myMap() {

        //---------------------------------------------------------------------------------------------
        // - WORLD MAP --------------------------------------------------------------------------------
        //---------------------------------------------------------------------------------------------

        var mapCanvas = document.getElementById("map-google-world");
        var mapOptions = {
            center: new google.maps.LatLng(20,0),
            zoom: 2
        };
        var map = new google.maps.Map(mapCanvas, mapOptions);

        //---------------------------------------------------------------------------------------------
        // - USA MAP ----------------------------------------------------------------------------------
        //---------------------------------------------------------------------------------------------

        var mapCanvas = document.getElementById("map-google-usa");
        var mapOptions = {
            center: new google.maps.LatLng(39.06612208192391,-101.29121961107398),
            zoom: 3,
            styles: [
                {
                    "featureType": "all",
                    "elementType": "labels.text.fill",
                    "stylers": [
                        {
                            "saturation": 36
                        },
                        {
                            "color": "#000000"
                        },
                        {
                            "lightness": 40
                        }
                    ]
                },
                {
                    "featureType": "all",
                    "elementType": "labels.text.stroke",
                    "stylers": [
                        {
                            "visibility": "on"
                        },
                        {
                            "color": "#000000"
                        },
                        {
                            "lightness": 16
                        }
                    ]
                },
                {
                    "featureType": "all",
                    "elementType": "labels.icon",
                    "stylers": [
                        {
                            "visibility": "off"
                        }
                    ]
                },
                {
                    "featureType": "administrative",
                    "elementType": "geometry.fill",
                    "stylers": [
                        {
                            "color": "#000000"
                        },
                        {
                            "lightness": 20
                        }
                    ]
                },
                {
                    "featureType": "administrative",
                    "elementType": "geometry.stroke",
                    "stylers": [
                        {
                            "color": "#000000"
                        },
                        {
                            "lightness": 17
                        },
                        {
                            "weight": 1.2
                        }
                    ]
                },
                {
                    "featureType": "landscape",
                    "elementType": "geometry",
                    "stylers": [
                        {
                            "color": "#000000"
                        },
                        {
                            "lightness": 20
                        }
                    ]
                },
                {
                    "featureType": "poi",
                    "elementType": "geometry",
                    "stylers": [
                        {
                            "color": "#000000"
                        },
                        {
                            "lightness": 21
                        }
                    ]
                },
                {
                    "featureType": "road.highway",
                    "elementType": "geometry.fill",
                    "stylers": [
                        {
                            "color": "#000000"
                        },
                        {
                            "lightness": 17
                        }
                    ]
                },
                {
                    "featureType": "road.highway",
                    "elementType": "geometry.stroke",
                    "stylers": [
                        {
                            "color": "#000000"
                        },
                        {
                            "lightness": 29
                        },
                        {
                            "weight": 0.2
                        }
                    ]
                },
                {
                    "featureType": "road.arterial",
                    "elementType": "geometry",
                    "stylers": [
                        {
                            "color": "#000000"
                        },
                        {
                            "lightness": 18
                        }
                    ]
                },
                {
                    "featureType": "road.local",
                    "elementType": "geometry",
                    "stylers": [
                        {
                            "color": "#000000"
                        },
                        {
                            "lightness": 16
                        }
                    ]
                },
                {
                    "featureType": "transit",
                    "elementType": "geometry",
                    "stylers": [
                        {
                            "color": "#000000"
                        },
                        {
                            "lightness": 19
                        }
                    ]
                },
                {
                    "featureType": "water",
                    "elementType": "geometry",
                    "stylers": [
                        {
                            "color": "#000000"
                        },
                        {
                            "lightness": 17
                        }
                    ]
                }
            ]
        };
        var map = new google.maps.Map(mapCanvas, mapOptions);

        //---------------------------------------------------------------------------------------------
        // - CANADA MAP -------------------------------------------------------------------------------
        //---------------------------------------------------------------------------------------------

        var mapCanvas = document.getElementById("map-google-ca");
        var mapOptions = {
            center: new google.maps.LatLng(61.12467682184264,-113.94746961107398),
            zoom: 3,
            styles:[
                {
                    "featureType": "administrative",
                    "elementType": "all",
                    "stylers": [
                        {
                            "saturation": "-100"
                        }
                    ]
                },
                {
                    "featureType": "administrative.province",
                    "elementType": "all",
                    "stylers": [
                        {
                            "visibility": "off"
                        }
                    ]
                },
                {
                    "featureType": "landscape",
                    "elementType": "all",
                    "stylers": [
                        {
                            "saturation": -100
                        },
                        {
                            "lightness": 65
                        },
                        {
                            "visibility": "on"
                        }
                    ]
                },
                {
                    "featureType": "poi",
                    "elementType": "all",
                    "stylers": [
                        {
                            "saturation": -100
                        },
                        {
                            "lightness": "50"
                        },
                        {
                            "visibility": "simplified"
                        }
                    ]
                },
                {
                    "featureType": "road",
                    "elementType": "all",
                    "stylers": [
                        {
                            "saturation": "-100"
                        }
                    ]
                },
                {
                    "featureType": "road.highway",
                    "elementType": "all",
                    "stylers": [
                        {
                            "visibility": "simplified"
                        }
                    ]
                },
                {
                    "featureType": "road.arterial",
                    "elementType": "all",
                    "stylers": [
                        {
                            "lightness": "30"
                        }
                    ]
                },
                {
                    "featureType": "road.local",
                    "elementType": "all",
                    "stylers": [
                        {
                            "lightness": "40"
                        }
                    ]
                },
                {
                    "featureType": "transit",
                    "elementType": "all",
                    "stylers": [
                        {
                            "saturation": -100
                        },
                        {
                            "visibility": "simplified"
                        }
                    ]
                },
                {
                    "featureType": "water",
                    "elementType": "geometry",
                    "stylers": [
                        {
                            "hue": "#ffff00"
                        },
                        {
                            "lightness": -25
                        },
                        {
                            "saturation": -97
                        }
                    ]
                },
                {
                    "featureType": "water",
                    "elementType": "labels",
                    "stylers": [
                        {
                            "lightness": -25
                        },
                        {
                            "saturation": -100
                        }
                    ]
                }
            ]
        };
        var map = new google.maps.Map(mapCanvas, mapOptions);

        //---------------------------------------------------------------------------------------------
        // - FRENCH MAP -------------------------------------------------------------------------------
        //---------------------------------------------------------------------------------------------

        var mapCanvas = document.getElementById("map-google-fr");
        var mapOptions = {
            center: new google.maps.LatLng( 47.268054539387315,2.134073357676016),
            zoom: 5,
            styles:[
                {
                    "elementType": "geometry",
                    "stylers": [
                        {
                            "hue": "#ff4400"
                        },
                        {
                            "saturation": -68
                        },
                        {
                            "lightness": -4
                        },
                        {
                            "gamma": 0.72
                        }
                    ]
                },
                {
                    "featureType": "road",
                    "elementType": "labels.icon"
                },
                {
                    "featureType": "landscape.man_made",
                    "elementType": "geometry",
                    "stylers": [
                        {
                            "hue": "#0077ff"
                        },
                        {
                            "gamma": 3.1
                        }
                    ]
                },
                {
                    "featureType": "water",
                    "stylers": [
                        {
                            "hue": "#00ccff"
                        },
                        {
                            "gamma": 0.44
                        },
                        {
                            "saturation": -33
                        }
                    ]
                },
                {
                    "featureType": "poi.park",
                    "stylers": [
                        {
                            "hue": "#44ff00"
                        },
                        {
                            "saturation": -23
                        }
                    ]
                },
                {
                    "featureType": "water",
                    "elementType": "labels.text.fill",
                    "stylers": [
                        {
                            "hue": "#007fff"
                        },
                        {
                            "gamma": 0.77
                        },
                        {
                            "saturation": 65
                        },
                        {
                            "lightness": 99
                        }
                    ]
                },
                {
                    "featureType": "water",
                    "elementType": "labels.text.stroke",
                    "stylers": [
                        {
                            "gamma": 0.11
                        },
                        {
                            "weight": 5.6
                        },
                        {
                            "saturation": 99
                        },
                        {
                            "hue": "#0091ff"
                        },
                        {
                            "lightness": -86
                        }
                    ]
                },
                {
                    "featureType": "transit.line",
                    "elementType": "geometry",
                    "stylers": [
                        {
                            "lightness": -48
                        },
                        {
                            "hue": "#ff5e00"
                        },
                        {
                            "gamma": 1.2
                        },
                        {
                            "saturation": -23
                        }
                    ]
                },
                {
                    "featureType": "transit",
                    "elementType": "labels.text.stroke",
                    "stylers": [
                        {
                            "saturation": -64
                        },
                        {
                            "hue": "#ff9100"
                        },
                        {
                            "lightness": 16
                        },
                        {
                            "gamma": 0.47
                        },
                        {
                            "weight": 2.7
                        }
                    ]
                }
            ]
        };
        var map = new google.maps.Map(mapCanvas, mapOptions);

    }

})(jQuery);


