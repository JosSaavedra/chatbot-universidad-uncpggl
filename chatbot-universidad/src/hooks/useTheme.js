import { useEffect, useState } from "react";

const STORAGE_KEY = "uncpggl-theme";

// El modo noche es el principal; el claro es una preferencia opcional que se
// recuerda entre visitas.
function getInitialTheme() {
  if (typeof window === "undefined") return "dark";
  try {
    return window.localStorage.getItem(STORAGE_KEY) === "light" ? "light" : "dark";
  } catch {
    return "dark";
  }
}

export function useTheme() {
  const [theme, setTheme] = useState(getInitialTheme);

  useEffect(() => {
    document.documentElement.setAttribute("data-theme", theme);
    try {
      window.localStorage.setItem(STORAGE_KEY, theme);
    } catch {
      // Si el almacenamiento no está disponible, el tema sigue funcionando
      // durante la sesión actual.
    }
  }, [theme]);

  const toggleTheme = () => {
    setTheme((current) => (current === "dark" ? "light" : "dark"));
  };

  return { theme, setTheme, toggleTheme };
}
