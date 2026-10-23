import type { HeaderTimestamp } from '../../../types/headers';

export function parseHeaderTimestamp(value: string): HeaderTimestamp {
    const unknown: HeaderTimestamp = { raw: value, iso: null, epochMs: null };
    const input = value.replace(/\([^()]*\)/g, '').trim();
    const match = /^(?:(?:Mon|Tue|Wed|Thu|Fri|Sat|Sun),?\s+)?(\d{1,2})\s+([a-z]{3})\s+(\d{4})\s+(\d{2}):(\d{2})(?::(\d{2}))?\s+([+-]\d{4}|UT|UTC|GMT)$/i.exec(input);
    if (!match) { return unknown; }
    const [, dayText, monthText, yearText, hourText, minuteText, secondText, zone] = match;
    const month = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'].indexOf(monthText.toLowerCase());
    const [day, year, hour, minute, second] = [Number(dayText), Number(yearText), Number(hourText), Number(minuteText), Number(secondText ?? 0)];
    if (month < 0 || year < 1900 || day < 1 || hour > 23 || minute > 59 || second > 59) { return unknown; }
    const local = new Date(Date.UTC(year, month, day, hour, minute, second));
    if (local.getUTCDate() !== day || local.getUTCMonth() !== month) { return unknown; }
    let offsetMinutes = 0;
    if (/^[+-]/.test(zone)) {
        const offsetHour = Number(zone.slice(1, 3));
        const offsetMinute = Number(zone.slice(3, 5));
        if (offsetHour > 23 || offsetMinute > 59) { return unknown; }
        offsetMinutes = (offsetHour * 60 + offsetMinute) * (zone[0] === '-' ? -1 : 1);
    }
    const epochMs = local.getTime() - offsetMinutes * 60000;
    return { raw: value, iso: new Date(epochMs).toISOString(), epochMs };
}
