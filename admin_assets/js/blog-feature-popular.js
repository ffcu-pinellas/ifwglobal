(function($)
{
    "use strict";
    var drivePath = window.location.pathname.replace('/','');


    $('.blogIsPopularBtn').on('click', function () {
        var selected = $(this).attr('id');
        $.ajax({
            type:'get',
            url: '/'+drivePath + '/'+selected,
            success:function (data) {
                // do nothing;
                if(data.status){
                    toastr.error(data.msg);
                }else if(!data.status){
                    toastr.warning(data.msg);
                }else{
                    toastr.success(data.msg);
                }
            }
        });
    });

    $('.blogIsFeaturedBtn').on('click', function () {
        var selected = $(this).attr('id');
        $.ajax({
            type:'get',
            url: '/'+drivePath + '/featured/'+selected,
            success:function (data) {
                // do nothing;
                if(data.status){
                    toastr.error(data.msg);
                }else if(!data.status){
                    toastr.warning(data.msg);
                }else{
                    toastr.success(data.msg);
                }
            }
        });
    });
})(jQuery);
