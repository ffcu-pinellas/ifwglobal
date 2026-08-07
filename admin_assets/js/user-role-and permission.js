(function ($){
    "use script";

    const route = window.location.pathname;
    //create role
    if (route.includes('role/create')){
        $('.permission_multiple_input').select2();
    }

    //edit role
    if (route.includes('role/edit')){
        $('.permission_multiple_input').select2();

        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });
        $.ajax({
            type: 'post',
            url: route,
            success:function (data){
                $(".permission_multiple_input").val(data).trigger('change');
            }
        })
    }

    //delete role
    $(document).on('click', '.roleDestroyBtn', function (e){
        e.preventDefault();
        swal({
            title: "Are you sure?",
            text: "Once you delete, You can not recover this data and related files.",
            icon: "warning",
            dangerMode: true,
            buttons: {
                cancel: true,
                confirm: {
                    text: "Delete",
                    value: true,
                    visible: true,
                    closeModal: true
                },
            },
        }).then((value) => {
            if(value){
                $(this).find('.deleteForm').submit();
            }
        });
    });
})(jQuery)
