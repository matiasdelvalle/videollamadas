import dayjs from "dayjs";

export function DateFormat(element, format) {
    if (!element) {
        return null;
    }
    return dayjs(element).format(format);
}

export function DateFormatUnix(element, format) {
    if (!element) {
        return null;
    }
    return dayjs.unix(element).format(format);
}