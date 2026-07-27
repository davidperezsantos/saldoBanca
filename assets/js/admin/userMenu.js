/**
 * Componente Alpine para el bloque de usuario del sidebar (admin_layout.html.twig) — menú
 * desplegable + modal de "editar nombre". Vive acá (no inline en el Twig) para no mezclar
 * lógica de fetch/CSRF con el markup.
 */
export function userMenu(initialName, updateUrl) {
    return {
        menuOpen: false,
        editOpen: false,
        saving: false,
        errorMsg: '',
        nameInput: initialName,

        openEdit() {
            this.menuOpen = false;
            this.errorMsg = '';
            this.nameInput = initialName;
            this.editOpen = true;
        },

        save() {
            this.saving = true;
            this.errorMsg = '';

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

            fetch(updateUrl, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ name: this.nameInput }),
            })
                .then((response) => response.json())
                .then((data) => {
                    this.saving = false;
                    if (data.success) {
                        window.location.reload();
                    } else {
                        this.errorMsg = data.message || 'Error';
                    }
                })
                .catch(() => {
                    this.saving = false;
                    this.errorMsg = 'Error de red';
                });
        },
    };
}
