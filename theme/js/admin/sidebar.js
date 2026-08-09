$(document).ready(function () {

    // Recupera estado salvo
    if ($(window).width() > 991) {

        let sidebarState = localStorage.getItem("sidebar");

        if (sidebarState === "collapsed") {
            $("#sidebar").addClass("collapsed");
            $("#content").addClass("expanded");
        }

    }

    // Botão do menu
    $("#toggleSidebar").on("click", function () {

        if ($(window).width() <= 991) {

            $("#sidebar").toggleClass("show");

        } else {

            $("#sidebar").toggleClass("collapsed");
            $("#content").toggleClass("expanded");

            if ($("#sidebar").hasClass("collapsed")) {
                localStorage.setItem("sidebar", "collapsed");
            } else {
                localStorage.setItem("sidebar", "expanded");
            }

        }

    });

    // Submenus
    $(".has-submenu").each(function (i) {

        if (localStorage.getItem("submenu_" + i) == "open") {
            $(this).addClass("open");
        }

    });

    $(".has-submenu > a").on("click", function (e) {

        if ($("#sidebar").hasClass("collapsed")) {
            return;
        }

        e.preventDefault();

        $(this).parent().toggleClass("open");

    });

    // Dropdown do usuário
    $(".user-button").on("click", function (e) {

        e.stopPropagation();

        $(this).next(".dropdown-menu").toggleClass("show");

    });

    $(document).on("click", function () {

        $(".dropdown-menu").removeClass("show");

    });

});