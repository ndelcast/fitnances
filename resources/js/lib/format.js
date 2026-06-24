const eur = new Intl.NumberFormat('es-ES', {
    style: 'currency',
    currency: 'EUR',
    minimumFractionDigits: 2,
});

export function formatEuros(value) {
    return eur.format(value ?? 0);
}

const dateFmt = new Intl.DateTimeFormat('es-ES', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
});

export function formatDate(isoDate) {
    if (!isoDate) return '';
    return dateFmt.format(new Date(isoDate));
}

const shortDateFmt = new Intl.DateTimeFormat('es-ES', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
});

export function formatShortDate(isoDate) {
    if (!isoDate) return '';
    return shortDateFmt.format(new Date(isoDate));
}

const percentFmt = new Intl.NumberFormat('es-ES', {
    style: 'percent',
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
});

export function formatPercent(value) {
    return percentFmt.format((value ?? 0) / 100);
}
