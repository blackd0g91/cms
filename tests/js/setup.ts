import { Window } from 'happy-dom';

// Newer Node versions have a localStorage of their own, which is undefined
// unless Node is given a file for it, and it hides the simulated browser's.
// Each test file gets a working one, emptied between tests by the tests.
if (typeof globalThis.localStorage?.getItem !== 'function') {
    const storage = new Window().localStorage;

    Object.defineProperty(globalThis, 'localStorage', {
        value: storage,
        configurable: true,
    });
}
