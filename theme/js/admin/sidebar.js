$(document).ready(function () {

    const $sidebar =
        $("#sidebar");

    const desktop =
        () => $(window).width() > 991;

    const recolhida =
        () => (
            desktop()
            && $sidebar.hasClass(
                "collapsed"
            )
        );

    /*
    |--------------------------------------------------------------------------
    | ESTADO SALVO DA SIDEBAR
    |--------------------------------------------------------------------------
    */

    if (desktop()) {

        const sidebarState =
            localStorage.getItem(
                "sidebar"
            );

        if (
            sidebarState
            === "collapsed"
        ) {
            $sidebar.addClass(
                "collapsed"
            );

            $("#content").addClass(
                "expanded"
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | BOTÃO DE RECOLHER / EXPANDIR
    |--------------------------------------------------------------------------
    */

    $("#toggleSidebar").on(
        "click",
        function () {

            fecharFlyouts();

            if (!desktop()) {

                $sidebar.toggleClass(
                    "show"
                );

                return;
            }

            $sidebar.toggleClass(
                "collapsed"
            );

            $("#content").toggleClass(
                "expanded"
            );

            if (
                $sidebar.hasClass(
                    "collapsed"
                )
            ) {
                localStorage.setItem(
                    "sidebar",
                    "collapsed"
                );
            } else {
                localStorage.setItem(
                    "sidebar",
                    "expanded"
                );
            }
        }
    );

    /*
    |--------------------------------------------------------------------------
    | SUBMENUS - MODO EXPANDIDO
    |--------------------------------------------------------------------------
    */

    $(".has-submenu > a").on(
        "click",
        function (event) {

            /*
             * Quando estiver recolhida, o clique
             * no ícone não alterna .open.
             * O submenu é controlado pelo hover.
             */
            if (recolhida()) {
                event.preventDefault();
                return;
            }

            event.preventDefault();

            $(this)
                .parent()
                .toggleClass(
                    "open"
                );
        }
    );

    /*
    |--------------------------------------------------------------------------
    | TÍTULO DO FLYOUT
    |--------------------------------------------------------------------------
    |
    | Ex.:
    | [ícone Financeiro] -> painel lateral:
    |
    | FINANCEIRO
    | Financeiro
    | Pagamentos
    |
    */

    $(".has-submenu").each(
        function () {

            const $item =
                $(this);

            const $submenu =
                $item.children(
                    ".submenu"
                );

            if (
                !$submenu.length
                || $submenu.children(
                    ".submenu-flyout-title"
                ).length
            ) {
                return;
            }

            const titulo =
                $.trim(
                    $item
                        .children("a")
                        .children("span")
                        .first()
                        .text()
                );

            if (!titulo) {
                return;
            }

            $("<li>", {
                class:
                    "submenu-flyout-title",
                text: titulo
            }).prependTo(
                $submenu
            );
        }
    );

    /*
    |--------------------------------------------------------------------------
    | POSICIONAMENTO DO FLYOUT
    |--------------------------------------------------------------------------
    */

    function posicionarFlyout(
        item
    ) {
        const $item =
            $(item);

        const $submenu =
            $item.children(
                ".submenu"
            );

        if (
            !$submenu.length
            || !recolhida()
        ) {
            return;
        }

        const elemento =
            $item.get(0);

        if (!elemento) {
            return;
        }

        const rect =
            elemento
                .getBoundingClientRect();

        const margem =
            10;

        /*
         * Precisamos medir a altura mesmo antes
         * de o painel estar visível.
         */
        const altura =
            $submenu
                .outerHeight()
            || 0;

        const viewport =
            window.innerHeight;

        let top =
            rect.top;

        /*
         * Se faltar espaço abaixo, sobe o menu
         * para que fique dentro da janela.
         */
        if (
            altura > 0
            && top + altura
                > viewport - margem
        ) {
            top =
                viewport
                - altura
                - margem;
        }

        top =
            Math.max(
                margem,
                top
            );

        $submenu.css(
            "--flyout-top",
            top + "px"
        );
    }

    function fecharFlyouts() {
        $(".has-submenu")
            .removeClass(
                "flyout-open"
            );
    }

    /*
    |--------------------------------------------------------------------------
    | HOVER DO MENU RECOLHIDO
    |--------------------------------------------------------------------------
    */

    $(".has-submenu").on(
        "mouseenter",
        function () {

            if (!recolhida()) {
                return;
            }

            fecharFlyouts();

            posicionarFlyout(
                this
            );

            $(this).addClass(
                "flyout-open"
            );
        }
    );

    $(".has-submenu").on(
        "mouseleave",
        function () {

            if (!recolhida()) {
                return;
            }

            $(this).removeClass(
                "flyout-open"
            );
        }
    );

    /*
     * Garante posicionamento correto ao rolar
     * ou redimensionar a janela.
     */
    $(window).on(
        "resize scroll",
        function () {

            if (!recolhida()) {
                fecharFlyouts();
                return;
            }

            $(".has-submenu.flyout-open")
                .each(
                    function () {
                        posicionarFlyout(
                            this
                        );
                    }
                );
        }
    );

    /*
    |--------------------------------------------------------------------------
    | FECHAR AO CLICAR EM UM LINK DO FLYOUT
    |--------------------------------------------------------------------------
    */

    $(".submenu a").on(
        "click",
        function () {
            fecharFlyouts();
        }
    );

    /*
    |--------------------------------------------------------------------------
    | DROPDOWN DO USUÁRIO
    |--------------------------------------------------------------------------
    */

    $(".user-button").on(
        "click",
        function (event) {

            event.stopPropagation();

            $(this)
                .next(
                    ".dropdown-menu"
                )
                .toggleClass(
                    "show"
                );
        }
    );

    $(document).on(
        "click",
        function () {
            $(".dropdown-menu")
                .removeClass(
                    "show"
                );
        }
    );

});
