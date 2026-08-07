(function($)
{
    "use strict";

    $('.thumbnail-image').imageUploader({
        imagesInputName: 'bg_image',
        maxFiles: 1,
    });

    $('.feature-image').imageUploader({
        imagesInputName: 'feature_img',
        maxFiles: 1,
    });


    $('.blog_tag_multiple_input').select2();

})(jQuery);

