const eur = new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'EUR',
    minimumFractionDigits: 2,
});

export function formatEuros(value) {
    return eur.format(value ?? 0);
}

const dateFmt = new Intl.DateTimeFormat('fr-FR', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
});

export function formatDate(isoDate) {
    if (!isoDate) return '';
    return dateFmt.format(new Date(isoDate));
}
