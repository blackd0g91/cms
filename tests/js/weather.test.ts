import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import {
    clockTime,
    describeWeather,
    fetchWeather,
    preferredUnit,
    roundCoordinate,
    startWeather,
    temperatureIn,
} from '@/weather';

// What Open-Meteo answers, trimmed to the fields asked for.
const forecast = {
    current: {
        temperature_2m: 17.3,
        apparent_temperature: 18.8,
        weather_code: 61,
        is_day: 1,
        wind_speed_10m: 5.9,
    },
    daily: {
        temperature_2m_max: [25.5],
        temperature_2m_min: [14.2],
        precipitation_probability_max: [80],
        sunrise: ['2026-10-11T05:37'],
        sunset: ['2026-10-11T18:09'],
    },
};

const respond = (body: unknown, ok = true) =>
    Promise.resolve({
        ok,
        status: ok ? 200 : 500,
        json: () => Promise.resolve(body),
    });

// The card as site/partials/weather.blade.php writes it, without the styling.
const cardHtml = `
<section data-weather hidden>
    <div data-weather-state="empty"><button data-weather-locate>Show my weather</button></div>
    <p data-weather-state="loading" hidden><span data-weather-loading></span></p>
    <div data-weather-state="error" hidden><p data-weather-error></p><button data-weather-retry>Try again</button></div>
    <div data-weather-state="ready" hidden>
        <span data-weather-icon></span><button data-weather-temperature></button><span data-weather-label></span>
        <span data-weather-place></span><span data-weather-feels></span>
        <dd data-weather-range></dd><dd data-weather-rain></dd><dd data-weather-wind></dd><dd data-weather-sun></dd>
        <button data-weather-locate>Update</button><button data-weather-forget>Forget</button>
    </div>
</section>`;

describe('helpers', () => {
    it('describes the weather codes, by day and by night', () => {
        expect(describeWeather(0, true)).toEqual({
            label: 'Clear',
            icon: '☀️',
        });
        expect(describeWeather(0, false)).toEqual({
            label: 'Clear',
            icon: '🌙',
        });
        expect(describeWeather(95, true).label).toBe('Thunderstorm');
        expect(describeWeather(42, true).label).toBe('Unknown');
    });

    it('rounds locations to about a kilometer', () => {
        expect(roundCoordinate(-23.550519)).toBe(-23.55);
        expect(roundCoordinate(46.633309)).toBe(46.63);
    });

    it('writes temperatures in either unit, and local clock times', () => {
        expect(temperatureIn(17.3, 'c')).toBe('17°');
        expect(temperatureIn(17.3, 'f')).toBe('63°');
        expect(clockTime('2026-10-11T05:37')).toBe('05:37');
    });

    it('takes the unit chosen on the site, or the usual one', () => {
        expect(preferredUnit()).toBe(
            navigator.language === 'en-US' ? 'f' : 'c',
        );

        localStorage.setItem('site.temperature', 'f');
        expect(preferredUnit()).toBe('f');

        document.documentElement.dataset.temperature = 'c';
        expect(preferredUnit()).toBe('c');
    });
});

describe('fetchWeather', () => {
    it('asks Open-Meteo for the place and reads its answer', async () => {
        const fetch = vi.fn((_url: string) => respond(forecast));
        vi.stubGlobal('fetch', fetch);

        const weather = await fetchWeather({
            latitude: -23.55,
            longitude: -46.63,
        });

        const url = new URL(fetch.mock.calls[0][0]);
        expect(url.host).toBe('api.open-meteo.com');
        expect(url.searchParams.get('latitude')).toBe('-23.55');
        expect(url.searchParams.get('timezone')).toBe('auto');
        expect(weather).toMatchObject({
            temperature: 17.3,
            code: 61,
            isDay: true,
            high: 25.5,
            low: 14.2,
            rainChance: 80,
        });
    });

    it('fails when Open-Meteo does', async () => {
        vi.stubGlobal('fetch', () => respond({}, false));

        await expect(
            fetchWeather({ latitude: 0, longitude: 0 }),
        ).rejects.toThrow();
    });
});

describe('startWeather', () => {
    let card: HTMLElement;
    const getCurrentPosition = vi.fn();
    const visible = () =>
        card.querySelector<HTMLElement>('[data-weather-state]:not([hidden])')!
            .dataset.weatherState;
    const text = (name: string) =>
        card.querySelector(`[data-weather-${name}]`)!.textContent;
    const settle = () =>
        vi.waitFor(() => expect(visible()).not.toBe('loading'));

    beforeEach(() => {
        document.body.innerHTML = cardHtml;
        card = document.querySelector('[data-weather]')!;
        document.documentElement.dataset.temperature = 'c';
        vi.stubGlobal('navigator', {
            language: 'en-GB',
            geolocation: { getCurrentPosition },
        });
        vi.stubGlobal('fetch', (url: string) =>
            respond(
                url.includes('open-meteo') ? forecast : { city: 'São Paulo' },
            ),
        );
    });

    afterEach(() => {
        document.body.innerHTML = '';
        delete document.documentElement.dataset.temperature;
        localStorage.clear();
        getCurrentPosition.mockReset();
    });

    it('waits to be asked before looking up the location', () => {
        startWeather(card);

        expect(card.hidden).toBe(false);
        expect(visible()).toBe('empty');
        expect(getCurrentPosition).not.toHaveBeenCalled();
    });

    it('shows the weather where the visitor is, and remembers the rounded place', async () => {
        getCurrentPosition.mockImplementation((resolve) =>
            resolve({
                coords: { latitude: -23.550519, longitude: -46.633309 },
            }),
        );
        startWeather(card);

        card.querySelector<HTMLButtonElement>('[data-weather-locate]')!.click();
        await settle();

        expect(visible()).toBe('ready');
        expect(text('temperature')).toBe('17°');
        expect(text('label')).toBe('Rain');
        expect(text('place')).toBe('São Paulo');
        expect(text('range')).toBe('26° / 14°');
        expect(text('rain')).toBe('80%');
        expect(text('wind')).toBe('6 km/h');
        expect(text('sun')).toBe('05:37 – 18:09');
        expect(JSON.parse(localStorage.getItem('site.weather.place')!)).toEqual(
            {
                latitude: -23.55,
                longitude: -46.63,
                name: 'São Paulo',
            },
        );
    });

    it('shows the weather straight away on later visits', async () => {
        localStorage.setItem(
            'site.weather.place',
            JSON.stringify({ latitude: 1, longitude: 2, name: 'Here' }),
        );

        startWeather(card);
        await settle();

        expect(visible()).toBe('ready');
        expect(text('place')).toBe('Here');
        expect(getCurrentPosition).not.toHaveBeenCalled();
    });

    it('swaps °C and °F when the temperature is tapped, for the whole site', async () => {
        localStorage.setItem(
            'site.weather.place',
            JSON.stringify({ latitude: 1, longitude: 2, name: 'Here' }),
        );
        startWeather(card);
        await settle();

        card.querySelector<HTMLButtonElement>(
            '[data-weather-temperature]',
        )!.click();

        expect(text('temperature')).toBe('63°');
        expect(text('wind')).toBe('4 mph');
        expect(document.documentElement.dataset.temperature).toBe('f');
        expect(localStorage.getItem('site.temperature')).toBe('f');
    });

    it('explains when location access is blocked', async () => {
        getCurrentPosition.mockImplementation((_, reject) =>
            reject({ code: 1 }),
        );
        startWeather(card);

        card.querySelector<HTMLButtonElement>('[data-weather-locate]')!.click();
        await settle();

        expect(visible()).toBe('error');
        expect(text('error')).toMatch(/blocked/);
        expect(localStorage.getItem('site.weather.place')).toBeNull();
    });

    it('forgets the place', async () => {
        localStorage.setItem(
            'site.weather.place',
            JSON.stringify({ latitude: 1, longitude: 2, name: 'Here' }),
        );
        startWeather(card);
        await settle();

        card.querySelector<HTMLButtonElement>('[data-weather-forget]')!.click();

        expect(visible()).toBe('empty');
        expect(localStorage.getItem('site.weather.place')).toBeNull();
        expect(localStorage.getItem('site.weather.cache')).toBeNull();
    });

    it('stays hidden in browsers without location', () => {
        vi.stubGlobal('navigator', { language: 'en-GB' });

        startWeather(card);

        expect(card.hidden).toBe(true);
    });
});
