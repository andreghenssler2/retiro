(() => {
    "use strict";

    const container =
        document.getElementById(
            "termosEventoContainer"
        );

    const addButton =
        document.getElementById(
            "adicionarTermoEvento"
        );

    if (!container || !addButton) {
        return;
    }

    const createRow = () => {
        const index =
            `n${Date.now()}${Math.floor(
                Math.random() * 1000
            )}`;

        const wrapper =
            document.createElement(
                "div"
            );

        wrapper.className =
            "border rounded p-3 "
            + "mb-3 termo-evento-row";

        wrapper.innerHTML = `
            <div class="d-flex justify-content-between align-items-center mb-3">
                <strong>Termo/Consentimento</strong>

                <button
                    type="button"
                    class="btn btn-sm btn-outline-danger"
                    data-remover-termo
                >
                    <i class="fa-solid fa-trash"></i>
                </button>
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">
                        Título
                    </label>

                    <input
                        type="text"
                        class="form-control"
                        name="termos[${index}][titulo]"
                        maxlength="180"
                        required
                    >
                </div>

                <div class="col-md-6">
                    <label class="form-label">
                        Link do documento
                    </label>

                    <input
                        type="url"
                        class="form-control"
                        name="termos[${index}][url]"
                        placeholder="https://..."
                    >
                </div>

                <div class="col-12">
                    <label class="form-label">
                        Descrição apresentada ao participante
                    </label>

                    <textarea
                        class="form-control"
                        name="termos[${index}][descricao]"
                        rows="2"
                        maxlength="1000"
                    ></textarea>
                </div>

                <div class="col-12">
                    <input
                        type="hidden"
                        name="termos[${index}][obrigatorio]"
                        value="0"
                    >

                    <div class="form-check">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            name="termos[${index}][obrigatorio]"
                            value="1"
                            id="termoObrigatorio${index}"
                            checked
                        >

                        <label
                            class="form-check-label"
                            for="termoObrigatorio${index}"
                        >
                            Aceite obrigatório para inscrição
                        </label>
                    </div>
                </div>
            </div>
        `;

        container.appendChild(
            wrapper
        );
    };

    addButton.addEventListener(
        "click",
        createRow
    );

    container.addEventListener(
        "click",
        (event) => {
            const button =
                event.target.closest(
                    "[data-remover-termo]"
                );

            if (!button) {
                return;
            }

            button
                .closest(
                    ".termo-evento-row"
                )
                ?.remove();
        }
    );
})();
