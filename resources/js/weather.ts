/**
 * The weather card on the home page (see site/partials/weather.blade.php):
 * small, with the weather now, and opening a dialog with the details and
 * the week ahead. Nothing happens until the visitor asks for it: their
 * location is then looked up once, rounded to about a kilometer and kept in
 * this browser, so later visits show the weather straight away. It is only
 * ever sent to the weather and place name services, from the visitor's own
 * browser.
 *
 * Weather: Open-Meteo (open-meteo.com). Place names: BigDataCloud's free
 * reverse geocoding for browser locations (bigdatacloud.com).
 */

export type Place = {
    latitude: number;
    longitude: number;
    name: string | null;
};

export type Day = {
    // "2026-10-11", in the place's time zone.
    date: string;
    code: number;
    high: number;
    low: number;
    rainChance: number | null;
};

export type Weather = {
    temperature: number;
    feelsLike: number;
    code: number;
    isDay: boolean;
    wind: number;
    sunrise: string;
    sunset: string;
    // Today first, then the six days after it.
    days: Day[];
};

export type Unit = 'c' | 'f';

const PLACE_KEY = 'site.weather.place';
// Versioned, as cached weather from before the forecast has no days.
const CACHE_KEY = 'site.weather.cache.v2';
// Shared with the {{ temp }} widgets (see widgets/temperature.ts).
const UNIT_KEY = 'site.temperature';
// Open-Meteo updates every 15 minutes.
const FRESH_FOR = 15 * 60_000;

// What the WMO weather codes Open-Meteo sends mean, with an icon for day
// and one for night.
const CONDITIONS: [number[], string, string, string][] = [
    [[0], 'Clear', '☀️', '🌙'],
    [[1], 'Mostly clear', '🌤️', '🌙'],
    [[2], 'Partly cloudy', '⛅', '☁️'],
    [[3], 'Cloudy', '☁️', '☁️'],
    [[45, 48], 'Fog', '🌫️', '🌫️'],
    [[51, 53, 55, 56, 57], 'Drizzle', '🌦️', '🌧️'],
    [[61, 63, 66, 80, 81], 'Rain', '🌧️', '🌧️'],
    [[65, 67, 82], 'Heavy rain', '🌧️', '🌧️'],
    [[71, 73, 75, 77, 85, 86], 'Snow', '🌨️', '🌨️'],
    [[95, 96, 99], 'Thunderstorm', '⛈️', '⛈️'],
];

export const describeWeather = (
    code: number,
    isDay = true,
): { label: string; icon: string } => {
    const condition = CONDITIONS.find(([codes]) => codes.includes(code));

    return condition
        ? { label: condition[1], icon: isDay ? condition[2] : condition[3] }
        : { label: 'Unknown', icon: '🌡️' };
};

/** About a kilometer: plenty for the weather, and no more than needed. */
export const roundCoordinate = (value: number) => Math.round(value * 100) / 100;

export const temperatureIn = (celsius: number, unit: Unit) =>
    `${Math.round(unit === 'f' ? (celsius * 9) / 5 + 32 : celsius)}°`;

/** "06:12" from the local times Open-Meteo sends, like "2026-10-11T06:12". */
export const clockTime = (iso: string) => iso.slice(11, 16);

/** "Today", then short weekday names: "Tue", "Wed"… */
export const dayName = (date: string, index: number) => {
    if (index === 0) {
        return 'Today';
    }

    const [year, month, day] = date.split('-').map(Number);

    return new Date(year, month - 1, day).toLocaleDateString(undefined, {
        weekday: 'short',
    });
};

export async function fetchWeather(
    place: Pick<Place, 'latitude' | 'longitude'>,
): Promise<Weather> {
    const query = new URLSearchParams({
        latitude: String(place.latitude),
        longitude: String(place.longitude),
        current:
            'temperature_2m,apparent_temperature,weather_code,is_day,wind_speed_10m',
        daily: 'weather_code,temperature_2m_max,temperature_2m_min,precipitation_probability_max,sunrise,sunset',
        timezone: 'auto',
        forecast_days: '7',
    });
    const response = await fetch(
        `https://api.open-meteo.com/v1/forecast?${query}`,
    );

    if (!response.ok) {
        throw new Error(`Open-Meteo answered ${response.status}`);
    }

    const { current, daily } = await response.json();

    return {
        temperature: current.temperature_2m,
        feelsLike: current.apparent_temperature,
        code: current.weather_code,
        isDay: current.is_day === 1,
        wind: current.wind_speed_10m,
        sunrise: daily.sunrise[0],
        sunset: daily.sunset[0],
        days: (daily.time as string[]).map((date, i) => ({
            date,
            code: daily.weather_code[i],
            high: daily.temperature_2m_max[i],
            low: daily.temperature_2m_min[i],
            rainChance: daily.precipitation_probability_max[i] ?? null,
        })),
    };
}

/** The town or city at a place, or null when there is no name for it. */
export async function fetchPlaceName(
    latitude: number,
    longitude: number,
): Promise<string | null> {
    try {
        const query = new URLSearchParams({
            latitude: String(latitude),
            longitude: String(longitude),
            localityLanguage: navigator.language.split('-')[0] || 'en',
        });
        const response = await fetch(
            `https://api.bigdatacloud.net/data/reverse-geocode-client?${query}`,
        );
        const data = await response.json();

        // The city can be a whole region ("Região Metropolitana de São
        // Paulo") and the locality a district ("Cidade de Westminster" for
        // London): the shorter of the two reads best.
        const names = [data.city, data.locality].filter(
            (name): name is string => typeof name === 'string' && name !== '',
        );

        return names.length
            ? names.reduce((a, b) => (b.length < a.length ? b : a))
            : data.principalSubdivision || null;
    } catch {
        // The weather still shows, just without the name.
        return null;
    }
}

const load = <T>(key: string): T | null => {
    try {
        const raw = localStorage.getItem(key);

        return raw ? (JSON.parse(raw) as T) : null;
    } catch {
        return null;
    }
};

const store = (key: string, value: unknown) => {
    try {
        if (value === null) {
            localStorage.removeItem(key);
        } else {
            localStorage.setItem(key, JSON.stringify(value));
        }
    } catch {
        // Not remembered (private windows): it works for this visit.
    }
};

/** The visitor's unit: chosen on a temperature, or the usual one where they are. */
export const preferredUnit = (): Unit => {
    let chosen = document.documentElement.dataset.temperature;

    try {
        // Stored as a plain "c" or "f", like the {{ temp }} widgets do.
        chosen ??= localStorage.getItem(UNIT_KEY) ?? undefined;
    } catch {
        // Not remembered: the usual unit.
    }

    if (chosen === 'c' || chosen === 'f') {
        return chosen;
    }

    return navigator.language === 'en-US' ? 'f' : 'c';
};

const locate = () =>
    new Promise<GeolocationPosition>((resolve, reject) =>
        navigator.geolocation.getCurrentPosition(resolve, reject, {
            enableHighAccuracy: false,
            timeout: 15_000,
            maximumAge: 30 * 60_000,
        }),
    );

type State = 'empty' | 'loading' | 'error' | 'ready';

export function startWeather(card: HTMLElement) {
    if (!('geolocation' in navigator)) {
        return;
    }

    const part = <T extends HTMLElement = HTMLElement>(name: string) =>
        card.querySelector<T>(`[data-weather-${name}]`)!;
    const all = (name: string) =>
        card.querySelectorAll<HTMLElement>(`[data-weather-${name}]`);
    const dialog = part<HTMLDialogElement>('dialog');
    const dayTemplate = part<HTMLTemplateElement>('day-template');
    let weather: Weather | null = null;

    const show = (state: State, message = '') => {
        all('state').forEach(
            (element) =>
                (element.hidden = element.dataset.weatherState !== state),
        );
        card.setAttribute('aria-busy', String(state === 'loading'));

        if (state === 'loading') {
            part('loading').textContent = message;
        } else if (state === 'error') {
            part('error').textContent = message;
        }

        if (state !== 'ready' && dialog.open) {
            dialog.close();
        }
    };

    // Fills every element marked with data-weather-{name}: the card and the
    // dialog share some, like the temperature and the place.
    const fill = (name: string, text: string) =>
        all(name).forEach((element) => (element.textContent = text));

    const render = () => {
        if (!weather) {
            return;
        }

        const place = load<Place>(PLACE_KEY);
        const unit = preferredUnit();
        const { label, icon } = describeWeather(weather.code, weather.isDay);
        const today = weather.days[0];

        fill('icon', icon);
        fill('temperature', temperatureIn(weather.temperature, unit));
        fill('label', label);
        fill('place', place?.name ?? 'Your location');
        fill('feels', temperatureIn(weather.feelsLike, unit));
        fill(
            'range',
            today
                ? `${temperatureIn(today.high, unit)} / ${temperatureIn(today.low, unit)}`
                : '–',
        );
        fill('rain', today?.rainChance == null ? '–' : `${today.rainChance}%`);
        fill(
            'wind',
            unit === 'f'
                ? `${Math.round(weather.wind / 1.609)} mph`
                : `${Math.round(weather.wind)} km/h`,
        );
        fill(
            'sun',
            `${clockTime(weather.sunrise)} – ${clockTime(weather.sunset)}`,
        );
        part('unit').textContent = `Show in °${unit === 'c' ? 'F' : 'C'}`;

        // The week: each day's range drawn on a bar spanning the week's
        // lowest to highest, so warmer and colder days stand out.
        const lowest = Math.min(...weather.days.map((day) => day.low));
        const highest = Math.max(...weather.days.map((day) => day.high));
        const span = Math.max(1, highest - lowest);

        part('days').replaceChildren(
            ...weather.days.map((day, index) => {
                const row = dayTemplate.content.firstElementChild!.cloneNode(
                    true,
                ) as HTMLElement;
                const condition = describeWeather(day.code);
                const cell = (name: string) =>
                    row.querySelector<HTMLElement>(`[data-day-${name}]`)!;

                cell('name').textContent = dayName(day.date, index);
                cell('icon').textContent = condition.icon;
                cell('icon').title = condition.label;
                cell('label').textContent = condition.label;
                cell('rain').textContent = day.rainChance
                    ? `${day.rainChance}%`
                    : '';
                cell('low').textContent = temperatureIn(day.low, unit);
                cell('high').textContent = temperatureIn(day.high, unit);
                cell('bar').style.left =
                    `${((day.low - lowest) / span) * 100}%`;
                cell('bar').style.right =
                    `${((highest - day.high) / span) * 100}%`;

                return row;
            }),
        );

        show('ready');
    };

    const refresh = async (place: Place) => {
        const cached = load<{ at: number; place: string; weather: Weather }>(
            CACHE_KEY,
        );
        const key = `${place.latitude},${place.longitude}`;

        if (
            cached &&
            cached.place === key &&
            Date.now() - cached.at < FRESH_FOR
        ) {
            weather = cached.weather;
            render();

            return;
        }

        show('loading', 'Checking the weather…');

        try {
            weather = await fetchWeather(place);
            store(CACHE_KEY, { at: Date.now(), place: key, weather });
            render();
        } catch {
            show('error', "Couldn't get the weather.");
        }
    };

    const findPlace = async () => {
        show('loading', 'Finding where you are…');

        let position: GeolocationPosition;

        try {
            position = await locate();
        } catch (error) {
            show(
                'error',
                (error as GeolocationPositionError).code === 1
                    ? 'Location is blocked for this site.'
                    : "Couldn't find where you are.",
            );

            return;
        }

        const latitude = roundCoordinate(position.coords.latitude);
        const longitude = roundCoordinate(position.coords.longitude);
        const place: Place = {
            latitude,
            longitude,
            name: await fetchPlaceName(latitude, longitude),
        };

        store(PLACE_KEY, place);
        await refresh(place);
    };

    all('locate').forEach((button) =>
        button.addEventListener('click', () => void findPlace()),
    );

    part('retry').addEventListener('click', () => {
        const place = load<Place>(PLACE_KEY);
        void (place ? refresh(place) : findPlace());
    });

    part('open').addEventListener('click', () => dialog.showModal());
    part('close').addEventListener('click', () => dialog.close());

    // A click on the backdrop, outside the dialog's box, closes it too.
    dialog.addEventListener('click', (event) => {
        const box = dialog.getBoundingClientRect();
        const outside =
            event.clientX < box.left ||
            event.clientX > box.right ||
            event.clientY < box.top ||
            event.clientY > box.bottom;

        if (event.target === dialog && outside) {
            dialog.close();
        }
    });

    part('forget').addEventListener('click', () => {
        store(PLACE_KEY, null);
        store(CACHE_KEY, null);
        weather = null;
        show('empty');
    });

    // Swaps °C and °F, here and in the {{ temp }} widgets.
    part('unit').addEventListener('click', () => {
        const next = preferredUnit() === 'c' ? 'f' : 'c';

        document.documentElement.dataset.temperature = next;

        try {
            localStorage.setItem(UNIT_KEY, next);
        } catch {
            // Swapped, not remembered.
        }

        render();
    });

    const saved = load<Place>(PLACE_KEY);

    card.hidden = false;

    if (saved) {
        void refresh(saved);
    } else {
        show('empty');
    }
}
