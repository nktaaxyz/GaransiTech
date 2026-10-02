"use client";

import { useEffect } from "react";

const THEME_KEY = "garansitech_theme";

export default function ThemeToggle() {
    useEffect(() => {
        const savedTheme = window.localStorage.getItem(THEME_KEY);
        const initialTheme = savedTheme === "dark" ? "dark" : "light";
        document.documentElement.dataset.theme = initialTheme;
    }, []);

    function toggleTheme() {
        const currentTheme = document.documentElement.dataset.theme === "dark" ? "dark" : "light";
        const nextTheme = currentTheme === "dark" ? "light" : "dark";
        document.documentElement.dataset.theme = nextTheme;
        window.localStorage.setItem(THEME_KEY, nextTheme);
    }

    return (
        <button
            className="theme-toggle"
            type="button"
            onClick={toggleTheme}
            aria-label="Ganti mode tampilan"
            title="Ganti mode tampilan"
        >
            <span className="theme-icon-dark" aria-hidden="true">☾</span>
            <span className="theme-icon-light" aria-hidden="true">☀</span>
        </button>
    );
}