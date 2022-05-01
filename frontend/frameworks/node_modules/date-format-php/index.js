// TimeZone formatter
/**
 * TimeZone formatter
 * @param {Date} d
 * @param {string} separator
 * @returns {string}
 */
const tz = (d, separator) => {
    const tzOffset = FORMATS.Z(d);
    const tzOffsetAbs = Math.abs(tzOffset);
    const sign = (tzOffset >= 0) ? "+" : "-";
    const hours = zeropad(Math.floor(tzOffsetAbs / 3600));
    const minutes = zeropad(Math.floor(tzOffsetAbs % 3600 / 60));
    return `${sign}${hours}${separator}${minutes}`;
};
/**
 * @param {number} n
 * @returns {string}
 */
const zeropad = (n) => (n < 10) ? (`0${n}`) : `${n}`;
/**
 * the `d` param in every formatter is of @type {Date}
 *
 * For the formats reference, @see https://secure.php.net/manual/en/function.date.php
 *
 * @type {{Y: function(*): number, m: function(*): (*|string), d: function(*): (*|string), H: function(*): (*|string), i: function(*): (*|string), s: function(*): (*|string), D: function(*): string, l: function(*): string, N: function(*): number, j: function(*): number, S: FORMATS.S, w: function(*): number, z: function(*=): number, L: FORMATS.L, W: function(*): (*|string), F: function(*): string, M: function(*): string, n: function(*): number, t: function(*=): number, y: function(*): (*|string), a: function(*=): string, A: function(*=): string, g: function(*): number, G: function(*): number, h: function(*=): (*|string), Z: function(*): number, c: function(*=): *, r: function(*=): *, U: function(*): number}}
 */
const FORMATS = {
    Y: d => d.getFullYear(),
    m: d => zeropad(d.getMonth() + 1),
    d: d => zeropad(d.getDate()),
    H: d => zeropad(d.getHours()),
    i: d => zeropad(d.getMinutes()),
    s: d => zeropad(d.getSeconds()),
    D: d => (["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"])[d.getDay() - 1],
    l: d => (["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"])[d.getDay() - 1],
    N: d => d.getDay(),
    j: d => d.getDate(),
    S: d => {// to form correct English number with suffix like 2nd, 3rd etc
        const j = d.getDate();
        if (j >= 10 && j <= 20) return 'th';
        switch (j % 10) {
            case 1:
                return 'st';
            case 2:
                return 'nd';
            case 3:
                return 'rd';
            default:
                return 'th';
        }
    },
    w: d => d.getDay() - 1,
    z: d => {// number of day within the year, starting from 0
        const year = FORMATS.L(d) ? LEAP_YEAR : YEAR;
        return year.slice(0, d.getMonth()).reduce((a, b) => a + b) + d.getDate() - 1;
    },
    L: d => {// leap year?
        const Y = d.getFullYear();
        if (!(Y%400)) return 1;
        if (!(Y%100)) return 0;
        return (Y%4) ? 0 : 1;
    },
    W: d => {// https://stackoverflow.com/questions/6117814/get-week-of-year-in-javascript-like-in-php/6117889#6117889
        // Copy date so don't modify original
        let dc = new Date(Date.UTC(d.getFullYear(), d.getMonth(), d.getDate()));
        // Set to nearest Thursday: current date + 4 - current day number
        // Make Sunday's day number 7
        dc.setUTCDate(dc.getUTCDate() + 4 - (dc.getUTCDay()||7));
        // Get first day of year
        let yearStart = new Date(Date.UTC(dc.getUTCFullYear(), 0, 1));
        // Calculate full weeks to nearest Thursday
        let weekNo = Math.ceil(( ( (dc - yearStart) / 86400000) + 1)/7);
        // Return array of year and week number
        return zeropad(weekNo);
    },
    F: d => MONTHS[d.getMonth()],
    M: d => MONTHS_SHORT[d.getMonth()],
    n: d => d.getMonth() + 1,
    t: d => (FORMATS.L(d) ? LEAP_YEAR : YEAR)[d.getMonth()],
    y: d => zeropad(d.getFullYear() % 100),
    a: d => FORMATS.H(d) >= 12 ? 'pm' : 'am',
    A: d => FORMATS.a(d).toUpperCase(),
    g: d => (d.getHours() % 12) || 12,
    G: d => d.getHours(),
    h: d => zeropad(FORMATS.g(d)),
    O: d => tz(d, ""),
    P: d => tz(d, ":"),
    Z: d => d.getTimezoneOffset() * -60,
    c: d => d.format("Y-m-dTH:i:s") + tz(d, ":"),
    r: d => d.format("D, d M Y H:i:s ") + tz(d, ""),
    U: d => Math.floor(d.getTime() / 1000)
};
// Number of days in months: regular year & leap year
const YEAR = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
const LEAP_YEAR = [31, 29, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];

const MONTHS = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
const MONTHS_SHORT = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];

// this is where the magic happens.
// we make a single regexp like this: /[YmdHisO......]/
const FORMATREGEXP = new RegExp(`[${Object.keys(FORMATS).join("")}]`, 'g');
// ... and in format() we simply apply appropriate callback from FORMATS
Date.prototype.format = function(format) {
    return format.replace(FORMATREGEXP, (m) => FORMATS[m].call(this, this));
};

module.exports = true;