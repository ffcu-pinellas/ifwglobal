(function($)
{
    "use strict";
    $('#addBlogCategoryBtn').on('click', blogCategoryAdd);
    $('.blogCategoryEditBtn').on('click', blogCategoryShow);

    function blogCategoryAdd(){
        $('#blogCategoryModal').modal('show');
    }

    function blogCategoryShow(){
        $.ajax({
            type:'get',
            url: '/admin/blog/category/'+$(this).attr('data-id'),
            success:function (data){
                blogCategoryEdit(data);
            }
        })

    }

    function blogCategoryEdit(category){
        $('#blogCategoryModal').modal('show').on('hidden.bs.modal', function (e) {
            window.location.reload();
        }).find('.modal-content').empty().append(category);
    }

})(jQuery);
