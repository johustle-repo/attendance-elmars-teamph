import { useCallback, useMemo, useSyncExternalStore } from 'react';

export type ResolvedAppearance = 'light' | 'dark';
export type Appearance = ResolvedAppearance | 'system';

export type UseAppearanceReturn = {
    readonly appearance: Appearance;
    readonly resolvedAppearance: ResolvedAppearance;
    readonly updateAppearance: (mode: Appearance) => void;
};

const listeners = new Set<() => void>();
let currentAppearance: Appearance = 'system';
let hasInitialized = false;
let systemThemeMediaQuery: MediaQueryList | null = null;

const APPEARANCE_VALUES = ['light', 'dark', 'system'] as const;

const isAppearance = (value: unknown): value is Appearance => {
    return APPEARANCE_VALUES.includes(value as Appearance);
};

const prefersDark = (): boolean => {
    if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') {
        return false;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches;
};

const setCookie = (name: string, value: string, days = 365): void => {
    if (typeof document === 'undefined') return;
    const maxAge = days * 24 * 60 * 60;
    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

const getCookieAppearance = (): Appearance | null => {
    if (typeof document === 'undefined') return null;

    const matchedAppearance = document.cookie.match(
        /(?:^|;\s*)appearance=([^;]+)/,
    )?.[1];

    if (!matchedAppearance) {
        return null;
    }

    const appearance = decodeURIComponent(matchedAppearance);

    return isAppearance(appearance) ? appearance : null;
};

const getStoredAppearance = (): Appearance => {
    if (typeof window === 'undefined') return 'system';

    let storedAppearance: string | null = null;

    try {
        storedAppearance = window.localStorage.getItem('appearance');
    } catch {
        storedAppearance = null;
    }

    if (isAppearance(storedAppearance)) {
        return storedAppearance;
    }

    return getCookieAppearance() ?? 'system';
};

const isDarkMode = (appearance: Appearance): boolean => {
    return appearance === 'dark' || (appearance === 'system' && prefersDark());
};

const applyTheme = (appearance: Appearance): void => {
    if (typeof document === 'undefined') return;

    const isDark = isDarkMode(appearance);

    document.documentElement.classList.toggle('dark', isDark);
    document.documentElement.dataset.appearance = appearance;
    document.documentElement.style.colorScheme = isDark ? 'dark' : 'light';
};

const subscribe = (callback: () => void) => {
    listeners.add(callback);

    return () => listeners.delete(callback);
};

const notify = (): void => listeners.forEach((listener) => listener());

const mediaQuery = (): MediaQueryList | null => {
    if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') {
        return null;
    }

    return window.matchMedia('(prefers-color-scheme: dark)');
};

const setStoredAppearance = (appearance: Appearance): void => {
    if (typeof window === 'undefined') {
        return;
    }

    try {
        window.localStorage.setItem('appearance', appearance);
    } catch {
        // Ignore storage failures and rely on cookies / in-memory state.
    }
};

const addMediaQueryListener = (query: MediaQueryList, listener: () => void): void => {
    if (typeof query.addEventListener === 'function') {
        query.addEventListener('change', listener);

        return;
    }

    if (typeof query.addListener === 'function') {
        query.addListener(listener);
    }
};

const handleSystemThemeChange = (): void => {
    if (currentAppearance !== 'system') {
        return;
    }

    applyTheme(currentAppearance);
    notify();
};

export function initializeTheme(): void {
    if (typeof window === 'undefined' || hasInitialized) return;

    currentAppearance = getStoredAppearance();
    setStoredAppearance(currentAppearance);
    setCookie('appearance', currentAppearance);
    applyTheme(currentAppearance);

    systemThemeMediaQuery = mediaQuery();
    if (systemThemeMediaQuery) {
        addMediaQueryListener(systemThemeMediaQuery, handleSystemThemeChange);
    }
    hasInitialized = true;
}

export function useAppearance(): UseAppearanceReturn {
    const appearance: Appearance = useSyncExternalStore(
        subscribe,
        () => currentAppearance,
        () => 'system',
    );

    const resolvedAppearance: ResolvedAppearance = useMemo(
        () => (isDarkMode(appearance) ? 'dark' : 'light'),
        [appearance],
    );

    const updateAppearance = useCallback((mode: Appearance): void => {
        currentAppearance = mode;

        // Store in localStorage for client-side persistence...
        setStoredAppearance(mode);

        // Store in cookie for SSR...
        setCookie('appearance', mode);

        applyTheme(mode);
        notify();
    }, []);

    return { appearance, resolvedAppearance, updateAppearance } as const;
}
