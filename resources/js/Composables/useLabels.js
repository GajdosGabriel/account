/**
 * Farby ručných štítkov kontaktov (App\Models\MailingLabel::COLORS).
 * Triedy sú vypísané celé, aby ich Tailwind našiel.
 */
const palette = {
    indigo: { chip: 'bg-indigo-50 text-indigo-700 ring-1 ring-inset ring-indigo-600/20', dot: 'bg-indigo-500' },
    sky: { chip: 'bg-sky-50 text-sky-700 ring-1 ring-inset ring-sky-600/20', dot: 'bg-sky-500' },
    emerald: { chip: 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20', dot: 'bg-emerald-500' },
    amber: { chip: 'bg-amber-50 text-amber-800 ring-1 ring-inset ring-amber-600/20', dot: 'bg-amber-500' },
    orange: { chip: 'bg-orange-50 text-orange-800 ring-1 ring-inset ring-orange-600/20', dot: 'bg-orange-500' },
    rose: { chip: 'bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-600/20', dot: 'bg-rose-500' },
    violet: { chip: 'bg-violet-50 text-violet-700 ring-1 ring-inset ring-violet-600/20', dot: 'bg-violet-500' },
    slate: { chip: 'bg-slate-100 text-slate-700 ring-1 ring-inset ring-slate-500/20', dot: 'bg-slate-400' },
};

export const labelClass = (color, part = 'chip') => (palette[color] ?? palette.indigo)[part];
