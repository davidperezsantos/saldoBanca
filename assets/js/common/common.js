//const base64 = require('base-64')
import $ from 'jquery';
import i18n from '@/js/translations/index';
import base64 from 'base-64';

function decodeBase64(encodedString) {
    try {
        const decodedString = base64.decode(encodedString);
        return decodedString;
    } catch (error) {
        console.error('Error decoding Base64 string:', error);
        return null;
    }
}

function encodeBase64(string) {
    try {
        const encodedString = base64.encode(string);
        return encodedString;
    } catch (error) {
        console.error('Error encoding string to Base64:', error);
        return null;
    }
}

function ajax(path, type, parameters, success, error) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    const headers = csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {};

    $.ajax({
        url: path,
        type: type,
        data: parameters,
        headers: headers,
        success: function (response) {
            if (typeof response === 'string') {
                window.location.reload()
            }
            success(response)
        },
        error: error
    })
}


function showLoading() {
    $('#loading-overlay').css({
        "display": "flex"
    }).fadeIn('fast');
}

function hideLoading() {
    $('#loading-overlay').fadeOut('fast');
}

function formatCurrency(value, currency, locale = 'es-ES') {
    return value.toLocaleString(locale, { style: 'currency', currency: currency });
}


function getCurrencySymbol(currencyCode) {
    const currencySymbols = {
        EUR: "€",
        USD: "$",
        CUP: "$",
        MXN: "₱",
        GBP: "£",
        JPY: "¥",
        CAD: "CA$",
        AUD: "A$",
        CHF: "CHF",
        // Agrega más monedas aquí según sea necesario
    };

    // Devuelve el símbolo correspondiente o un mensaje de error si no se encuentra el código
    return currencySymbols[currencyCode] || "$";
}

function getCurrencies() {
    return [
        { id: 'EUR', name: i18n.global.t('currency.eur') },
        { id: 'USD', name: i18n.global.t('currency.usd') },
        { id: 'CUP', name: i18n.global.t('currency.cup') },
        { id: 'MXN', name: i18n.global.t('currency.mxn') },
        { id: 'GBP', name: i18n.global.t('currency.gbp') },
        { id: 'JPY', name: i18n.global.t('currency.jpy') },
        { id: 'CAD', name: i18n.global.t('currency.cad') },
        { id: 'AUD', name: i18n.global.t('currency.aud') },
        { id: 'CHF', name: i18n.global.t('currency.chf') }
    ]
}

// Permisos del usuario logueado, inyectados por admin_layout.html.twig como
// window.__PERMISSIONS__ (lista plana "modulo:accion" / "modulo.submodulo:accion" — mismo
// formato que valida App\Security\PermissionVoter en el backend). Sirve para ocultar en el
// SPA botones de acciones que el backend igual va a rechazar con 403.
function can(permission) {
    return (window.__PERMISSIONS__ || []).includes(permission);
}

function getTypeDriver() {
    return [
        {
            id: 'mysql',
            name: 'MySQL'
        },
        {
            id: 'postgresql',
            name: 'Postgres'
        },
        {
            id: 'sqlite',
            name: 'Sqlite'
        }
    ]
}

export default {
    ajax,
    showLoading,
    hideLoading,
    getCurrencySymbol,
    getTypeDriver,
    formatCurrency,
    getCurrencies,
    decodeBase64,
    encodeBase64,
    can
}
