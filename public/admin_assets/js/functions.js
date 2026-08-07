/*===================================================================================================

 - TEMPLATE : Bibric
 - DESCRIPTION : MODERN BOOTSTRAP 4 ADMIN TEMPLATE - FULLY RESPONSIVE
 - AUTHOR : bdCoder (http://www.bdcoder.com/)
 - VERSION : 1.0
 - FILE : MAIN JS

 ===================================================================================================*/
(function($) {
    "use strict";

    $(document).ready(function () {

        //---------------------------------------------------------------------------------------------
        // - WRAPPR LAOD ------------------------------------------------------------------------------
        //---------------------------------------------------------------------------------------------

        $("#wrapper-load").toggleClass("end");
        setTimeout(function () {
            $("#wrapper-load").hide(0);
        }, 400);

        //---------------------------------------------------------------------------------------------
        // - WRAPPER LEFT -----------------------------------------------------------------------------
        //---------------------------------------------------------------------------------------------

        $(".sidebar-nav > .has-dropdown > .nav-link").on("click", function () {
            var last = $(".sidebar-nav > .has-dropdown.open > a").not(this);
            if ($("#wrapper-left").width() === 80) {
                last.next().toggle("slide", "left");
                $(this).next().toggle("slide", "left");
            } else {
                last.next().slideToggle();
                $(this).next().slideToggle();
            }
            last.parent().toggleClass("open");
            $(this).parent().toggleClass("open");
        });

        $(".sidebar-nav > .has-dropdown .has-dropdown > .nav-link").on("click", function () {
            $(this).find("i").text(($(this).find("i").text() != "chevron_right") ? "chevron_right" : "keyboard_arrow_down");
            $(this).next().slideToggle();
            $(this).parent().toggleClass("open");
        });

        $.fn.updateSidebar = function () {
            var el = $("#wrapper").not(".wrapper-left-fixed");
            var wc = el.height() - 61;
            var ws = $(this).find(".sidebar-container").height();
            if (ws < wc)
                $(this).find(".sidebar-container").css("height", wc + "px");
        };

        //---------------------------------------------------------------------------------------------
        // - SCROLL -----------------------------------------------------------------------------------
        //---------------------------------------------------------------------------------------------

        $(window).scroll(function () {
            var topPos = $(this).scrollTop();
            if (topPos > 100) {
                $("#wrapper-slide").fadeIn();
                $("#timeline-menu").addClass("position-fixed").css({ "top": "60px" });
            } else {
                $("#wrapper-slide").fadeOut();
                if (topPos >= 80) {
                    $("#timeline-menu").addClass("position-fixed").css({ "top": "60px" });

                } else {
                    $("#timeline-menu").removeClass("position-fixed").css({ "top": "0px" });
                }
            }
        });

        //---------------------------------------------------------------------------------------------
        // - TRIGGER ----------------------------------------------------------------------------------
        //---------------------------------------------------------------------------------------------

        $("[data-toggle='class']").on("click", function () {
            var target = $(this).attr("data-target");
            var clss = $(this).attr("toggle-class");
            $(target).toggleClass(clss);
        });

        $("[data-toggle='switch']").each(function () {
            var node = $(this);
            var first = $(this).attr("data-iconFirst");
            var second = $(this).attr("data-iconSecond");
            $(this).parent().on("click", function () {
                node.text(node.text() !== first ? first : second);
            });
        });

        $("[data-toggle='slideUp']").on("click", function () {
            var node = $(this);
            var target = $(this).attr("data-target");
            var ecart = 0;
            if ($(this).attr("data-ecart") != null)
                ecart = $(this).attr("data-ecart");
            $('html, body').animate({ scrollTop: ($(target).offset().top - ecart) }, 900);
        });

        $(".btn-wave-light, .btn-wave-dark").on("click", function (e) {
            var offset = $(this).offset();
            let h = $(this).height();
            let x = e.pageX - offset.left - h / 2;
            let y = e.pageY - offset.top - h / 2;
            var $div = $("<div/>").addClass("wave");
            $div.css({ height: h + 2, width: h + 2, top: y - 2, left: x - 2 }).appendTo($(this));
            setTimeout(function () {

            }, 500);
        });

        $(".btn-flash-light, .btn-flash-dark").on("click", function () {
            var $div = $("<div/>").addClass("flash");
            $div.appendTo($(this));
            setTimeout(function () {
                $div.remove();
            }, 500);
        });

        $(".custom-file-input").on("change", function () {
            var path = $(this).val().split('\\');
            path = (path[path.length - 1] !== "") ? path[path.length - 1] : "";
            $(this).next(".custom-file-label").text(path);
        });

        //---------------------------------------------------------------------------------------------
        // - INITIALISATION ---------------------------------------------------------------------------
        //---------------------------------------------------------------------------------------------

        $("#wrapper-left").updateSidebar();

        $("#time").quantummagazineStartTime();

        $("[data-toggle='tooltip']").tooltip();

        $('[data-toggle="popover"]').popover();

        $('.style-scroll').overlayScrollbars({});

        $('.style-scroll-dark').overlayScrollbars({ className: 'os-theme-light' });

        $('.custom-select').SumoSelect();

        $('.custom-select').on('sumo:opened', function (sumo) {
            var ref = $($(this).next());
            var elm = $(ref.next());
            var popper = new Popper(ref, elm);
        });

        $('.custom-select').each(function () {
            if ($(this).val().length == 0)
                $(this).parent().attr("validation", "false");
            else
                $(this).parent().attr("validation", "true");
        });

        $('.custom-select').on("change", function () {
            if ($(this).val().length == 0)
                $(this).parent().attr("validation", "false");
            else
                $(this).parent().attr("validation", "true");
        });

        $('.SumoSelect > .optWrapper > .options').overlayScrollbars({});

        $('.custom-ranger').bootstrapSlider({
            formatter: function (value) {
                return 'Current value: ' + value;
            }
        });

        $('.custom-color').colorPicker();

        $('.wizard').each(function () {
            var parent = $('.wizard');
            var count = parent.find(".tab-pane").length;
            var indice = 1;
            var values = 100 / count;
            test();
            parent.on("click", ".wizard-submit", function () {

                var current = parent.find('.tab-pane.active');
                if (validate(current) > 0) {
                    current.addClass('was-validated');
                } else {
                    $.notify("Success !", { type: "success", icon: "check" });
                }

            });
            parent.on("click", ".wizard-next", function () {

                var current = parent.find('.tab-pane.active');
                if (validate(current) > 0) {
                    current.addClass('was-validated');
                } else {
                    current.removeClass('was-validated');
                    var current_statu = parent.find('.wizard-item.active');
                    current.removeClass('active');
                    current.next().addClass('active');
                    current_statu.next().addClass('active ');
                    indice = indice + 1;
                    test();
                }

            });
            parent.on("click", ".wizard-previous", function () {
                var current = parent.find('.tab-pane.active');
                var current_statu = parent.find('.wizard-item').eq(indice - 1);
                current.removeClass('active');
                current.prev().addClass('active');
                current_statu.removeClass('active');
                indice = indice - 1;
                test();
            });
            function test() {
                parent.find(".progress-bar").css("width", "" + (values * indice) + "%");
                switch (indice) {
                    case 1:
                        parent.find(".wizard-previous").hide();
                        parent.find(".wizard-submit").hide();
                        break;
                    case count:
                        parent.find(".wizard-next").hide();
                        parent.find(".wizard-submit").show();
                        break;
                    default:
                        parent.find(".wizard-previous").show();
                        parent.find(".wizard-next").show();
                        parent.find(".wizard-submit").hide();
                        break;
                }
            }
            function validate(current) {
                var co = 0;
                current.find('*[required]').each(function () {
                    if ($(this).val() == '')
                        co = co + 1;
                });
                return co;
            }
        });

        //---------------------------------------------------------------------------------------------
        // - OPTION -----------------------------------------------------------------------------------
        //---------------------------------------------------------------------------------------------

        $('#option-layout , #option-layout-navbar, #option-layout-sidebar').on ('change', function () {
            var style = $(this).attr("option");
            if ($(this).is(":checked")) {
                if (!$("#wrapper").hasClass(style)) {
                    $("#wrapper").addClass(style);
                }
            } else {
                $("#wrapper").removeClass(style);
            }

            if (style === "wrapper-box")
                $("body").toggleClass("bg-light");
            if (style === "wrapper-left-fixed") {
                $("#wrapper-left").updateSidebar();
            }

        });

        $("#option-texture a").on("click", function () {
            var texture = $(this).attr("texture");
            if (texture === "style-1")
                $("#wrapper").css("background-image", " url(assets/img/texture/brushed-alum.png)");
            else
                $("#wrapper").css("background-image", " url(assets/img/texture/low-contrast-line.png)");

        });

        $('#option-style-navbar , #option-style-sidebar').change(function () {
            var type = $(this).attr("option");
            if ($(this).is(":checked")) {
                $("." + type).addClass(type + "-dark");
                $("." + type).removeClass(type + "-light");
            } else {
                $("." + type).addClass(type + "-light");
                $("." + type).removeClass(type + "-dark");
            }
        });

        $("#option-background-navbar a").on("click", function () {
            $(".navbar").removeClass(function (index, className) {
                return (className.match(/(^|\s)bg-\S+/g) || []).join(' ');
            });
            $(".navbar").addClass($(this).attr("color"));
        });

        $("#option-bakcground-sidebar a").on("click", function () {
            $(".sidebar").removeClass(function (index, className) {
                return (className.match(/(^|\s)bg-\S+/g) || []).join(' ');
            });
            $(".sidebar").addClass($(this).attr("color"));
        });

        $("#option-color-navbar a").on("click", function () {
            $(".navbar").removeClass(function (index, className) {
                return (className.match(/(navbar-primary|navbar-secondary|navbar-success|navbar-info|navbar-warning|navbar-danger)/g).join(' '));
            });
            $(".navbar").addClass($(this).attr("color"));
        });

        $("#option-color-sidebar a").on("click", function () {
            $(".sidebar").removeClass(function (index, className) {
                return (className.match(/(sidebar-primary|sidebar-secondary|sidebar-success|sidebar-info|sidebar-warning|sidebar-danger)/g).join(' '));
            });
            $(".sidebar").addClass($(this).attr("color"));
        })

    });

    // submit Logout form
    $("#logOut").on("click", function (){
        document.getElementById('logOutForm').submit();
    });


    // delete with confirm code
    const deleteWithConfirmButton = document.getElementById('delete_with_confirm_button');

    if (deleteWithConfirmButton) {
        deleteWithConfirmButton.addEventListener('click', function () {
            swal({
                title: "Are you sure?",
                icon: "warning",
                buttons: true,
                dangerMode: true,
            })
                .then((willDelete) => {
                    if (willDelete) {
                        this.closest('form').submit();
                    } else {
                    }
                });
        })
    }


})(jQuery);



