/**
 * Componente Alpine para el bloque de usuario del sidebar (admin_layout.html.twig) — menú
 * desplegable + modales de "editar nombre" y "cambiar contraseña". Vive acá (no inline en el
 * Twig) para no mezclar lógica de fetch/CSRF con el markup.
 */
function putJson(url, body) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    return fetch(url, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify(body),
    }).then((response) => response.json());
}

export function userMenu(initialName, updateNameUrl, changePasswordUrl) {
    return {
        menuOpen: false,

        editOpen: false,
        saving: false,
        errorMsg: '',
        nameInput: initialName,

        passwordOpen: false,
        pwSaving: false,
        pwErrorMsg: '',
        currentPassword: '',
        newPassword: '',
        newPasswordConfirm: '',

        openEdit() {
            this.menuOpen = false;
            this.errorMsg = '';
            this.nameInput = initialName;
            this.editOpen = true;
        },

        save() {
            this.saving = true;
            this.errorMsg = '';

            putJson(updateNameUrl, { name: this.nameInput })
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

        openChangePassword() {
            this.menuOpen = false;
            this.pwErrorMsg = '';
            this.currentPassword = '';
            this.newPassword = '';
            this.newPasswordConfirm = '';
            this.passwordOpen = true;
        },

        savePassword() {
            if (this.newPassword !== this.newPasswordConfirm) {
                this.pwErrorMsg = 'Las contraseñas nuevas no coinciden';
                return;
            }

            this.pwSaving = true;
            this.pwErrorMsg = '';

            putJson(changePasswordUrl, {
                currentPassword: this.currentPassword,
                newPassword: this.newPassword,
            })
                .then((data) => {
                    this.pwSaving = false;
                    if (data.success) {
                        this.passwordOpen = false;
                    } else {
                        this.pwErrorMsg = data.message || 'Error';
                    }
                })
                .catch(() => {
                    this.pwSaving = false;
                    this.pwErrorMsg = 'Error de red';
                });
        },
    };
}
