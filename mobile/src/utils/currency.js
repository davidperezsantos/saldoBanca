// Mismo mapa que assets/js/common/common.js (getCurrencySymbol) del panel web, para que el
// símbolo de cada moneda se vea igual en los dos lados.
const SYMBOLS = {
    EUR: '€',
    USD: '$',
    CUP: '$',
    MXN: '₱',
    GBP: '£',
    JPY: '¥',
    CAD: 'CA$',
    AUD: 'A$',
    CHF: 'CHF',
};

export function currencySymbol(code) {
    return SYMBOLS[code] ?? code;
}

export function formatMoney(amount, code) {
    if (amount === null || amount === undefined) return '';
    return `${amount} ${currencySymbol(code)}`;
}
