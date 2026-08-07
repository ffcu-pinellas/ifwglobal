/*===================================================================================================

 - TEMPLATE : Bibric
 - DESCRIPTION : MODERN BOOTSTRAP 4 ADMIN TEMPLATE - FULLY RESPONSIVE
 - AUTHOR : bdCoder (http://www.bdcoder.com/)
 - VERSION : 1.0
 - FILE : FORM MASK JS

 ===================================================================================================*/
(function($) {
    "use strict";

    $(document).ready(function () {

        //---------------------------------------------------------------------------------------------
        // - MASK INPUT -------------------------------------------------------------------------------
        //---------------------------------------------------------------------------------------------

        var container = $('.demo-mask');

        //Date
        container.find('.date').inputmask('dd/mm/yyyy', { placeholder: '__/__/____' });

        //Time
        container.find('.time12').inputmask('hh:mm t', { placeholder: '__:__ _m', alias: 'time12', hourFormat: '12' });
        container.find('.time24').inputmask('hh:mm', { placeholder: '__:__ _m', alias: 'time24', hourFormat: '24' });

        //Date Time
        container.find('.datetime').inputmask('d/m/y h:s', { placeholder: '__/__/____ __:__', alias: "datetime", hourFormat: '24' });

        //Mobile Phone Number
        container.find('.mobile-phone-number').inputmask('+99 (999) 999-99-99', { placeholder: '+__ (___) ___-__-__' });
        //Phone Number
        container.find('.phone-number').inputmask('+99 (999) 999-99-99', { placeholder: '+__ (___) ___-__-__' });

        //Dollar Money
        container.find('.money-dollar').inputmask('99,99 $', { placeholder: '__,__ $' });
        //Euro Money
        container.find('.money-euro').inputmask('99,99 €', { placeholder: '__,__ €' });

        //IP Address
        container.find('.ip').inputmask('999.999.999.999', { placeholder: '___.___.___.___' });

        //Credit Card
        container.find('.credit-card').inputmask('9999 9999 9999 9999', { placeholder: '____ ____ ____ ____' });

        //Email
        container.find('.email').inputmask({ alias: "email" });

        //Serial Key
        container.find('.key').inputmask('****-****-****-****', { placeholder: '____-____-____-____' });


    });
})(jQuery);



