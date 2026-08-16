import { ref } from 'vue';

export type Theme = 'light' | 'dark' | 'system';

const theme = ref<Theme>('system');
const isDark = ref(false);

export function useTheme() {
    const applyTheme = (targetTheme: Theme) => {
        theme.value = targetTheme;
        localStorage.setItem('novapos_theme', targetTheme);

        let dark = false;
        if (targetTheme === 'system') {
            dark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        } else {
            dark = targetTheme === 'dark';
        }

        isDark.value = dark;

        if (dark) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    };

    const toggleTheme = () => {
        if (theme.value === 'light') {
            applyTheme('dark');
        } else if (theme.value === 'dark') {
            applyTheme('system');
        } else {
            applyTheme('light');
        }
    };

    const initTheme = () => {
        const stored = localStorage.getItem('novapos_theme') as Theme | null;
        const initialTheme = stored && ['light', 'dark', 'system'].includes(stored) ? stored : 'system';
        applyTheme(initialTheme);

        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
            if (theme.value === 'system') {
                isDark.value = e.matches;
                if (e.matches) {
                    document.documentElement.classList.add('dark');
                } else {
                    document.documentElement.classList.remove('dark');
                }
            }
        });
    };

    return {
        theme,
        isDark,
        applyTheme,
        toggleTheme,
        initTheme,
    };
}
