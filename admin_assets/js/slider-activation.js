(function($)
{
    "use strict";
    var drivePath = window.location.pathname.replace('/','');

    console.log(drivePath);
    $('.sliderActivationBtn').on('click', function () {
        var selected = $(this).attr('id');
        $.ajax({
            type:'get',
            url: '/'+drivePath + '/'+selected,
            success:function (data) {
                console.log(data)
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
