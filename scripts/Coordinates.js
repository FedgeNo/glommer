/**
 * Client twin of Coordinates.php: a latitude/longitude pair parsed from
 * untrusted input, with the same both-or-neither rule. A pair where one half
 * failed to parse would put a point on the equator rather than nowhere, which
 * is worse than having no point at all.
 */
export class Coordinates {
    constructor(latitude, longitude) {
        this.latitude = latitude;
        this.longitude = longitude;
    }

    /** The pair, or null if either side is missing or out of range. */
    static parse(latitude, longitude) {
        if (latitude === undefined || latitude === null || latitude === ''
            || longitude === undefined || longitude === null || longitude === '') {
            return null;
        }

        latitude = Number(latitude);
        longitude = Number(longitude);

        if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
            return null;
        }

        if (latitude < -90 || latitude > 90 || longitude < -180 || longitude > 180) {
            return null;
        }

        return new Coordinates(latitude, longitude);
    }
}
