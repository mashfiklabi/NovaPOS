import { ref } from 'vue';

export type Theme = 'light' | 'dark';

const theme = ref<Theme>('light');
const isDark = ref(false);

export function useTheme() {
    const applyTheme = (targetTheme: Theme) => {
        theme.value = targetTheme;
        localStorage.setItem('novapos_theme', targetTheme);

        const dark = targetTheme === 'dark';
        isDark.value = dark;

        if (dark) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    };

    const toggleTheme = () => {
        const nextTheme: Theme = theme.value === 'light' ? 'dark' : 'light';
        applyTheme(nextTheme);
    };

    const initTheme = () => {
        const stored = localStorage.getItem('novapos_theme') as Theme | null;
        let initialTheme: Theme;

        if (stored && ['light', 'dark'].includes(stored)) {
            initialTheme = stored;
        } else {
            const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            initialTheme = systemPrefersDark ? 'dark' : 'light';
        }

        applyTheme(initialTheme);
    };

    return {
        theme,
        isDark,
        applyTheme,
        toggleTheme,
        initTheme,
    };
}
