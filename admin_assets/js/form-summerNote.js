/*===================================================================================================

 - TEMPLATE : Bibric
 - DESCRIPTION : MODERN BOOTSTRAP 4 ADMIN TEMPLATE - FULLY RESPONSIVE
 - AUTHOR : bdCoder (http://www.bdcoder.com/)
 - VERSION : 1.0
 - FILE : FORM SUMMERNOTE JS

 ===================================================================================================*/
(function($) {
    "use strict";

    $(document).ready(function () {

        //---------------------------------------------------------------------------------------------
        // - SUMMERNOTE INIT --------------------------------------------------------------------------
        //---------------------------------------------------------------------------------------------

        $('textarea.bapric_edittor').summernote({
            placeholder: 'Wright your blog................',
            minHeight: 250,
            maxHeight: 400,
            styleTags: ['p', 'pre', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'],
            fontNames: ['Arial', 'Galada', 'Kalpurush', 'Roboto', 'Times New Roman', 'Verdana'],
            fontNamesIgnoreCheck: ['Roboto', 'Galada', 'Kalpurush'],
            toolbar: [
                ['style', ['style']],
                ['font', ['bold', 'underline', 'clear']],
                ['fontname', ['fontname']],
                ['fontsize', ['fontsize']],
                ['color', ['color']],
                ['para', ['ul', 'ol', 'paragraph']],
                ['table', ['table']],
                ['insert', ['link', 'picture', 'video']],
                ['view', ['fullscreen', 'codeview', 'help']]
            ]
        });


    });

})(jQuery);


