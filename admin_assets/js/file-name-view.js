(function ($){
    "use script";
    $(document).ready(function(){
        $('input[type="file"]').change(function(e){
            var fileName = e.target.files[0].name;
            $('#showSelectedFile').html(fileName);
        });
    });
})(jQuery);
